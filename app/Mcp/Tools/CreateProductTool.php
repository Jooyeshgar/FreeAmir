<?php

namespace App\Mcp\Tools;

use App\Http\Requests\StoreProductRequest;
use App\Models\FiscalYear;
use App\Models\ProductGroup;
use App\Services\ProductService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;

class CreateProductTool extends AccountingTool
{
    protected string $name = 'create_product';

    protected string $description = 'Create a product in the selected authorized open fiscal year. Prices use its company currency; initial stock is zero.';

    protected array $permissionGroups = [['products.store']];

    protected bool $readOnlyHint = false;

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->required(),
            'name' => $schema->string()->min(1)->max(20)->required(),
            'group_id' => $schema->integer()->min(1)->description('Optional product group ID from get_current_context (select the matching group for the product category/brand, e.g. FNIRSI; defaults to the first product group in the selected fiscal year).'),
            'code' => $schema->string()->max(20)->description('Optional numeric product code (digits only). Omit to auto-generate the next numeric code.'),
            'external_sku' => $schema->string()->max(100)->description('Optional external SKU/code from another system (e.g. "FNIRSI-LC1020E"), searchable via list_products and get_product.'),
            'quantity_warning' => $schema->number()->min(0),
            'selling_price' => $schema->number()->min(0)->description('Selling price in the selected fiscal year company currency.'),
            'vat' => $schema->number()->min(0)->max(100)->description('VAT percentage from 0 to 100.'),
            'oversell' => $schema->boolean()->description('Allow sales beyond available stock. Defaults to false.'),
            'location' => $schema->string()->max(50),
            'description' => $schema->string()->max(150),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:20'],
            'group_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'code' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/\A[0-9]+\z/'],
            'external_sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'quantity_warning' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'selling_price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'vat' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'oversell' => ['sometimes', 'nullable', 'boolean'],
            'location' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        $yearId = (int) $arguments['fiscal_year_id'];
        $groupId = $arguments['group_id'] ?? ProductGroup::where('fiscal_year_id', $yearId)->orderBy('id')->value('id');
        if (! $groupId) {
            throw ValidationException::withMessages(['group_id' => __('No product group found for this fiscal year.')]);
        }

        $group = ProductGroup::query()->find($groupId);
        if (! $group || (int) $group->fiscal_year_id !== $yearId) {
            throw ValidationException::withMessages(['group_id' => __('The selected product group is invalid.')]);
        }

        $request = $this->validateFormRequest(StoreProductRequest::class, [
            'name' => $arguments['name'],
            'group' => (int) $groupId,
            'code' => $arguments['code'] ?? null,
            'external_sku' => isset($arguments['external_sku']) && trim((string) $arguments['external_sku']) !== '' ? trim((string) $arguments['external_sku']) : null,
            'quantity' => '0',
            'quantity_warning' => (string) ($arguments['quantity_warning'] ?? 0),
            'selling_price' => (string) ($arguments['selling_price'] ?? 0),
            'vat' => number_format((float) ($arguments['vat'] ?? 0), 2, '.', ''),
            'oversell' => (bool) ($arguments['oversell'] ?? false),
            'location' => $arguments['location'] ?? null,
            'description' => $arguments['description'] ?? null,
        ]);

        $product = app(ProductService::class)->create($request->getValidatedData());

        return $this->success([
            'id' => $product->id,
            'fiscal_year_id' => $product->fiscal_year_id,
            'name' => $product->name,
            'code' => $product->code,
            'external_sku' => $product->external_sku,
            'group_id' => $product->group,
            'quantity' => (float) $product->quantity,
            'selling_price' => (float) $product->selling_price,
            'currency' => (string) ($fiscalYear->company?->currency ?: 'Rial'),
            'message' => __('Product created successfully.'),
        ]);
    }
}
