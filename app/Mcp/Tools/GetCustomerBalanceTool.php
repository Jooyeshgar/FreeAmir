<?php

namespace App\Mcp\Tools;

use App\Models\Customer;
use App\Models\FiscalYear;
use App\Services\SubjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetCustomerBalanceTool extends AccountingTool
{
    protected string $name = 'get_customer_balance';

    protected string $description = 'Search for a customer or account party and return contact details, ledger balance, debtor/creditor status, and credit limit. Monetary values use the selected fiscal year company currency.';

    protected array $permissionGroups = [['customers.show']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->description('Authorized fiscal year ID to use for the account balance.'),
            'query' => $schema->string()->description('Customer name, mobile or phone number, national ID, or customer ID to search for.')->required(),
            'limit' => $schema->integer()->min(1)->max(20)->description('Maximum number of matching customers to return (default: 5).'),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['sometimes', 'integer', 'min:1'],
            'query' => ['required', 'string', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        $queryStr = trim($arguments['query']);

        $limit = isset($arguments['limit']) ? max(1, min(20, (int) $arguments['limit'])) : 5;
        $currency = (string) ($fiscalYear->company?->currency ?: 'Rial');

        $query = Customer::query();

        if (is_numeric($queryStr)) {
            $query->where(function ($q) use ($queryStr) {
                $q->where('id', (int) $queryStr)
                    ->orWhere('phone', 'like', "%{$queryStr}%")
                    ->orWhere('mobile', 'like', "%{$queryStr}%")
                    ->orWhere('personal_code', 'like', "%{$queryStr}%");
            });
        } else {
            $query->where('name', 'like', "%{$queryStr}%");
        }

        $customers = $query->with('subject')->take($limit)->get();

        if ($customers->isEmpty()) {
            return $this->success([
                'fiscal_year_id' => (int) $fiscalYear->id,
                'currency' => $currency,
                'found' => false,
                'message' => __("No customer found matching ':query'.", ['query' => $queryStr]),
                'customers' => [],
            ]);
        }

        $results = [];
        foreach ($customers as $c) {
            $balance = (int) SubjectService::sumSubject($c->subject);
            [$status, $statusCode] = match (true) {
                $balance < 0 => [__('Debtor'), 'debtor'],
                $balance > 0 => [__('Creditor'), 'creditor'],
                default => [__('Settled (zero)'), 'settled'],
            };

            $results[] = [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone ?: $c->mobile,
                'balance' => $balance,
                'formatted_balance' => number_format(abs($balance)).' '.$currency.' ('.$status.')',
                'balance_status' => $status,
                'balance_status_code' => $statusCode,
                'credit_limit' => (int) ($c->credit ?? 0),
                'formatted_credit_limit' => number_format((int) ($c->credit ?? 0)).' '.$currency,
            ];
        }

        $first = $results[0];
        $humanSummary = count($results) === 1
        ? __('Account party :name (ID :id) has a balance of :balance.',
            ['name' => $first['name'], 'id' => $first['id'], 'balance' => $first['formatted_balance']]
        )
        : __('Found :count matching customers. The first result is :name with a balance of :balance.',
            ['count' => count($results), 'name' => $first['name'], 'balance' => $first['formatted_balance']]
        );

        return $this->success([
            'fiscal_year_id' => (int) $fiscalYear->id,
            'currency' => $currency,
            'found' => true,
            'count' => count($results),
            'customers' => $results,
            'human_summary' => $humanSummary,
        ]);
    }
}
