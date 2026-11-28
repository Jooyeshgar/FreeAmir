<?php

namespace App\Mcp\Tools;

use App\Models\FiscalYear;
use App\Models\Product;
use App\Services\FiscalYearMutationGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;

class UpdateProductTool extends AccountingTool
{
    protected string $name = 'update_product';

    protected string $description = 'Set, change, or clear the external SKU of an existing product in the selected authorized open fiscal year. Does not change prices, stock, or accounting data.';

    protected array $permissionGroups = [['products.edit']];

    protected bool $readOnlyHint = false;

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->description('Authorized open fiscal year ID.')->required(),
            'product_id' => $schema->integer()->min(1)->description('ID of the product to update.')->required(),
            'external_sku' => $schema->string()->max(100)->description('External SKU/code from another system. Send an empty string to clear it.')->required(),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['required', 'integer', 'min:1'],
            'product_id' => ['required', 'integer', 'min:1'],
            'external_sku' => ['present', 'nullable', 'string', 'max:100'],
        ];
    }

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($fiscalYear);

        $product = Product::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->find((int) $arguments['product_id']);

        if (! $product) {
            return $this->error(__('Product not found in the selected fiscal year.'));
        }

        $sku = trim((string) ($arguments['external_sku'] ?? ''));

        if ($sku !== '' && Product::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('external_sku', $sku)
            ->whereKeyNot($product->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'external_sku' => __('A product with this external SKU already exists in the selected fiscal year.'),
            ]);
        }

        $product->external_sku = $sku === '' ? null : $sku;
        $product->save();

        return $this->success([
            'id' => (int) $product->id,
            'fiscal_year_id' => (int) $product->fiscal_year_id,
            'code' => $product->code,
            'external_sku' => $product->external_sku,
            'message' => __('Product updated successfully.'),
        ]);
    }
}
