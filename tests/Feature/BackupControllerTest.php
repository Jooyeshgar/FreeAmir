<?php

namespace Tests\Feature;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_export_downloads_zip_with_json_backup_contents(): void
    {
        $response = $this->post(route('backups.export'), [
            'source_id' => $this->company->id,
            'tables_to_backup' => [FiscalYearSection::SUBJECTS->value],
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/zip');

        $zipFile = $response->baseResponse->getFile();
        $this->assertNotNull($zipFile);

        $zip = new ZipArchive;
        $opened = $zip->open($zipFile->getPathname());

        $this->assertTrue($opened === true);
        $this->assertSame(1, $zip->numFiles);

        $jsonEntryName = $zip->getNameIndex(0);
        $jsonContent = $zip->getFromIndex(0);
        $zip->close();

        $this->assertNotFalse($jsonEntryName);
        $this->assertStringEndsWith('.json', $jsonEntryName);
        $this->assertNotFalse($jsonContent);

        $decoded = json_decode($jsonContent, true);

        $this->assertIsArray($decoded);
        $this->assertSame($this->company->id, $decoded['meta']['source_company_id']);
        $this->assertSame($this->company->name, $decoded['meta']['source_company_name']);
        $this->assertSame([FiscalYearSection::SUBJECTS->value], $decoded['meta']['sections_exported']);
        $this->assertArrayHasKey(FiscalYearSection::SUBJECTS->value, $decoded);
    }

    public function test_import_uploads_json_and_creates_new_company(): void
    {
        $payload = [
            'meta' => [
                'source_company_id' => $this->company->id,
                'source_company_name' => $this->company->name,
            ],
        ];

        $file = UploadedFile::fake()->createWithContent(
            'backup.json',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $response = $this->post(route('backups.import'), [
            'file' => $file,
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

    public function test_import_rejects_invalid_json_upload(): void
    {
        $existingCompanies = Company::count();

        $file = UploadedFile::fake()->createWithContent(
            'backup.json',
            '{"meta": invalid json}'
        );

        $response = $this->post(route('backups.import'), [
            'file' => $file,
            'fiscal_year' => 1411,
            'company_name' => 'Broken Import',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame($existingCompanies, Company::count());
    }
}
