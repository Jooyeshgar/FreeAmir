<?php

namespace Tests\Feature;

use App\Enums\FiscalYearSection;
use App\Enums\PayrollStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\OrganizationUnit;
use App\Models\Payroll;
use App\Models\PayrollStatusHistory;
use App\Models\Scopes\FiscalYearScope;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Services\DocumentService;
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
        Storage::disk('public')->put($path, str_repeat('x', 1024 * 1024));
        DocumentFile::factory()->withDocument($document)->create(['path' => $path]);

        $response = $this->get(route('backups.document-files-size', ['source_id' => $this->company->id]));

        $response->assertOk();
        $response->assertJsonStructure(['size_mb']);
        $response->assertJson(['size_mb' => 1.0]);
    }

    public function test_export_embeds_document_file_base64_in_json_when_section_selected(): void
    {
        Storage::fake('public');

        $document = Document::factory()->create(['company_id' => $this->company->id]);
        $fileContent = 'dummy pdf content';
        $path = "documents/{$document->id}/report.pdf";
        Storage::disk('public')->put($path, $fileContent);
        DocumentFile::factory()->withDocument($document)->create(['path' => $path]);

        $response = $this->post(route('backups.export'), [
            'source_id' => $this->company->id,
            'tables_to_backup' => [
                FiscalYearSection::DOCUMENTS->value,
                FiscalYearSection::DOCUMENT_FILES->value,
            ],
        ]);

        $response->assertStatus(200);

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());

        $this->assertSame(1, $zip->numFiles);
        $jsonContent = $zip->getFromIndex(0);
        $zip->close();

        $data = json_decode($jsonContent, true);
        $this->assertNotEmpty($data['document_files']);

        $docFile = $data['document_files'][0];
        $this->assertArrayHasKey('document_file', $docFile);
        $this->assertSame('report.pdf', $docFile['document_file']['name']);
        $this->assertSame('pdf', $docFile['document_file']['extension']);
        $this->assertSame(strlen($fileContent), $docFile['document_file']['size']);
        $this->assertSame(hash('sha256', $fileContent), $docFile['document_file']['sha256']);
        $this->assertSame(base64_encode($fileContent), $docFile['document_file']['content']);
    }

    public function test_export_document_file_metadata_is_complete(): void
    {
        Storage::fake('public');

        $document = Document::factory()->create(['company_id' => $this->company->id]);
        $fileContent = str_repeat('A', 1024);
        $path = "documents/{$document->id}/invoice.pdf";
        Storage::disk('public')->put($path, $fileContent);
        DocumentFile::factory()->withDocument($document)->create(['path' => $path]);

        $response = $this->post(route('backups.export'), [
            'source_id' => $this->company->id,
            'tables_to_backup' => [
                FiscalYearSection::DOCUMENTS->value,
                FiscalYearSection::DOCUMENT_FILES->value,
            ],
        ]);

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $data = json_decode($zip->getFromIndex(0), true);
        $zip->close();

        $meta = $data['document_files'][0]['document_file'];

        $this->assertArrayHasKey('name', $meta);
        $this->assertArrayHasKey('mime', $meta);
        $this->assertArrayHasKey('extension', $meta);
        $this->assertArrayHasKey('size', $meta);
        $this->assertArrayHasKey('sha256', $meta);
        $this->assertArrayHasKey('content', $meta);
        $this->assertSame(1024, $meta['size']);
        $this->assertSame(hash('sha256', $fileContent), $meta['sha256']);
        $this->assertSame('pdf', $meta['extension']);
        $this->assertSame('invoice.pdf', $meta['name']);
    }

    public function test_import_restores_document_files_from_embedded_base64(): void
    {
        Storage::fake('public');

        $binaryContent = 'binary file content';

        $payload = [
            'documents' => [
                ['id' => 99, 'number' => 1, 'date' => '2026-01-01', 'creator_id' => null, 'title' => 'Doc', 'company_id' => 0],
            ],
            'document_files' => [
                [
                    'id' => 1, 'document_id' => 99, 'user_id' => null,
                    'title' => 'Test File', 'name' => 'test.pdf', 'path' => 'documents/99/test.pdf',
                    'document_file' => [
                        'name' => 'test.pdf',
                        'mime' => 'application/pdf',
                        'extension' => 'pdf',
                        'size' => strlen($binaryContent),
                        'sha256' => hash('sha256', $binaryContent),
                        'content' => base64_encode($binaryContent),
                    ],
                ],
            ],
        ];

        $zipPath = tempnam(sys_get_temp_dir(), 'base64_import_test_');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('backup.json', json_encode($payload));
        $zip->close();

        $upload = UploadedFile::fake()->createWithContent('backup.zip', File::get($zipPath));
        @unlink($zipPath);

        $this->post(route('backups.import'), [
            'file' => $upload,
            'fiscal_year' => 1405,
            'company_name' => 'Base64 Import Co',
        ])->assertRedirect(route('home'));

        $newCompany = Company::where('name', 'Base64 Import Co')->firstOrFail();

        $documentIds = Document::withoutGlobalScope(FiscalYearScope::class)
            ->where('company_id', $newCompany->id)
            ->pluck('id');

        $docFile = DocumentFile::whereIn('document_id', $documentIds)->firstOrFail();

        $expectedPath = "documents/{$docFile->document_id}/test.pdf";
        $this->assertSame($expectedPath, $docFile->path);
        Storage::disk('public')->assertExists($expectedPath);
        $this->assertSame($binaryContent, Storage::disk('public')->get($expectedPath));
    }

    public function test_import_checksum_mismatch_throws_exception(): void
    {
        Storage::fake('public');

        $payload = [
            'documents' => [
                ['id' => 1, 'number' => 1, 'date' => '2026-01-01', 'creator_id' => null, 'title' => 'Doc', 'company_id' => 0],
            ],
            'document_files' => [
                [
                    'id' => 1, 'document_id' => 1, 'user_id' => null,
                    'title' => 'File', 'name' => 'file.pdf', 'path' => 'documents/1/file.pdf',
                    'document_file' => [
                        'name' => 'file.pdf',
                        'mime' => 'application/pdf',
                        'extension' => 'pdf',
                        'size' => 7,
                        'sha256' => 'deliberately_wrong_sha256_value',
                        'content' => base64_encode('content'),
                    ],
                ],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SHA-256 checksum mismatch/');

        FiscalYearService::importData($payload, ['name' => 'Checksum Fail Co', 'fiscal_year' => 1407]);
    }

    public function test_import_base64_checksum_validates_on_correct_hash(): void
    {
        Storage::fake('public');

        $content = 'correct content';

        $payload = [
            'documents' => [
                ['id' => 1, 'number' => 1, 'date' => '2026-01-01', 'creator_id' => null, 'title' => 'Doc', 'company_id' => 0],
            ],
            'document_files' => [
                [
                    'id' => 1, 'document_id' => 1, 'user_id' => null,
                    'title' => 'File', 'name' => 'valid.pdf', 'path' => 'documents/1/valid.pdf',
                    'document_file' => [
                        'name' => 'valid.pdf',
                        'mime' => 'application/pdf',
                        'extension' => 'pdf',
                        'size' => strlen($content),
                        'sha256' => hash('sha256', $content),
                        'content' => base64_encode($content),
                    ],
                ],
            ],
        ];

        $company = FiscalYearService::importData($payload, ['name' => 'Checksum OK Co', 'fiscal_year' => 1408]);

        $documentIds = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $company->id)->pluck('id');
        $docFile = DocumentFile::whereIn('document_id', $documentIds)->firstOrFail();

        Storage::disk('public')->assertExists($docFile->path);
        $this->assertSame($content, Storage::disk('public')->get($docFile->path));
    }

    public function test_export_and_import_large_file_roundtrip(): void
    {
        Storage::fake('public');

        $largeContent = str_repeat('X', 200 * 1024); // 200 KiB — spans multiple 57 KiB encode chunks
        $document = Document::factory()->create(['company_id' => $this->company->id]);
        $path = "documents/{$document->id}/large.bin";
        Storage::disk('public')->put($path, $largeContent);
        DocumentFile::factory()->withDocument($document)->create(['path' => $path]);

        $response = $this->post(route('backups.export'), [
            'source_id' => $this->company->id,
            'tables_to_backup' => [
                FiscalYearSection::DOCUMENTS->value,
                FiscalYearSection::DOCUMENT_FILES->value,
            ],
        ]);

        $response->assertStatus(200);

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $data = json_decode($zip->getFromIndex(0), true);
        $zip->close();

        $meta = $data['document_files'][0]['document_file'];
        $this->assertSame(200 * 1024, $meta['size']);
        $this->assertSame(hash('sha256', $largeContent), $meta['sha256']);
        $this->assertSame($largeContent, base64_decode($meta['content']));
    }

    public function test_export_documents_section_without_document_files_section_omits_base64(): void
    {
        Storage::fake('public');

        $document = Document::factory()->create(['company_id' => $this->company->id]);
        Storage::disk('public')->put("documents/{$document->id}/note.pdf", 'content');
        DocumentFile::factory()->withDocument($document)->create(['path' => "documents/{$document->id}/note.pdf"]);

        $response = $this->post(route('backups.export'), [
            'source_id' => $this->company->id,
            'tables_to_backup' => [FiscalYearSection::DOCUMENTS->value],
        ]);

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $data = json_decode($zip->getFromIndex(0), true);
        $zip->close();

        $this->assertNotEmpty($data['document_files']);
        $this->assertArrayNotHasKey('document_file', $data['document_files'][0]);
    }

    public function test_export_skips_document_file_missing_from_storage_gracefully(): void
    {
        Storage::fake('public');

        $document = Document::factory()->create(['company_id' => $this->company->id]);
        DocumentFile::factory()->withDocument($document)->create(['path' => "documents/{$document->id}/ghost.pdf"]);

        $response = $this->post(route('backups.export'), [
            'source_id' => $this->company->id,
            'tables_to_backup' => [
                FiscalYearSection::DOCUMENTS->value,
                FiscalYearSection::DOCUMENT_FILES->value,
            ],
        ]);

        $response->assertStatus(200);

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $data = json_decode($zip->getFromIndex(0), true);
        $zip->close();

        $this->assertNotEmpty($data['document_files']);
        $this->assertArrayNotHasKey('document_file', $data['document_files'][0]);
    }

    public function test_export_multiple_document_files_all_get_embedded(): void
    {
        Storage::fake('public');

        $doc1 = Document::factory()->create(['company_id' => $this->company->id]);
        $doc2 = Document::factory()->create(['company_id' => $this->company->id]);
        $content1 = 'first file content';
        $content2 = 'second file content';

        Storage::disk('public')->put("documents/{$doc1->id}/a.pdf", $content1);
        Storage::disk('public')->put("documents/{$doc2->id}/b.pdf", $content2);
        DocumentFile::factory()->withDocument($doc1)->create(['path' => "documents/{$doc1->id}/a.pdf"]);
        DocumentFile::factory()->withDocument($doc2)->create(['path' => "documents/{$doc2->id}/b.pdf"]);

        $response = $this->post(route('backups.export'), [
            'source_id' => $this->company->id,
            'tables_to_backup' => [
                FiscalYearSection::DOCUMENTS->value,
                FiscalYearSection::DOCUMENT_FILES->value,
            ],
        ]);

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $data = json_decode($zip->getFromIndex(0), true);
        $zip->close();

        $this->assertCount(2, $data['document_files']);

        $contents = collect($data['document_files'])
            ->map(fn ($f) => base64_decode($f['document_file']['content']))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            collect([$content1, $content2])->sort()->values()->all(),
            $contents
        );
    }

    public function test_import_skips_document_file_entry_without_base64_content(): void
    {
        Storage::fake('public');

        $payload = [
            'documents' => [
                ['id' => 1, 'number' => 1, 'date' => '2026-01-01', 'creator_id' => null, 'title' => 'Doc', 'company_id' => 0],
            ],
            'document_files' => [
                ['id' => 1, 'document_id' => 1, 'user_id' => null, 'title' => 'Plain', 'name' => 'plain.pdf', 'path' => 'documents/1/plain.pdf'],
            ],
        ];

        $company = FiscalYearService::importData($payload, ['name' => 'No Content Co', 'fiscal_year' => 1409]);

        $documentIds = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $company->id)->pluck('id');

        $this->assertSame(0, DocumentFile::whereIn('document_id', $documentIds)->count());
        Storage::disk('public')->assertMissing('documents/1/plain.pdf');
    }

    public function test_import_multiple_document_files_all_restored(): void
    {
        Storage::fake('public');

        $c1 = 'alpha content';
        $c2 = 'beta content';

        $payload = [
            'documents' => [
                ['id' => 10, 'number' => 1, 'date' => '2026-01-01', 'creator_id' => null, 'title' => 'A', 'company_id' => 0],
                ['id' => 20, 'number' => 2, 'date' => '2026-01-01', 'creator_id' => null, 'title' => 'B', 'company_id' => 0],
            ],
            'document_files' => [
                [
                    'id' => 1, 'document_id' => 10, 'user_id' => null, 'title' => 'F1', 'name' => 'alpha.pdf', 'path' => 'documents/10/alpha.pdf',
                    'document_file' => ['name' => 'alpha.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf', 'size' => strlen($c1), 'sha256' => hash('sha256', $c1), 'content' => base64_encode($c1)],
                ],
                [
                    'id' => 2, 'document_id' => 20, 'user_id' => null, 'title' => 'F2', 'name' => 'beta.pdf', 'path' => 'documents/20/beta.pdf',
                    'document_file' => ['name' => 'beta.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf', 'size' => strlen($c2), 'sha256' => hash('sha256', $c2), 'content' => base64_encode($c2)],
                ],
            ],
        ];

        $company = FiscalYearService::importData($payload, ['name' => 'Multi File Co', 'fiscal_year' => 1410]);

        $documentIds = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $company->id)->pluck('id');
        $docFiles = DocumentFile::whereIn('document_id', $documentIds)->get();

        $this->assertCount(2, $docFiles);

        foreach ($docFiles as $docFile) {
            Storage::disk('public')->assertExists($docFile->path);
        }

        $restoredContents = $docFiles->map(fn ($f) => Storage::disk('public')->get($f->path))->sort()->values()->all();
        $this->assertSame(collect([$c1, $c2])->sort()->values()->all(), $restoredContents);
    }

    public function test_import_invalid_base64_throws_exception(): void
    {
        Storage::fake('public');

        $payload = [
            'documents' => [
                ['id' => 1, 'number' => 1, 'date' => '2026-01-01', 'creator_id' => null, 'title' => 'Doc', 'company_id' => 0],
            ],
            'document_files' => [
                [
                    'id' => 1, 'document_id' => 1, 'user_id' => null,
                    'title' => 'File', 'name' => 'file.pdf', 'path' => 'documents/1/file.pdf',
                    'document_file' => [
                        'name' => 'file.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf',
                        'size' => 7, 'sha256' => 'irrelevant',
                        'content' => '!!!NOT_VALID_BASE64!!!', // contains chars outside base64 alphabet
                    ],
                ],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid Base64/');

        FiscalYearService::importData($payload, ['name' => 'Bad Base64 Co', 'fiscal_year' => 1411]);
    }

    public function test_export_excludes_document_files_when_only_document_files_selected(): void
    {
        Storage::fake('public');

        $document = Document::factory()->create(['company_id' => $this->company->id]);
        $path = "documents/{$document->id}/test.pdf";
        Storage::disk('public')->put($path, 'content');
        DocumentFile::factory()->withDocument($document)->create(['path' => $path]);

        $response = $this->post(route('backups.export'), [
            'source_id' => $this->company->id,
            'tables_to_backup' => [FiscalYearSection::DOCUMENT_FILES->value],
        ]);

        $response->assertStatus(200);

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $data = json_decode($zip->getFromIndex(0), true);
        $zip->close();

        $this->assertArrayNotHasKey('document_files', $data);
        $this->assertNotContains(FiscalYearSection::DOCUMENT_FILES->value, $data['meta']['sections_exported']);
    }

    public function test_export_strips_document_files_but_keeps_other_sections_when_documents_missing(): void
    {
        Storage::fake('public');

        $document = Document::factory()->create(['company_id' => $this->company->id]);
        Storage::disk('public')->put("documents/{$document->id}/f.pdf", 'x');
        DocumentFile::factory()->withDocument($document)->create(['path' => "documents/{$document->id}/f.pdf"]);

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
        $data = json_decode($zip->getFromIndex(0), true);
        $zip->close();

        $this->assertArrayNotHasKey('document_files', $data);
        $this->assertArrayHasKey('subjects', $data);
        $this->assertContains(FiscalYearSection::SUBJECTS->value, $data['meta']['sections_exported']);
        $this->assertNotContains(FiscalYearSection::DOCUMENT_FILES->value, $data['meta']['sections_exported']);
    }

    public function test_import_skips_document_files_when_documents_section_absent_from_payload(): void
    {
        Storage::fake('public');

        $fileContent = 'orphaned file content';

        $payload = [
            'document_files' => [
                [
                    'id' => 5, 'document_id' => 99, 'user_id' => null,
                    'title' => 'Orphan', 'name' => 'orphan.pdf', 'path' => 'documents/99/orphan.pdf',
                    'document_file' => [
                        'name' => 'orphan.pdf',
                        'mime' => 'application/pdf',
                        'extension' => 'pdf',
                        'size' => strlen($fileContent),
                        'sha256' => hash('sha256', $fileContent),
                        'content' => base64_encode($fileContent),
                    ],
                ],
            ],
        ];

        $company = FiscalYearService::importData($payload, ['name' => 'No Docs Co', 'fiscal_year' => 1412]);

        $documentIds = Document::withoutGlobalScope(FiscalYearScope::class)
            ->where('company_id', $company->id)
            ->pluck('id');

        $this->assertSame(0, DocumentFile::whereIn('document_id', $documentIds)->count());
        Storage::disk('public')->assertMissing('documents/99/orphan.pdf');
    }

    public function test_import_endpoint_skips_document_files_when_documents_not_in_backup(): void
    {
        Storage::fake('public');

        $fileContent = 'embedded but orphaned';

        $payload = [
            'meta' => [
                'source_company_id' => $this->company->id,
                'source_company_name' => $this->company->name,
            ],
            'document_files' => [
                [
                    'id' => 1, 'document_id' => 1, 'user_id' => null,
                    'title' => 'File', 'name' => 'doc.pdf', 'path' => 'documents/1/doc.pdf',
                    'document_file' => [
                        'name' => 'doc.pdf',
                        'mime' => 'application/pdf',
                        'extension' => 'pdf',
                        'size' => strlen($fileContent),
                        'sha256' => hash('sha256', $fileContent),
                        'content' => base64_encode($fileContent),
                    ],
                ],
            ],
        ];

        $response = $this->post(route('backups.import'), [
            'file' => $this->makeZipUpload($payload),
            'fiscal_year' => 1413,
            'company_name' => 'No Docs Import Co',
        ]);

        $response->assertRedirect(route('home'));

        $newCompany = Company::where('name', 'No Docs Import Co')->firstOrFail();

        $documentIds = Document::withoutGlobalScope(FiscalYearScope::class)
            ->where('company_id', $newCompany->id)
            ->pluck('id');

        $this->assertSame(0, DocumentFile::whereIn('document_id', $documentIds)->count());
        Storage::disk('public')->assertMissing('documents/1/doc.pdf');
    }

    public function test_deleting_document_file_removes_storage_file(): void
    {
        Storage::fake('public');

        $document = Document::factory()->create(['company_id' => $this->company->id]);
        $path = "documents/{$document->id}/attachment.pdf";
        Storage::disk('public')->put($path, 'file body');
        $docFile = DocumentFile::factory()->withDocument($document)->create(['path' => $path]);

        Storage::disk('public')->assertExists($path);

        (new \App\Services\DocumentFileService)->delete($docFile);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('document_files', ['id' => $docFile->id]);
    }

    public function test_deleting_document_removes_all_document_files_and_storage_files(): void
    {
        $disk = Storage::fake('public');

        $document = Document::factory()->create(['company_id' => $this->company->id]);
        $paths = [
            "documents/{$document->id}/file1.pdf",
            "documents/{$document->id}/file2.pdf",
        ];

        $fileIds = [];
        foreach ($paths as $path) {
            $disk->put($path, 'content');
            $fileIds[] = DocumentFile::factory()->withDocument($document)->create(['path' => $path])->id;
        }

        config(['active-company-id' => $this->company->id]);
        DocumentService::deleteDocument($document->id);

        foreach ($paths as $path) {
            $disk->assertMissing($path);
        }

        foreach ($fileIds as $id) {
            $this->assertDatabaseMissing('document_files', ['id' => $id]);
        }

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    public function test_employees_section_export_includes_organization_units(): void
    {
        OrganizationUnit::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Engineering',
            'code' => 'ENG-01',
        ]);

        $exportData = FiscalYearService::exportData($this->company->id, [FiscalYearSection::EMPLOYEES->value]);

        $this->assertArrayHasKey('organization_units', $exportData);
        $this->assertCount(1, $exportData['organization_units']);
        $this->assertSame('Engineering', $exportData['organization_units'][0]['name']);
    }

    public function test_organization_unit_ids_are_remapped_on_employee_import(): void
    {
        $payload = [
            'org_charts' => [],
            'work_sites' => [
                ['id' => 10, 'name' => 'HQ', 'code' => 'HQ-01', 'company_id' => 0, 'address' => null, 'phone' => null, 'is_active' => true],
            ],
            'work_site_contracts' => [],
            'work_shifts' => [
                ['id' => 20, 'name' => 'Day Shift', 'company_id' => 0, 'start_time' => '08:00', 'end_time' => '17:00', 'float' => 0, 'break' => 0, 'paid_leave' => 1200, 'is_active' => true],
            ],
            'organization_units' => [
                ['id' => 30, 'name' => 'HR Department', 'code' => 'HR-01', 'company_id' => 0, 'parent_id' => null, 'description' => null, 'is_active' => true],
            ],
            'employees' => [
                $this->makeMinimalEmployeeData(40, 10, 20, 30),
            ],
        ];

        $newCompany = FiscalYearService::importData($payload, ['name' => 'Org Unit Remap Co', 'fiscal_year' => 1404]);

        $newOrgUnit = OrganizationUnit::withoutGlobalScope(FiscalYearScope::class)
            ->where('company_id', $newCompany->id)
            ->where('name', 'HR Department')
            ->first();

        $this->assertNotNull($newOrgUnit, 'Org unit should be created in the new company');
        $this->assertNotSame(30, $newOrgUnit->id, 'Org unit ID must be remapped, not kept from source');

        $employee = Employee::withoutGlobalScope(FiscalYearScope::class)
            ->where('company_id', $newCompany->id)
            ->first();

        $this->assertNotNull($employee);
        $this->assertSame($newOrgUnit->id, $employee->organization_unit_id, 'Employee must point to new org unit ID, not source ID');
    }

    public function test_organization_unit_parent_child_hierarchy_preserved_on_import(): void
    {
        $payload = [
            'org_charts' => [],
            'work_sites' => [
                ['id' => 1, 'name' => 'HQ', 'code' => 'HQ-01', 'company_id' => 0, 'address' => null, 'phone' => null, 'is_active' => true],
            ],
            'work_site_contracts' => [],
            'work_shifts' => [
                ['id' => 1, 'name' => 'Day', 'company_id' => 0, 'start_time' => '08:00', 'end_time' => '17:00', 'float' => 0, 'break' => 0, 'paid_leave' => 1200, 'is_active' => true],
            ],
            'organization_units' => [
                ['id' => 1, 'name' => 'Company Root', 'code' => 'ROOT', 'company_id' => 0, 'parent_id' => null, 'description' => null, 'is_active' => true],
                ['id' => 2, 'name' => 'Engineering', 'code' => 'ENG', 'company_id' => 0, 'parent_id' => 1, 'description' => null, 'is_active' => true],
            ],
            'employees' => [
                $this->makeMinimalEmployeeData(1, 1, 1, 2), // assigned to child unit (id=2)
            ],
        ];

        $newCompany = FiscalYearService::importData($payload, ['name' => 'Hierarchy Co', 'fiscal_year' => 1404]);

        $parentUnit = OrganizationUnit::withoutGlobalScope(FiscalYearScope::class)
            ->where('company_id', $newCompany->id)->where('name', 'Company Root')->first();
        $childUnit = OrganizationUnit::withoutGlobalScope(FiscalYearScope::class)
            ->where('company_id', $newCompany->id)->where('name', 'Engineering')->first();

        $this->assertNotNull($parentUnit);
        $this->assertNotNull($childUnit);
        $this->assertNull($parentUnit->parent_id, 'Root unit must have no parent');
        $this->assertSame($parentUnit->id, $childUnit->parent_id, 'Child must point to new parent ID, not source parent ID');

        $employee = Employee::withoutGlobalScope(FiscalYearScope::class)
            ->where('company_id', $newCompany->id)->first();
        $this->assertSame($childUnit->id, $employee->organization_unit_id);
    }

    public function test_employee_with_null_organization_unit_imports_with_null(): void
    {
        $payload = [
            'org_charts' => [],
            'work_sites' => [
                ['id' => 1, 'name' => 'HQ', 'code' => 'HQ-01', 'company_id' => 0, 'address' => null, 'phone' => null, 'is_active' => true],
            ],
            'work_site_contracts' => [],
            'work_shifts' => [
                ['id' => 1, 'name' => 'Day', 'company_id' => 0, 'start_time' => '08:00', 'end_time' => '17:00', 'float' => 0, 'break' => 0, 'paid_leave' => 1200, 'is_active' => true],
            ],
            'organization_units' => [],
            'employees' => [
                $this->makeMinimalEmployeeData(1, 1, 1, null), // no org unit
            ],
        ];

        $newCompany = FiscalYearService::importData($payload, ['name' => 'Null OrgUnit Co', 'fiscal_year' => 1404]);

        $employee = Employee::withoutGlobalScope(FiscalYearScope::class)
            ->where('company_id', $newCompany->id)->first();

        $this->assertNotNull($employee);
        $this->assertNull($employee->organization_unit_id);
    }

    public function test_full_export_import_roundtrip_preserves_organization_unit_assignment(): void
    {
        $orgUnit = OrganizationUnit::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Finance Department',
        ]);
        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
            'organization_unit_id' => $orgUnit->id,
        ]);

        $exportData = FiscalYearService::exportData($this->company->id, [FiscalYearSection::EMPLOYEES->value]);

        $this->assertArrayHasKey('organization_units', $exportData);
        $this->assertNotEmpty($exportData['organization_units']);

        $newCompany = FiscalYearService::importData($exportData, ['name' => 'Roundtrip OrgUnit Co', 'fiscal_year' => 1405]);

        $newOrgUnit = OrganizationUnit::withoutGlobalScope(FiscalYearScope::class)
            ->where('company_id', $newCompany->id)->where('name', 'Finance Department')->first();

        $this->assertNotNull($newOrgUnit, 'Org unit must be recreated in new company');
        $this->assertNotSame($orgUnit->id, $newOrgUnit->id, 'New org unit must have a new ID');

        $newEmployee = Employee::withoutGlobalScope(FiscalYearScope::class)
            ->where('company_id', $newCompany->id)->first();

        $this->assertNotNull($newEmployee);
        $this->assertSame($newOrgUnit->id, $newEmployee->organization_unit_id, 'Employee must be linked to the new org unit, not the source one');
    }

    public function test_payrolls_section_export_includes_status_histories(): void
    {
        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ]);
        $payroll = Payroll::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'year' => 1404, 'month' => 1,
            'total_earnings' => 10_000_000, 'total_deductions' => 1_000_000, 'net_payment' => 9_000_000,
            'employer_insurance' => 2_000_000, 'tax_base_amount' => 9_000_000, 'income_tax_amount' => 500_000,
            'status' => PayrollStatus::PendingManagerApproval,
        ]);
        PayrollStatusHistory::create([
            'payroll_id' => $payroll->id,
            'from_status' => PayrollStatus::Draft->value,
            'to_status' => PayrollStatus::PendingManagerApproval->value,
            'changed_by' => null,
            'note' => 'Submitted for review',
        ]);

        $exportData = FiscalYearService::exportData($this->company->id, [FiscalYearSection::PAYROLLS->value]);

        $this->assertArrayHasKey('payroll_status_histories', $exportData);
        $this->assertCount(1, $exportData['payroll_status_histories']);
        $this->assertSame(PayrollStatus::Draft->value, $exportData['payroll_status_histories'][0]['from_status']);
        $this->assertSame(PayrollStatus::PendingManagerApproval->value, $exportData['payroll_status_histories'][0]['to_status']);
        $this->assertSame('Submitted for review', $exportData['payroll_status_histories'][0]['note']);
    }

    public function test_payrolls_section_export_includes_no_status_histories_when_there_are_none(): void
    {
        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ]);
        Payroll::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'year' => 1404, 'month' => 2,
            'total_earnings' => 5_000_000, 'total_deductions' => 500_000, 'net_payment' => 4_500_000,
            'employer_insurance' => 1_000_000, 'tax_base_amount' => 4_500_000, 'income_tax_amount' => 200_000,
            'status' => PayrollStatus::Draft,
        ]);

        $exportData = FiscalYearService::exportData($this->company->id, [FiscalYearSection::PAYROLLS->value]);

        $this->assertArrayHasKey('payroll_status_histories', $exportData);
        $this->assertEmpty($exportData['payroll_status_histories']);
    }

    public function test_payroll_status_history_payroll_ids_are_remapped_on_import(): void
    {
        $payload = $this->makeMinimalPayrollPayload([
            'payroll_status_histories' => [
                [
                    'id' => 1, 'payroll_id' => 1,
                    'from_status' => PayrollStatus::Draft->value,
                    'to_status' => PayrollStatus::PendingManagerApproval->value,
                    'changed_by' => null, 'changed_at' => '2025-01-15 10:00:00', 'note' => 'Ready for review',
                ],
            ],
        ]);

        $newCompany = FiscalYearService::importData($payload, ['name' => 'PSH Remap Co', 'fiscal_year' => 1404]);

        $newPayroll = Payroll::withoutGlobalScopes()->where('company_id', $newCompany->id)->first();
        $this->assertNotNull($newPayroll);
        $this->assertNotSame(1, $newPayroll->id, 'Payroll ID must be remapped');

        $history = PayrollStatusHistory::where('payroll_id', $newPayroll->id)->first();
        $this->assertNotNull($history, 'Status history must be created using the new payroll ID');
        $this->assertSame(PayrollStatus::Draft->value, $history->from_status->value);
        $this->assertSame(PayrollStatus::PendingManagerApproval->value, $history->to_status->value);
        $this->assertSame('Ready for review', $history->note);
    }

    public function test_payroll_status_history_with_unknown_payroll_id_is_skipped_gracefully(): void
    {
        $payload = $this->makeMinimalPayrollPayload([
            'payroll_status_histories' => [
                [
                    'id' => 99, 'payroll_id' => 9999, // unknown — not in the payload's payrolls list
                    'from_status' => PayrollStatus::Draft->value,
                    'to_status' => PayrollStatus::PendingManagerApproval->value,
                    'changed_by' => null, 'changed_at' => '2025-01-15 10:00:00', 'note' => null,
                ],
            ],
        ]);

        $newCompany = FiscalYearService::importData($payload, ['name' => 'PSH Skip Co', 'fiscal_year' => 1404]);

        $newPayroll = Payroll::withoutGlobalScopes()->where('company_id', $newCompany->id)->first();
        $this->assertNotNull($newPayroll);

        $this->assertSame(
            0,
            PayrollStatusHistory::where('payroll_id', $newPayroll->id)->count(),
            'Status history with unknown payroll_id must be skipped without exception'
        );
    }

    public function test_multiple_payroll_status_histories_are_all_imported_with_correct_mapping(): void
    {
        $payload = $this->makeMinimalPayrollPayload([
            'payroll_status_histories' => [
                [
                    'id' => 1, 'payroll_id' => 1,
                    'from_status' => PayrollStatus::Draft->value,
                    'to_status' => PayrollStatus::PendingManagerApproval->value,
                    'changed_by' => null, 'changed_at' => '2025-01-10 09:00:00', 'note' => 'First transition',
                ],
                [
                    'id' => 2, 'payroll_id' => 1,
                    'from_status' => PayrollStatus::PendingManagerApproval->value,
                    'to_status' => PayrollStatus::Approved->value,
                    'changed_by' => null, 'changed_at' => '2025-01-12 14:00:00', 'note' => 'Approved',
                ],
            ],
        ]);

        $newCompany = FiscalYearService::importData($payload, ['name' => 'Multi PSH Co', 'fiscal_year' => 1404]);

        $newPayroll = Payroll::withoutGlobalScopes()->where('company_id', $newCompany->id)->first();
        $this->assertNotNull($newPayroll);

        $histories = PayrollStatusHistory::where('payroll_id', $newPayroll->id)
            ->orderBy('changed_at')
            ->get();

        $this->assertCount(2, $histories);
        $this->assertSame(PayrollStatus::Draft->value, $histories[0]->from_status->value);
        $this->assertSame(PayrollStatus::PendingManagerApproval->value, $histories[0]->to_status->value);
        $this->assertSame('First transition', $histories[0]->note);
        $this->assertSame(PayrollStatus::PendingManagerApproval->value, $histories[1]->from_status->value);
        $this->assertSame(PayrollStatus::Approved->value, $histories[1]->to_status->value);
        $this->assertSame('Approved', $histories[1]->note);
    }

    private function makeMinimalEmployeeData(int $id, int $workSiteId, int $workShiftId, ?int $orgUnitId, string $code = 'EMP-001'): array
    {
        return [
            'id' => $id, 'code' => $code, 'first_name' => 'Ali', 'last_name' => 'Ahmadi',
            'father_name' => null, 'national_code' => null, 'passport_number' => null,
            'nationality' => 'iranian', 'gender' => 'male', 'marital_status' => null,
            'children_count' => 0, 'birth_date' => null, 'birth_place' => null,
            'duty_status' => null, 'phone' => null, 'address' => null,
            'insurance_number' => null, 'insurance_type' => null,
            'bank_name' => null, 'bank_account' => null, 'card_number' => null, 'shaba_number' => null,
            'education_level' => null, 'field_of_study' => null, 'employment_type' => null,
            'contract_start_date' => null, 'contract_end_date' => null,
            'org_chart_id' => null, 'organization_unit_id' => $orgUnitId,
            'work_site_id' => $workSiteId, 'work_shift_id' => $workShiftId, 'contract_framework_id' => null,
            'user_id' => null, 'is_active' => true, 'company_id' => 0,
            'device_id' => null, 'leave_remain' => 1200,
        ];
    }

    private function makeMinimalPayrollPayload(array $extra = []): array
    {
        return array_merge([
            'org_charts' => [],
            'work_sites' => [
                ['id' => 1, 'name' => 'HQ', 'code' => 'HQ-01', 'company_id' => 0, 'address' => null, 'phone' => null, 'is_active' => true],
            ],
            'work_site_contracts' => [],
            'work_shifts' => [
                ['id' => 1, 'name' => 'Day', 'company_id' => 0, 'start_time' => '08:00', 'end_time' => '17:00', 'float' => 0, 'break' => 0, 'paid_leave' => 1200, 'is_active' => true],
            ],
            'organization_units' => [],
            'employees' => [
                $this->makeMinimalEmployeeData(1, 1, 1, null),
            ],
            'salary_decrees' => [
                ['id' => 1, 'employee_id' => 1, 'company_id' => 0, 'name' => 'Decree 2025',
                    'start_date' => '2025-01-01', 'end_date' => null, 'daily_wage' => '1000000.00', 'description' => null, 'is_active' => true],
            ],
            'monthly_attendances' => [
                ['id' => 1, 'employee_id' => 1, 'company_id' => 0, 'year' => 1404, 'month' => 1,
                    'start_date' => '2025-03-21', 'duration' => 26, 'work_days' => 26, 'present_days' => 26,
                    'absent_days' => 0, 'overtime' => 0, 'auto_overtime' => 0, 'undertime' => 0,
                    'mission' => 0, 'paid_leave' => 0, 'unpaid_leave' => 0, 'remote_work' => 0, 'friday' => 0, 'holiday' => 0],
            ],
            'payrolls' => [
                ['id' => 1, 'employee_id' => 1, 'company_id' => 0, 'decree_id' => 1, 'monthly_attendance_id' => 1,
                    'year' => 1404, 'month' => 1, 'total_earnings' => '10000000.00', 'total_deductions' => '1000000.00',
                    'net_payment' => '9000000.00', 'employer_insurance' => '2000000.00',
                    'tax_base_amount' => '9000000.00', 'income_tax_amount' => '500000.00',
                    'status' => PayrollStatus::Draft->value, 'issue_date' => '2025-04-01 00:00:00',
                    'accounting_voucher_id' => null, 'description' => null],
            ],
            'payroll_elements' => [],
            'payroll_items' => [],
            'decree_benefits' => [],
            'attendance_logs' => [],
            'personnel_requests' => [],
            'payroll_status_histories' => [],
        ], $extra);
    }
}
