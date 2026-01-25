<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Config;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Cookie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HomeSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = $this->setActiveCompanyContext();
        $this->hydrateAmirConfig();
        $subjects = $this->existingCashAndBankSubjects();

        $this->seedCashAndBankTransactions($subjects, $companyId);

        $this->incomeChart($companyId);
    }

    private function setActiveCompanyContext(): int
    {
        $company = Company::withoutGlobalScopes()->find(config('active-company-id')) ?? Company::withoutGlobalScopes()->first();

        if (! $company) {
            Cookie::queue('active-company-id', 1);

            return 1;
        }

        Cookie::queue('active-company-id', $company->id);

        config([
            'active-company-name' => $company->name,
            'active-company-fiscal-year' => $company->fiscal_year,
        ]);

        return $company->id;
    }

    private function hydrateAmirConfig(): void
    {
        Config::withoutGlobalScopes()->where('category', 1)->get()->each(function (Config $config) {
            config(['amir.'.$config->key => $config->value]);
        });
    }

    private function existingCashAndBankSubjects(): Collection
    {
        $bankParentId = (int) config('amir.bank');
        $cashParentId = (int) config('amir.cash_book');

        $banks = Subject::withoutGlobalScopes()->where('parent_id', $bankParentId)->get();
        $cashBooks = Subject::withoutGlobalScopes()->where('parent_id', $cashParentId)->get();

        return $banks->merge($cashBooks);
    }

    private function seedCashAndBankTransactions(Collection $subjects, int $companyId): void
    {
        $user = User::withoutGlobalScopes()->first() ?? User::factory()->create();
        if (! $user->companies()->where('companies.id', $companyId)->exists()) {
            $user->companies()->attach($companyId);
        }

        $userId = $user->id;
        if ($userId === null || $subjects->isEmpty()) {
            return;
        }

        $documentNumber = (int) (Document::withoutGlobalScopes()->max('number') ?? 0);
        $startDate = Carbon::now()->startOfMonth()->subMonths(11);

        foreach ($subjects as $subject) {
            foreach (range(0, 11) as $monthOffset) {
                $date = (clone $startDate)->addMonths($monthOffset)->addDays(random_int(0, 20));
                $documentNumber++;

                $document = Document::create([
                    'number' => $documentNumber,
                    'date' => $date,
                    'title' => 'Seeded cash/bank flow',
                    'creator_id' => $userId,
                    'company_id' => $companyId,
                ]);

                Transaction::create([
                    'subject_id' => $subject->id,
                    'document_id' => $document->id,
                    'user_id' => $userId,
                    'value' => random_int(-25000000, 35000000),
                    'desc' => 'Seeded balance change',
                ]);
            }
        }
    }

    private function incomeChart(int $companyId)
    {
        $user = User::withoutGlobalScopes()->first() ?? User::factory()->create();
        if (! $user->companies()->where('companies.id', $companyId)->exists()) {
            $user->companies()->attach($companyId);
        }

        $documentNumber = (int) (Document::withoutGlobalScopes()->max('number') ?? 0);
        $startDate = Carbon::now()->startOfMonth()->subMonths(11);

        $subjects = [
            'sales' => (int) config('amir.sales_revenue'),
            'service' => (int) config('amir.service_revenue'),
            'other_income' => (int) config('amir.income'),
            'cost' => (int) config('amir.cost'),
        ];

        foreach (range(0, 11) as $monthOffset) {
            $date = (clone $startDate)->addMonths($monthOffset)->addDays(random_int(0, 20));
            $documentNumber++;

            $document = Document::create([
                'number' => $documentNumber,
                'date' => $date,
                'title' => 'Seeded income summary',
                'creator_id' => $user->id,
                'company_id' => $companyId,
            ]);

            $salesAmount = random_int(12000000, 32000000);
            $serviceAmount = random_int(8000000, 18000000);
            $otherIncomeAmount = random_int(2000000, 8000000);
            $costAmount = random_int(9000000, 25000000);

            Transaction::create([
                'subject_id' => $subjects['sales'],
                'document_id' => $document->id,
                'user_id' => $user->id,
                'value' => $salesAmount,
                'desc' => 'Seeded sales revenue',
            ]);

            Transaction::create([
                'subject_id' => $subjects['service'],
                'document_id' => $document->id,
                'user_id' => $user->id,
                'value' => $serviceAmount,
                'desc' => 'Seeded service revenue',
            ]);

            Transaction::create([
                'subject_id' => $subjects['other_income'],
                'document_id' => $document->id,
                'user_id' => $user->id,
                'value' => $otherIncomeAmount,
                'desc' => 'Seeded other income',
            ]);

            Transaction::create([
                'subject_id' => $subjects['cost'],
                'document_id' => $document->id,
                'user_id' => $user->id,
                'value' => -$costAmount,
                'desc' => 'Seeded cost',
            ]);
        }
    }
}
