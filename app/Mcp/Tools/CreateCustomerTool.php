<?php

namespace App\Mcp\Tools;

use App\Enums\CustomerType;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\CustomerGroup;
use App\Services\CustomerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;

class CreateCustomerTool extends AccountingTool
{
    protected string $name = 'create_customer';

    protected string $description = 'Create a customer or account party in the selected authorized open fiscal year.';

    protected array $permissionGroups = [['customers.store']];

    protected bool $readOnlyHint = false;

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->required(),
            'name' => $schema->string()->min(1)->max(100)->required(),
            'group_id' => $schema->integer()->min(1)->description('Optional customer group ID. Defaults to the first customer group in the selected fiscal year.'),
            'type' => $schema->string()->enum(CustomerType::valueNames())->description('Optional customer type. Defaults to individual.'),
            'phone' => $schema->string()->max(15),
            'mobile' => $schema->string()->max(15),
            'email' => $schema->string()->max(64),
            'personal_code' => $schema->string()->max(15),
            'postal_code' => $schema->string()->max(15),
            'address' => $schema->string()->max(100),
            'description' => $schema->string()->max(150),
            'subject_code' => $schema->string()->max(20),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:100'],
            'group_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'type' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', CustomerType::valueNames())],
            'phone' => ['sometimes', 'nullable', 'string', 'max:15'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:15'],
            'email' => ['sometimes', 'nullable', 'email', 'max:64'],
            'personal_code' => ['sometimes', 'nullable', 'string', 'max:15'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:15'],
            'address' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:150'],
            'subject_code' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    protected function report(array $arguments): Response
    {
        $yearId = (int) $arguments['fiscal_year_id'];
        $groupId = $arguments['group_id'] ?? CustomerGroup::where('fiscal_year_id', $yearId)->orderBy('id')->value('id');
        if (! $groupId) {
            throw ValidationException::withMessages(['group_id' => __('No customer group found for this fiscal year.')]);
        }

        $group = CustomerGroup::query()->find($groupId);
        if (! $group || (int) $group->fiscal_year_id !== $yearId) {
            throw ValidationException::withMessages(['group_id' => __('The selected customer group is invalid.')]);
        }

        $request = $this->validateFormRequest(StoreCustomerRequest::class, [
            'name' => $arguments['name'],
            'group_id' => (int) $groupId,
            'type' => $arguments['type'] ?? 'individual',
            'phone' => $arguments['phone'] ?? null,
            'mobile' => $arguments['mobile'] ?? null,
            'email' => $arguments['email'] ?? null,
            'personal_code' => $arguments['personal_code'] ?? null,
            'postal_code' => $arguments['postal_code'] ?? null,
            'address' => $arguments['address'] ?? null,
            'desc' => $arguments['description'] ?? null,
            'subject_code' => $arguments['subject_code'] ?? null,
        ]);

        $customer = app(CustomerService::class)->create($request->validated());

        return $this->success([
            'id' => $customer->id,
            'fiscal_year_id' => $customer->fiscal_year_id,
            'name' => $customer->name,
            'group_id' => $customer->group_id,
            'type' => $customer->type?->valueName(),
            'phone' => $customer->phone,
            'mobile' => $customer->mobile,
            'email' => $customer->email,
            'subject_id' => $customer->subject_id,
            'message' => __('Customer created successfully.'),
        ]);
    }
}
