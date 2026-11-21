<?php

namespace App\Mcp\Tools;

use App\Models\FiscalYear;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetProductTool extends AccountingTool
{
    protected string $name = 'get_product';

    protected string $description = 'Retrieve full details for a single product by product ID or exact product code within the selected authorized fiscal year. Monetary values use the selected fiscal year company currency.';

    protected array $permissionGroups = [['products.show'], ['products.index']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->description('Authorized fiscal year ID to inspect the product in.'),
            'year' => $schema->integer()->min(1300)->max(1500)->description('Optional four-digit Jalali year.'),
            'product_id' => $schema->integer()->min(1)->description('Product ID (provide product_id or code).'),
            'code' => $schema->string()->max(50)->description('Exact product code (provide product_id or code).'),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['sometimes', 'integer', 'min:1'],
            'year' => ['sometimes', 'integer', 'min:1300', 'max:1500'],
            'product_id' => ['required_without:arguments.code', 'integer', 'min:1'],
            'code' => ['required_without:arguments.product_id', 'string', 'max:50'],
        ];
    }

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        $currency = (string) ($fiscalYear->company?->currency ?: 'Rial');

        $query = Product::query()
            ->with(['productGroup:id,name', 'warehouseStocks.warehouse:id,name'])
            ->where('fiscal_year_id', $fiscalYear->id);

        if (isset($arguments['product_id'])) {
            $query->where('id', (int) $arguments['product_id']);
        }

        if (isset($arguments['code'])) {
            $query->where('code', trim((string) $arguments['code']));
        }

        $product = $query->first();

        if (! $product) {
            return $this->error(__('Product not found in the selected fiscal year.'));
        }

        $warehouseStocks = $product->warehouseStocks->map(fn ($stock): array => [
            'warehouse_id' => (int) $stock->warehouse_id,
            'warehouse_name' => $stock->warehouse?->name,
            'quantity' => (float) ($stock->quantity ?? 0),
            'average_cost' => (float) ($stock->average_cost ?? 0),
        ])->values()->all();

        return $this->success([
            'fiscal_year_id' => (int) $fiscalYear->id,
            'currency' => $currency,
            'product' => array_merge(ListProductsTool::formatSummary($product, $currency), [
                'sstid' => $product->sstid,
                'average_cost' => (float) ($product->average_cost ?? 0),
                'weight' => $product->weight !== null ? (float) $product->weight : null,
                'warehouse_stocks' => $warehouseStocks,
            ]),
            'human_summary' => __('Product :name (ID :id, code :code): selling price :price :currency, quantity :quantity.', [
                'name' => $product->name,
                'id' => $product->id,
                'code' => $product->code ?: '-',
                'price' => number_format((float) ($product->selling_price ?? 0)),
                'currency' => $currency,
                'quantity' => (float) ($product->quantity ?? 0),
            ]),
        ]);
    }
}
