<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use App\Models\FiscalYear;
use App\Services\SubjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetCustomerTool extends AccountingTool
{
    protected string $name = 'get_customer';

    protected string $description = 'Retrieve full profile details and ledger balance for a single customer by customer ID within the selected authorized fiscal year. Monetary values use the selected fiscal year company currency.';

    protected array $permissionGroups = [['customers.show'], ['customers.index']];

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

        $balance = $customer->subject ? (int) SubjectService::sumSubject($customer->subject) : 0;
        [$status, $statusCode] = match (true) {
            $balance < 0 => [__('Debtor'), 'debtor'],
            $balance > 0 => [__('Creditor'), 'creditor'],
            default => [__('Settled (zero)'), 'settled'],
        };

        $formattedBalance = number_format(abs($balance)).' '.$currency.' ('.$status.')';
        $creditLimit = (int) ($customer->credit ?? 0);

        return $this->success([
            'fiscal_year_id' => (int) $fiscalYear->id,
            'currency' => $currency,
            'customer' => array_merge(ListCustomersTool::formatSummary($customer, $currency), [
                'fax' => $customer->fax ?: null,
                'address' => $customer->address ?: null,
                'postal_code' => $customer->postal_code ?: null,
                'email' => $customer->email ?: null,
                'web_page' => $customer->web_page ?: null,
                'responsible' => $customer->responsible ?: null,
                'connector' => $customer->connector ?: null,
                'description' => $customer->desc ?: null,
                'introducer_id' => $customer->introducer_id !== null ? (int) $customer->introducer_id : null,
                'introducer_name' => $customer->introducer?->name,
                'subject_id' => $customer->subject?->id !== null ? (int) $customer->subject->id : null,
                'subject_code' => $customer->subject?->code,
                'balance' => $balance,
                'formatted_balance' => $formattedBalance,
                'balance_status' => $status,
                'balance_status_code' => $statusCode,
                'formatted_credit_limit' => number_format($creditLimit).' '.$currency,
            ]),
            'human_summary' => __('Account party :name (ID :id) has a balance of :balance.', [
                'name' => $customer->name,
                'id' => $customer->id,
                'balance' => $formattedBalance,
            ]),
        ]);
    }
}
