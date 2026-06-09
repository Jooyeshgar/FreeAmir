<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ServiceImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ServiceGroup $serviceGroup;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'services.index']),
            Permission::firstOrCreate(['name' => 'services.export']),
            Permission::firstOrCreate(['name' => 'services.import']),
            Permission::firstOrCreate(['name' => 'services.import.store']),
        ]);

        $this->withCookies(['active-company-id' => $this->companyId]);
        config(['active-company-id' => $this->companyId]);

        $this->serviceGroup = ServiceGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
    }

    private function upload(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('services.csv', $csv);
    }

    public function test_export_returns_csv_with_services(): void
    {
        $service = Service::factory()->withGroup($this->serviceGroup)->withSubject()->create(['company_id' => $this->companyId, 'name' => 'Consulting', 'code' => 6001]);
        $response = $this->actingAs($this->user)->get(route('services.export'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('code,name,group_name', $content);
        $this->assertStringContainsString('Consulting', $content);
        $this->assertStringContainsString((string) $service->code, $content);
    }

    public function test_import_creates_new_group_and_service_with_auto_code(): void
    {
        $csv = "name,group_name,selling_price\n"."New Consulting,Brand New Group,2000\n";
        $response = $this->actingAs($this->user)->post(route('services.import.store'), ['file' => $this->upload($csv)]);
        $response->assertRedirect(route('services.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('service_groups', ['name' => 'Brand New Group', 'company_id' => $this->companyId]);
        $service = Service::where('name', 'New Consulting')->first();
        $this->assertNotNull($service);
        $this->assertNotNull($service->code);
        $this->assertEquals(2000, $service->selling_price);
    }

    public function test_import_reuses_existing_group(): void
    {
        $csv = "name,group_name\n"."Reuse Consulting,{$this->serviceGroup->name}\n";
        $this->actingAs($this->user)->post(route('services.import.store'), ['file' => $this->upload($csv)])->assertSessionHas('success');
        $this->assertSame(1, ServiceGroup::withoutGlobalScopes()->where('company_id', $this->companyId)->where('name', $this->serviceGroup->name)->count());
        $service = Service::where('name', 'Reuse Consulting')->first();
        $this->assertSame($this->serviceGroup->id, $service->group);
    }

    public function test_import_updates_existing_service_when_code_matches(): void
    {
        $existing = Service::factory()->withGroup($this->serviceGroup)->withSubject()->create(['company_id' => $this->companyId, 'name' => 'Old Name', 'code' => 8888, 'selling_price' => 100]);
        $csv = "code,name,group_name,selling_price\n"."8888,Updated Name,{$this->serviceGroup->name},300\n";
        $response = $this->actingAs($this->user)->post(route('services.import.store'), ['file' => $this->upload($csv)]);
        $response->assertRedirect(route('services.index'));
        $response->assertSessionHas('success');
        $this->assertSame(1, Service::where('code', 8888)->count());
        $existing->refresh();
        $this->assertSame('Updated Name', $existing->name);
        $this->assertEquals(300, $existing->selling_price);
    }

    public function test_import_requires_group_name_and_rolls_back(): void
    {
        $csv = "name,group_name\n"."Good Service,{$this->serviceGroup->name}\n"."Bad Service,\n";
        $response = $this->actingAs($this->user)->post(route('services.import.store'), ['file' => $this->upload($csv)]);
        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('services', ['name' => 'Good Service']);
        $this->assertDatabaseMissing('services', ['name' => 'Bad Service']);
    }
}
