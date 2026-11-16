<?php

namespace App\Mcp\Tools;

use App\Http\Requests\StoreProductRequest;
use App\Models\ProductGroup;
use App\Services\ProductService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;

class CreateProductTool extends AccountingTool
{
    protected string $name = 'create_product';

    protected string $description = 'ایجاد کالای جدید با قیمت ریالی و گروه کالایی؛ موجودی اولیه صفر است.';

    protected array $permissionGroups = [['products.store']];

    protected bool $readOnlyHint = false;

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->required(),
            'name' => $schema->string()->min(1)->max(20)->required(),
            'group_id' => $schema->integer()->min(1)->description('شناسه گروه کالا. اختیاری؛ در صورت عدم ارسال از اولین گروه کالای فعال سال مالی استفاده می‌شود.'),
            'code' => $schema->string()->max(50),
            'quantity_warning' => $schema->number()->min(0),
            'selling_price' => $schema->number()->min(0),
            'vat' => $schema->number()->min(0)->max(100),
            'oversell' => $schema->boolean()->description('امکان فروش بیش از موجودی (پیش‌فرض: false).'),
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
            'code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'quantity_warning' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'selling_price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'vat' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'oversell' => ['sometimes', 'nullable', 'boolean'],
            'location' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }

    protected function report(array $arguments): Response
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
            'quantity' => '0',
            'quantity_warning' => (string) ($arguments['quantity_warning'] ?? 0),
            'selling_price' => (string) ($arguments['selling_price'] ?? 0),
            'vat' => (float) ($arguments['vat'] ?? 0),
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
            'group_id' => $product->group,
            'quantity' => (float) $product->quantity,
            'selling_price' => (float) $product->selling_price,
            'message' => __('Product created successfully.'),
        ]);
    }
}
