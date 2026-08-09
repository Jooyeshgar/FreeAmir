<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use App\Models\Transaction;
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

        app()->setLocale('en');

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

    private function parseCsv(string $content): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $content));
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    public function test_service_export_rejects_columns_outside_the_allowlist(): void
    {
        $this->actingAs($this->user)->get(route('services.export', [
            'cols_submitted' => 1,
            'columns' => ['not_an_export_column'],
        ]))->assertSessionHasErrors('columns.0');
    }

    public function test_export_returns_csv_with_services(): void
    {
        $service = Service::factory()->withGroup($this->serviceGroup)->withSubject()->create(['company_id' => $this->companyId, 'name' => 'Consulting', 'code' => 6001]);
        $response = $this->actingAs($this->user)->get(route('services.export'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        [$headers, $values] = $this->parseCsv($response->streamedContent());
        $row = array_combine($headers, $values);

        $this->assertSame(['Service Code', 'Name', 'Sell price', 'VAT (%)', 'Service group'], array_slice($headers, 0, 5));
        $this->assertSame('Consulting', $row['Name']);
        $this->assertSame((string) $service->code, $row['Service Code']);
    }

    public function test_export_includes_all_subject_codes(): void
    {
        $service = Service::factory()->withGroup($this->serviceGroup)->withSubject()->create(['company_id' => $this->companyId, 'name' => 'Consulting', 'code' => 6002]);
        $service->loadMissing('subject', 'cogsSubject', 'salesReturnsSubject');

        $response = $this->actingAs($this->user)->get(route('services.export'));
        $response->assertStatus(200);
        [$headers, $values] = $this->parseCsv($response->streamedContent());
        $row = array_combine($headers, $values);

        $this->assertSame($service->subject->code, $row['Revenue subject code']);
        $this->assertSame($service->cogsSubject->code, $row['COGS subject code']);
        $this->assertSame($service->salesReturnsSubject->code, $row['Sales returns subject code']);
    }

    public function test_export_only_includes_selected_optional_columns(): void
    {
        Service::factory()->withGroup($this->serviceGroup)->withSubject()->create([
            'company_id' => $this->companyId,
            'name' => 'Selected Service',
            'code' => 6003,
        ]);

        [$headers] = $this->parseCsv($this->actingAs($this->user)->get(route('services.export', [
            'cols_submitted' => 1,
            'columns' => ['code', 'selling_price'],
        ]))->streamedContent());

        $this->assertSame(['Service Code', 'Name', 'Sell price'], $headers);
    }

    public function test_export_includes_service_subject_amounts(): void
    {
        $service = Service::factory()->withGroup($this->serviceGroup)->withSubject()->create([
            'company_id' => $this->companyId,
            'name' => 'Accounted Service',
            'code' => 6004,
        ]);

        Transaction::factory()->create(['subject_id' => $service->subject_id, 'value' => 1250]);
        Transaction::factory()->create(['subject_id' => $service->cogs_subject_id, 'value' => -400]);
        Transaction::factory()->create(['subject_id' => $service->sales_returns_subject_id, 'value' => -90]);

        [$headers, $row] = $this->parseCsv($this->actingAs($this->user)->get(route('services.export', [
            'cols_submitted' => 1,
            'columns' => ['revenue_account', 'cogs_account', 'sales_return_account'],
        ]))->streamedContent());

        $this->assertSame(['Name', 'Revenue account amount', 'COGS account amount', 'Sales return account amount'], $headers);
        $this->assertSame(['Accounted Service', '1250', '400', '90'], $row);
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

    public function test_import_creates_service_subjects_under_the_group_accounts(): void
    {
        $csv = "name,group_name\nImported Service,{$this->serviceGroup->name}\n";

        $this->actingAs($this->user)->post(route('services.import.store'), ['file' => $this->upload($csv)])->assertSessionHas('success');

        $service = Service::with('subject', 'cogsSubject', 'salesReturnsSubject')->where('name', 'Imported Service')->firstOrFail();
        $this->serviceGroup->loadMissing('subject', 'cogsSubject', 'salesReturnsSubject');

        $relations = [
            [$service->subject, $this->serviceGroup->subject],
            [$service->cogsSubject, $this->serviceGroup->cogsSubject],
            [$service->salesReturnsSubject, $this->serviceGroup->salesReturnsSubject],
        ];

        foreach ($relations as [$subject, $parent]) {
            $this->assertNotNull($subject);
            $this->assertSame($parent->id, $subject->parent_id);
            $this->assertSame($service->name, $subject->name);
            $this->assertSame($service->company_id, $subject->company_id);
            $this->assertSame($service->id, $subject->subjectable_id);
            $this->assertSame($service->getMorphClass(), $subject->subjectable_type);
        }

        $this->assertSame(3, Subject::whereMorphedTo('subjectable', $service)->count());
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

    public function test_import_accepts_translated_headers_and_restores_subject_codes(): void
    {
        $this->serviceGroup->loadMissing('subject', 'cogsSubject', 'salesReturnsSubject');

        $codes = [
            'income' => $this->serviceGroup->subject->code.'701',
            'cogs' => $this->serviceGroup->cogsSubject->code.'702',
            'returns' => $this->serviceGroup->salesReturnsSubject->code.'703',
        ];

        $csv = "Service Code,Name,Sell price,VAT,Service group,Revenue subject code,COGS subject code,Sales returns subject code\n".
            "9020,Translated Service,4100,10,{$this->serviceGroup->name},{$codes['income']},{$codes['cogs']},{$codes['returns']}\n";

        $this->actingAs($this->user)->post(route('services.import.store'), ['file' => $this->upload($csv)])->assertSessionHas('success');

        $service = Service::with('subject', 'cogsSubject', 'salesReturnsSubject')->where('code', 9020)->firstOrFail();

        $this->assertSame($codes['income'], $service->subject->code);
        $this->assertSame($codes['cogs'], $service->cogsSubject->code);
        $this->assertSame($codes['returns'], $service->salesReturnsSubject->code);
        $this->assertSame(3, Subject::whereIn('id', [
            $service->subject_id,
            $service->cogs_subject_id,
            $service->sales_returns_subject_id,
        ])->where('subjectable_id', $service->id)->count());
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
