<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Models\Customer;
use App\Models\Transaction;
use App\Traits\FilterName;

class CustomerFilter extends FilterAbstract
{
    use FilterName;

    protected array $filterables = [
        'name' => self::TYPE_STRING,
        'subject_code' => self::TYPE_STRING,
        'phone' => self::TYPE_STRING,
        'group_id' => self::TYPE_STRING,
        'balance' => self::TYPE_STRING,
    ];

    public function subject_code(string $subjectCode): void
    {
        if ($subjectCode === '') {
            return;
        }

        $subjectCode = str_replace('/', '', $subjectCode);

        $this->builder->whereHas('subject', function ($subject) use ($subjectCode) {
            $subject->where('code', 'like', '%'.$subjectCode.'%');
        });
    }

    public function phone(string $phone): void
    {
        if ($phone !== '') {
            $this->builder->where(function ($query) use ($phone) {
                $query->where('phone', 'like', '%'.$phone.'%')
                    ->orWhere('mobile', 'like', '%'.$phone.'%');
            });
        }
    }

    public function group_id(string $groupId): void
    {
        if ($groupId !== '' && $groupId !== 'all') {
            $this->builder->where('group_id', $groupId);
        }
    }

    public function balance(string $balance): void
    {
        if (! in_array($balance, ['debt', 'credit'], true)) {
            return;
        }

        $this->builder->whereIn('subject_id', $this->balanceSubjectIds($balance));
        $this->builder->reorder('balance', $balance === 'debt' ? 'asc' : 'desc');
    }

    private function balanceSubjectIds(string $balance): mixed
    {
        $customerSubjectIds = Customer::query()->whereNotNull('subject_id')->pluck('subject_id');
        $comparison = $balance === 'credit' ? 'SUM(value) > 0' : 'SUM(value) < 0';

        return Transaction::query()
            ->whereIn('subject_id', $customerSubjectIds)
            ->groupBy('subject_id')
            ->havingRaw($comparison)
            ->pluck('subject_id');
    }
}
