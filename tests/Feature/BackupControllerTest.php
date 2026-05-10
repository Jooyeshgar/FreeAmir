<?php

namespace Tests\Feature;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
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

        $this->assertStringEndsWith('.json', $jsonEntryName);
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
}
