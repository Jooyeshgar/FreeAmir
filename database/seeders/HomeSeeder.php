<?php

namespace Database\Seeders;

use App\Models\Config;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HomeSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = (int) getActiveCompany();
        $this->hydrateAmirConfig();
        $subjects = $this->existingCashAndBankSubjects($companyId);

        $this->seedCashAndBankTransactions($subjects, $companyId);
    }

    private function hydrateAmirConfig(): void
    {
        Config::withoutGlobalScopes()->where('category', 1)->where(function ($query) {
            $query->whereNull('company_id')->orWhere('company_id', getActiveCompany());
        })->get()->each(function (Config $config) {
            config(['amir.'.$config->key => $config->value]);
        });
    }

    private function existingCashAndBankSubjects(int $companyId): Collection
    {
        $bankParentId = (int) config('amir.bank');
        $cashParentId = (int) config('amir.cash_book');

        $banks = Subject::withoutGlobalScopes()->where('company_id', $companyId)->where('parent_id', $bankParentId)->get();
        $cashBooks = Subject::withoutGlobalScopes()->where('company_id', $companyId)->where('parent_id', $cashParentId)->get();

        return $banks->merge($cashBooks);
    }

    private function seedCashAndBankTransactions(Collection $subjects, int $companyId): void
    {
        $userId = User::withoutGlobalScopes()->first()?->id;
        if ($userId === null || $subjects->isEmpty()) {
            return;
        }

        $documentNumber = (int) (Document::withoutGlobalScopes()->where('company_id', $companyId)->max('number') ?? 0);
        $startDate = Carbon::now()->startOfMonth()->subMonths(11);

        foreach ($subjects as $subject) {
            foreach (range(0, 11) as $monthOffset) {
                $date = (clone $startDate)->addMonths($monthOffset)->addDays(random_int(0, 20));
                $documentNumber++;

                $document = Document::create([
                    'number' => $documentNumber,
                    'date' => $date,
                    'title' => __('Cash and bank fund flow'),
                    'creator_id' => $userId,
                    'approved_at' => $date->addDays(random_int(0, 5)),
                    'approver_id' => $userId,
                    'company_id' => $companyId,
                ]);

                $value = random_int(0, 35000000);

                Transaction::create([
                    'subject_id' => $subject->id,
                    'document_id' => $document->id,
                    'user_id' => $userId,
                    'value' => -$value,
                    'desc' => __('Account balance flow entry'),
                ]);

                $randomSubject = Subject::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->whereNot('id', $subject->id)
                    ->whereNot('parent_id', $subject->id)
                    ->inRandomOrder()
                    ->first();
                if (! $randomSubject) {
                    continue;
                }
                Transaction::create([
                    'subject_id' => $randomSubject->id,
                    'document_id' => $document->id,
                    'user_id' => $userId,
                    'value' => $value,
                    'desc' => __('Account balance flow entry'),
                ]);
            }
        }
    }
}
