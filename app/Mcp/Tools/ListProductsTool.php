<?php

namespace App\Mcp\Tools;

use App\Models\FiscalYear;
use App\Models\Product;
use App\Services\ProductService;
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
            'query' => $schema->string()->max(255)->description('Optional search text matching product name, product code, or exact numeric ID.'),
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
            'group_id' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        $currency = (string) ($fiscalYear->company?->currency ?: 'Rial');
        $limit = isset($arguments['limit']) ? max(1, min(50, (int) $arguments['limit'])) : 15;
        $page = isset($arguments['page']) ? max(1, (int) $arguments['page']) : 1;

        $paginator = ProductService::searchQuery([
            'q' => $arguments['query'] ?? '',
            'group_id' => $arguments['group_id'] ?? null,
        ])
            ->where('fiscal_year_id', $fiscalYear->id)
            ->paginate($limit, ['*'], 'page', $page);

        $products = collect($paginator->items())
            ->map(fn (Product $product): array => ProductService::formatSummary($product))
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
}
