<?php

namespace Database\Seeders;

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
        Cookie::queue('active-company-id', 1);

        $this->hydrateAmirConfig();
        $subjects = $this->existingCashAndBankSubjects();

        $this->seedCashAndBankTransactions($subjects);
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

    private function seedCashAndBankTransactions(Collection $subjects): void
    {
        $userId = User::withoutGlobalScopes()->first()?->id;
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
                    'company_id' => 1,
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
}
