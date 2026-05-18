<?php

namespace Tests\Feature;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Invoice;
use App\Models\Scopes\FiscalYearScope;
use App\Models\User;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class BackupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();

        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'backups.create']),
            Permission::firstOrCreate(['name' => 'backups.document-files-size']),
            Permission::firstOrCreate(['name' => 'backups.export']),
            Permission::firstOrCreate(['name' => 'backups.import'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->company->id]);
    }

    private function makeZipUpload(array|string $payload, string $archiveName = 'backup.zip', string $entryName = 'backup.json'): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'backup_test_');
        $this->assertNotFalse($zipPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

        $entryContent = is_array($payload)
            ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : $payload;

        $this->assertNotFalse($entryContent);
        $this->assertTrue($zip->addFromString($entryName, $entryContent));
        $zip->close();

        $archiveContents = File::get($zipPath);
        @unlink($zipPath);

        return UploadedFile::fake()->createWithContent($archiveName, $archiveContents);
    }

    public function test_create_preselects_current_fiscal_year(): void
    {
        $currentYear = (int) toEnglish(jdate('Y'));

        $currentYearCompany = Company::factory()->create(['fiscal_year' => $currentYear]);
        $otherCompany = Company::factory()->create(['fiscal_year' => $currentYear - 1]);

        $response = $this->get(route('backups.create'));

        $response->assertOk();
        $response->assertSee("value=\"{$currentYearCompany->id}\" selected", false);
        $response->assertDontSee("value=\"{$otherCompany->id}\" selected", false);
    }

    public function test_export_downloads_zip_with_json_backup_contents(): void
    {
        $response = $this->post(route('backups.export'), [
            'source_id' => $this->company->id,
            'tables_to_backup' => [FiscalYearSection::SUBJECTS->value],
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('application/zip', $response->headers->get('content-type'));
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response->baseResponse);

        $zipFile = $response->baseResponse->getFile();
        $this->assertNotNull($zipFile);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipFile->getPathname()) === true);
        $this->assertSame(1, $zip->numFiles);

        $jsonEntryName = $zip->getNameIndex(0);
        $jsonContent = $zip->getFromIndex(0);
        $zip->close();

        $this->assertMatchesRegularExpression(
            '/^Amir-.+-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}\.json$/',
            $jsonEntryName
        );
        $this->assertStringStartsWith('Amir-', $jsonEntryName);
        $this->assertNotFalse($jsonContent);

        $decoded = json_decode($jsonContent, true);

        $this->assertIsArray($decoded);
        $this->assertSame($this->company->id, $decoded['meta']['source_company_id']);
        $this->assertSame($this->company->name, $decoded['meta']['source_company_name']);
        $this->assertSame([FiscalYearSection::SUBJECTS->value], $decoded['meta']['sections_exported']);
        $this->assertArrayHasKey(FiscalYearSection::SUBJECTS->value, $decoded);
    }

    public function test_import_uploads_zip_and_creates_new_company(): void
    {
        $response = $this->post(route('backups.import'), [
            'file' => $this->makeZipUpload([
                'meta' => [
                    'source_company_id' => $this->company->id,
                    'source_company_name' => $this->company->name,
                ],
            ]),
            'fiscal_year' => 1410,
            'company_name' => 'Imported Company',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('companies', [
            'name' => 'Imported Company',
            'fiscal_year' => 1410,
        ]);
    }

    public function test_import_rejects_invalid_json_inside_zip_upload(): void
    {
        $existingCompanies = Company::count();

        $response = $this->post(route('backups.import'), [
            'file' => $this->makeZipUpload('{"meta": invalid json}'),
            'fiscal_year' => 1411,
            'company_name' => 'Broken Import',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame($existingCompanies, Company::count());
    }

    public function test_import_rejects_zip_upload_without_json_file(): void
    {
        $existingCompanies = Company::count();

        $response = $this->post(route('backups.import'), [
            'file' => $this->makeZipUpload('plain text file', 'backup.zip', 'backup.txt'),
            'fiscal_year' => 1412,
            'company_name' => 'Missing Json',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame($existingCompanies, Company::count());
    }

    public function test_import_rejects_empty_json(): void
    {
        $response = $this->post(route('backups.import'), [
            'file' => $this->makeZipUpload(''),
            'fiscal_year' => 1414,
            'company_name' => 'Empty JSON',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_invoices_are_imported_when_documents_section_is_absent(): void
    {
        // Minimal payload: subjects + customers + invoices, but NO documents section.
        // Before the fix, empty documentMapping caused all invoices to be skipped.
        $payload = [
            'subjects' => [
                ['id' => 1, 'code' => '1',   'name' => 'Group Subject',    'parent_id' => null, 'company_id' => 0, 'type' => 'both', 'subjectable_type' => CustomerGroup::class, 'subjectable_id' => 1, 'is_permanent' => false],
                ['id' => 2, 'code' => '11', 'name' => 'Customer Subject', 'parent_id' => 1,    'company_id' => 0, 'type' => 'both', 'subjectable_type' => Customer::class, 'subjectable_id' => 1, 'is_permanent' => false],
            ],
            'customer_groups' => [
                ['id' => 1, 'name' => 'Test Group', 'description' => null, 'subject_id' => 1, 'company_id' => 0],
            ],
            'customers' => [
                ['id' => 1, 'name' => 'Test Customer', 'company_id' => 0, 'group_id' => 1, 'subject_id' => 2, 'introducer_id' => null],
            ],
            'comments' => [],
            'invoices' => [
                ['id' => 1, 'number' => '1', 'date' => '2026-01-01', 'customer_id' => 1, 'document_id' => null,
                    'company_id' => 0, 'subtraction' => 0, 'vat' => 0, 'amount' => 100, 'returned_invoice_id' => null,
                    'creator_id' => null, 'approver_id' => null, 'status' => 'unapproved', 'invoice_type' => 'sell'],
            ],
            'invoice_items' => [],
            'ancillary_costs' => [],
            'ancillary_cost_items' => [],
        ];

        $newCompany = FiscalYearService::importData($payload, ['name' => 'Restored Co', 'fiscal_year' => 1403]);

        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('company_id', $newCompany->id)->count());
    }

    public function test_export_filename_replaces_spaces_with_hyphens(): void
    {
        $company = Company::factory()->create(['name' => 'My Test Company']);

        $response = $this->post(route('backups.export'), [
            'source_id' => $company->id,
            'tables_to_backup' => [FiscalYearSection::SUBJECTS->value],
        ]);

        $response->assertStatus(200);

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $jsonEntryName = $zip->getNameIndex(0);
        $zip->close();

        $this->assertStringContainsString('My-Test-Company', $jsonEntryName);
        $this->assertStringNotContainsString(' ', $jsonEntryName);
    }

    public function test_document_files_size_endpoint_returns_size_in_mb(): void
    {
        Storage::fake('public');

        $document = Document::factory()->create(['company_id' => $this->company->id]);
        $path = "documents/{$document->id}/test.pdf";
        Storage::disk('public')->put($path, str_repeat('x', 1024 * 1024)); // 1 MiB
        DocumentFile::factory()->withDocument($document)->create(['path' => $path]);

        $response = $this->get(route('backups.document-files-size', ['source_id' => $this->company->id]));

        $response->assertOk();
        $response->assertJsonStructure(['size_mb']);
        $response->assertJson(['size_mb' => 1.0]);
    }

    public function test_export_appends_document_files_to_zip_when_section_selected(): void
    {
        Storage::fake('public');

        $document = Document::factory()->create(['company_id' => $this->company->id]);
        $path = "documents/{$document->id}/report.pdf";
        Storage::disk('public')->put($path, 'dummy pdf content');
        DocumentFile::factory()->withDocument($document)->create(['path' => $path]);

        $response = $this->post(route('backups.export'), [
            'source_id' => $this->company->id,
            'tables_to_backup' => [
                FiscalYearSection::SUBJECTS->value,
                FiscalYearSection::DOCUMENT_FILES->value,
            ],
        ]);

        $response->assertStatus(200);

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertCount(2, $entries);
        $this->assertTrue(collect($entries)->contains(fn ($n) => str_starts_with($n, 'files/')));
        $this->assertTrue(collect($entries)->contains(fn ($n) => str_ends_with($n, '.json')));
    }

    public function test_import_restores_binary_document_files_from_zip(): void
    {
        Storage::fake('public');

        $payload = [
            'documents' => [
                ['id' => 99, 'number' => 1, 'date' => '2026-01-01', 'creator_id' => null, 'title' => 'Doc', 'company_id' => 0],
            ],
            'document_files' => [
                ['id' => 1, 'document_id' => 99, 'user_id' => null, 'title' => 'Test File', 'name' => 'test.pdf', 'path' => 'documents/99/test.pdf'],
            ],
        ];

        $zipPath = tempnam(sys_get_temp_dir(), 'bin_import_test_');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('backup.json', json_encode($payload));
        $zip->addFromString('files/documents/99/test.pdf', 'binary file content');
        $zip->close();

        $upload = UploadedFile::fake()->createWithContent('backup.zip', File::get($zipPath));
        @unlink($zipPath);

        $this->post(route('backups.import'), [
            'file' => $upload,
            'fiscal_year' => 1405,
            'company_name' => 'Binary Import Co',
        ])->assertRedirect(route('home'));

        $newCompany = Company::where('name', 'Binary Import Co')->firstOrFail();

        $documentIds = Document::withoutGlobalScope(FiscalYearScope::class)
            ->where('company_id', $newCompany->id)
            ->pluck('id');

        $docFile = DocumentFile::whereIn('document_id', $documentIds)->firstOrFail();

        $expectedPath = "documents/{$docFile->document_id}/test.pdf";
        $this->assertSame($expectedPath, $docFile->path);
        Storage::disk('public')->assertExists($expectedPath);
    }
}
