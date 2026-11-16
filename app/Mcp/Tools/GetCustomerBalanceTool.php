<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ContextResolver;
use App\Models\Customer;
use App\Services\SubjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetCustomerBalanceTool extends AccountingTool
{
    protected string $name = 'get_customer_balance';

    protected string $description = 'استعلام مانده حساب، وضعیت بدهکار/بستانکار، مشخصات تماس و خلاصه وضعیت یک مشتری یا طرف‌حساب.';

    protected array $permissionGroups = [['customers.show']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->description('شناسه سال مالی مجاز برای مانده طرف‌حساب.'),
            'query' => $schema->string()->description('نام، شماره همراه، تلفن، کد ملی یا شناسه طرف‌حساب جهت جستجو.')->required(),
            'limit' => $schema->integer()->min(1)->max(20)->description('تعداد نتایج در صورت وجود چندین مشتری مشابه (پیش‌فرض ۵).'),
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

    protected function report(array $arguments): Response
    {
        $queryStr = trim($arguments['query']);

        $limit = isset($arguments['limit']) ? max(1, min(20, (int) $arguments['limit'])) : 5;

        /** @var Builder $query */
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
                'fiscal_year_id' => ContextResolver::resolveFiscalYearId($arguments),
                'found' => false,
                'message' => "طرف‌حسابی با مشخصات '{$queryStr}' یافت نشد.",
                'customers' => [],
            ]);
        }

        $results = [];
        foreach ($customers as $c) {
            $balance = (int) SubjectService::sumSubject($c->subject);
            $status = match (true) {
                $balance < 0 => 'بدهکار',
                $balance > 0 => 'بستانکار',
                default => 'تسویه (صفر)',
            };

            $results[] = [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone ?: $c->mobile,
                'balance' => $balance,
                'formatted_balance' => number_format(abs($balance)).' ریال ('.$status.')',
                'balance_status' => $status,
                'credit_limit' => (int) ($c->credit ?? 0),
                'formatted_credit_limit' => number_format((int) ($c->credit ?? 0)).' ریال',
            ];
        }

        $first = $results[0];
        $humanSummary = count($results) === 1
        ? sprintf(
            'طرف‌حساب %s (شناسه %d): مانده حساب %s می‌باشد.',
            $first['name'],
            $first['id'],
            $first['formatted_balance']
        )
        : sprintf(
            '%d مشتری مطابق با جستجوی شما یافت شد. نخستین نتیجه: %s با مانده %s.',
            count($results),
            $first['name'],
            $first['formatted_balance']
        );

        return $this->success([
            'fiscal_year_id' => ContextResolver::resolveFiscalYearId($arguments),
            'found' => true,
            'count' => count($results),
            'customers' => $results,
            'human_summary' => $humanSummary,
        ]);
    }
}
