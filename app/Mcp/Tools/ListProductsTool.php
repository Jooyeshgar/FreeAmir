<?php

namespace App\Mcp\Tools;

use App\Models\FiscalYear;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class ListProductsTool extends AccountingTool
{
    protected string $name = 'list_products';

    protected string $description = 'Search and list products in the selected authorized fiscal year by name, code, ID, or product group. Monetary values use the selected fiscal year company currency.';

    protected array $permissionGroups = [['products.index'], ['products.show']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->description('Authorized fiscal year ID to query products from.'),
            'year' => $schema->integer()->min(1300)->max(1500)->description('Optional four-digit Jalali year.'),
            'query' => $schema->string()->max(255)->description('Optional search text matching product name, product code, external SKU, or exact numeric ID.'),
            'external_sku' => $schema->string()->max(100)->description('Optional exact external SKU filter.'),
            'group_id' => $schema->integer()->min(1)->description('Optional product group ID filter.'),
            'page' => $schema->integer()->min(1)->description('Page number (default: 1).'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Maximum number of products per page (default: 15, max: 50).'),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['sometimes', 'integer', 'min:1'],
            'year' => ['sometimes', 'integer', 'min:1300', 'max:1500'],
            'query' => ['sometimes', 'nullable', 'string', 'max:255'],
            'external_sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'group_id' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        $currency = (string) ($fiscalYear->company?->currency ?: 'Rial');
        $search = isset($arguments['query']) ? trim((string) $arguments['query']) : '';
        $limit = isset($arguments['limit']) ? max(1, min(50, (int) $arguments['limit'])) : 15;
        $page = isset($arguments['page']) ? max(1, (int) $arguments['page']) : 1;

        $query = Product::query()
            ->with('productGroup:id,name')
            ->where('fiscal_year_id', $fiscalYear->id)
            ->orderBy('code')
            ->orderBy('id');

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $query->where(function ($q) use ($search, $escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                    ->orWhere('code', 'like', "%{$escaped}%")
                    ->orWhere('external_sku', 'like', "%{$escaped}%");

                if (ctype_digit($search) && (int) $search > 0) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        if (isset($arguments['external_sku']) && trim((string) $arguments['external_sku']) !== '') {
            $query->where('external_sku', trim((string) $arguments['external_sku']));
        }

        if (isset($arguments['group_id'])) {
            $query->where('group', (int) $arguments['group_id']);
        }

        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        $products = collect($paginator->items())
            ->map(fn (Product $product): array => self::formatSummary($product))
            ->all();

        $humanSummary = $paginator->total() === 0
            ? __('No products found in fiscal year :year.', ['year' => $fiscalYear->year])
            : __('Found :total matching products (showing page :page of :last_page).', [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ]);

        return $this->success([
            'fiscal_year_id' => (int) $fiscalYear->id,
            'currency' => $currency,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'products' => $products,
            'human_summary' => $humanSummary,
        ]);
    }

    public static function formatSummary(Product $product): array
    {
        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'code' => $product->code !== null ? (string) $product->code : null,
            'external_sku' => $product->external_sku,
            'group_id' => $product->group !== null ? (int) $product->group : null,
            'group_name' => $product->productGroup?->name,
            'selling_price' => (float) ($product->selling_price ?? 0),
            'vat' => (float) ($product->vat ?? 0),
            'quantity' => (float) ($product->quantity ?? 0),
            'quantity_warning' => (float) ($product->quantity_warning ?? 0),
            'oversell' => (bool) $product->oversell,
            'location' => $product->location,
            'description' => $product->description,
        ];
    }
}
