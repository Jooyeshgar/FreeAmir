<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductGroup;
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

    public function test_export_returns_csv_with_products(): void
    {
        $product = Product::factory()->withGroup($this->productGroup)->create(['company_id' => $this->companyId, 'name' => 'Widget', 'code' => '5001']);
        $response = $this->actingAs($this->user)->get(route('products.export'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('code,name,group_name', $content);
        $this->assertStringContainsString('Widget', $content);
        $this->assertStringContainsString((string) $product->code, $content);
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

    public function test_import_requires_group_name_and_rolls_back(): void
    {
        $csv = "name,group_name\n"."Good Product,{$this->productGroup->name}\n"."Bad Product,\n";
        $response = $this->actingAs($this->user)->post(route('products.import.store'), ['file' => $this->upload($csv)]);
        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('products', ['name' => 'Good Product']);
        $this->assertDatabaseMissing('products', ['name' => 'Bad Product']);
    }
}
