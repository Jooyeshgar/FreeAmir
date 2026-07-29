<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ProductGroup $productGroup;

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
            Permission::firstOrCreate(['name' => 'products.index']),
            Permission::firstOrCreate(['name' => 'products.export']),
            Permission::firstOrCreate(['name' => 'products.import']),
            Permission::firstOrCreate(['name' => 'products.import.store']),
        ]);

        $this->withCookies(['active-company-id' => $this->companyId]);
        config(['active-company-id' => $this->companyId]);

        $this->productGroup = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);
    }

    private function upload(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('products.csv', $csv);
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

    public function test_export_returns_csv_with_products(): void
    {
        $product = Product::factory()->withGroup($this->productGroup)->create(['company_id' => $this->companyId, 'name' => 'Widget', 'code' => '5001']);
        $response = $this->actingAs($this->user)->get(route('products.export'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        [$headers, $values] = $this->parseCsv($response->streamedContent());
        $row = array_combine($headers, $values);

        $this->assertSame(['Product name', 'Inbound', 'Outbound', 'Stock', 'Category', 'Product code'], array_slice($headers, 0, 6));
        $this->assertSame('Widget', $row['Product name']);
        $this->assertSame((string) $product->code, $row['Product code']);
    }

    public function test_export_includes_all_requested_report_columns(): void
    {
        Product::factory()->withGroup($this->productGroup)->withSubjects()->create([
            'company_id' => $this->companyId,
            'name' => 'Widget',
            'code' => '5003',
        ]);

        [$headers] = $this->parseCsv(
            $this->actingAs($this->user)->get(route('products.export'))->streamedContent()
        );

        $this->assertSame([
            'Product name',
            'Inbound',
            'Outbound',
            'Stock',
            'Category',
            'Product code',
            'Sale price',
            'Cost of goods',
            'Last item cost',
            'Sales profit',
            'Revenue account amount',
            'COGS account amount',
            'Inventory account amount',
            'Sales return account amount',
        ], array_slice($headers, 0, 14));
    }

    public function test_export_only_includes_selected_optional_columns(): void
    {
        Product::factory()->withGroup($this->productGroup)->withSubjects()->create([
            'company_id' => $this->companyId,
            'name' => 'Selected Widget',
            'code' => '5004',
        ]);

        [$headers] = $this->parseCsv($this->actingAs($this->user)->get(route('products.export', [
            'cols_submitted' => 1,
            'columns' => ['stock', 'code', 'income_subject_code'],
        ]))->streamedContent());

        $this->assertSame(['Product name', 'Stock', 'Product code', 'Revenue subject code'], $headers);
    }

    public function test_export_includes_all_subject_codes(): void
    {
        $product = Product::factory()->withGroup($this->productGroup)->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Widget', 'code' => '5002']);
        $product->loadMissing('incomeSubject', 'cogsSubject', 'inventorySubject', 'salesReturnsSubject');

        $response = $this->actingAs($this->user)->get(route('products.export'));
        $response->assertStatus(200);
        [$headers, $values] = $this->parseCsv($response->streamedContent());
        $row = array_combine($headers, $values);

        $this->assertSame($product->incomeSubject->code, $row['Revenue subject code']);
        $this->assertSame($product->cogsSubject->code, $row['COGS subject code']);
        $this->assertSame($product->inventorySubject->code, $row['Inventory subject code']);
        $this->assertSame($product->salesReturnsSubject->code, $row['Sales returns subject code']);
    }

    public function test_import_creates_new_group_and_product_with_auto_code(): void
    {
        $csv = "name,group_name,selling_price\n"."Newest Widget,Brand New Group,1500\n";
        $response = $this->actingAs($this->user)->post(route('products.import.store'), ['file' => $this->upload($csv)]);
        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('product_groups', ['name' => 'Brand New Group', 'company_id' => $this->companyId]);
        $product = Product::where('name', 'Newest Widget')->first();
        $this->assertNotNull($product);
        $this->assertNotNull($product->code);
        $this->assertEquals(1500, $product->selling_price);
    }

    public function test_import_creates_product_subjects_under_the_group_accounts(): void
    {
        $csv = "name,group_name\nImported Product,{$this->productGroup->name}\n";

        $this->actingAs($this->user)->post(route('products.import.store'), ['file' => $this->upload($csv)])->assertSessionHas('success');

        $product = Product::with('incomeSubject', 'cogsSubject', 'inventorySubject', 'salesReturnsSubject')->where('name', 'Imported Product')->firstOrFail();
        $this->productGroup->loadMissing('incomeSubject', 'cogsSubject', 'inventorySubject', 'salesReturnsSubject');

        $relations = [
            [$product->incomeSubject, $this->productGroup->incomeSubject],
            [$product->cogsSubject, $this->productGroup->cogsSubject],
            [$product->inventorySubject, $this->productGroup->inventorySubject],
            [$product->salesReturnsSubject, $this->productGroup->salesReturnsSubject],
        ];

        foreach ($relations as [$subject, $parent]) {
            $this->assertNotNull($subject);
            $this->assertSame($parent->id, $subject->parent_id);
            $this->assertSame($product->name, $subject->name);
            $this->assertSame($product->company_id, $subject->company_id);
            $this->assertSame($product->id, $subject->subjectable_id);
            $this->assertSame($product->getMorphClass(), $subject->subjectable_type);
        }

        $this->assertSame(4, Subject::whereMorphedTo('subjectable', $product)->count());
    }

    public function test_import_reuses_existing_group(): void
    {
        $csv = "name,group_name\n"."Reuse Widget,{$this->productGroup->name}\n";
        $this->actingAs($this->user)->post(route('products.import.store'), ['file' => $this->upload($csv)])->assertSessionHas('success');
        $this->assertSame(1, ProductGroup::withoutGlobalScopes()->where('company_id', $this->companyId)->where('name', $this->productGroup->name)->count());
        $product = Product::where('name', 'Reuse Widget')->first();
        $this->assertSame($this->productGroup->id, $product->group);
    }

    public function test_import_updates_existing_product_when_code_matches(): void
    {
        $existing = Product::factory()->withGroup($this->productGroup)->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Old Name', 'code' => '7777', 'selling_price' => 100]);
        $csv = "code,name,group_name,selling_price\n"."7777,Updated Name,{$this->productGroup->name},250\n";
        $response = $this->actingAs($this->user)->post(route('products.import.store'), ['file' => $this->upload($csv)]);
        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success');
        $this->assertSame(1, Product::where('code', '7777')->count());
        $existing->refresh();
        $this->assertSame('Updated Name', $existing->name);
        $this->assertEquals(250, $existing->selling_price);
    }

    public function test_import_accepts_translated_headers_and_restores_subject_codes(): void
    {
        $this->productGroup->loadMissing('incomeSubject', 'cogsSubject', 'inventorySubject', 'salesReturnsSubject');

        $codes = [
            'income' => $this->productGroup->incomeSubject->code.'701',
            'cogs' => $this->productGroup->cogsSubject->code.'702',
            'inventory' => $this->productGroup->inventorySubject->code.'703',
            'returns' => $this->productGroup->salesReturnsSubject->code.'704',
        ];

        $csv = "Product code,Product name,Category,Revenue subject code,COGS subject code,Inventory subject code,Sales returns subject code,Sale price\n".
            "9010,Translated Widget,{$this->productGroup->name},{$codes['income']},{$codes['cogs']},{$codes['inventory']},{$codes['returns']},3200\n";

        $this->actingAs($this->user)->post(route('products.import.store'), ['file' => $this->upload($csv)])->assertSessionHas('success');

        $product = Product::with('incomeSubject', 'cogsSubject', 'inventorySubject', 'salesReturnsSubject')->where('code', '9010')->firstOrFail();

        $this->assertSame($codes['income'], $product->incomeSubject->code);
        $this->assertSame($codes['cogs'], $product->cogsSubject->code);
        $this->assertSame($codes['inventory'], $product->inventorySubject->code);
        $this->assertSame($codes['returns'], $product->salesReturnsSubject->code);
        $this->assertSame(4, Subject::whereIn('id', [
            $product->income_subject_id,
            $product->cogs_subject_id,
            $product->inventory_subject_id,
            $product->sales_returns_subject_id,
        ])->where('subjectable_id', $product->id)->count());
    }

    public function test_import_requires_group_name_and_rolls_back(): void
    {
        $csv = "name,group_name\n"."Good Product,{$this->productGroup->name}\n"."Bad Product,\n";
        $response = $this->actingAs($this->user)->post(route('products.import.store'), ['file' => $this->upload($csv)]);
        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('products', ['name' => 'Good Product']);
        $this->assertDatabaseMissing('products', ['name' => 'Bad Product']);
    }
}
