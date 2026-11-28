<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use App\Models\FiscalYear;
use App\Services\CustomerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetCustomerTool extends AccountingTool
{
    protected string $name = 'get_customer';

    protected string $description = 'Retrieve full profile details and ledger balance for a single customer by customer ID within the selected authorized fiscal year. Monetary values use the selected fiscal year company currency.';

    protected array $permissionGroups = [['customers.show']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->description('Authorized fiscal year ID to inspect the customer in.'),
            'year' => $schema->integer()->min(1300)->max(1500)->description('Optional four-digit Jalali year.'),
            'customer_id' => $schema->integer()->min(1)->description('Customer ID.')->required(),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['sometimes', 'integer', 'min:1'],
            'year' => ['sometimes', 'integer', 'min:1300', 'max:1500'],
            'customer_id' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        $currency = (string) ($fiscalYear->company?->currency ?: 'Rial');

        $customer = Customer::query()
            ->with(['group:id,name', 'subject', 'introducer:id,name'])
            ->where('fiscal_year_id', $fiscalYear->id)
            ->find((int) $arguments['customer_id']);

        if (! $customer) {
            return $this->error(__('Customer not found in the selected fiscal year.'));
        }

        $detail = CustomerService::formatDetail($customer, $currency);

        return $this->success([
            'fiscal_year_id' => (int) $fiscalYear->id,
            'currency' => $currency,
            'customer' => $detail,
            'human_summary' => __('Account party :name (ID :id) has a balance of :balance.', [
                'name' => $customer->name,
                'id' => $customer->id,
                'balance' => $detail['formatted_balance'],
            ]),
        ]);
    }
}
