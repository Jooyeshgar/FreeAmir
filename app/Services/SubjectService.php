<?php

namespace App\Services;

use App\Models\Subject;

class SubjectService
{
    public function sumSubjectWithDateRange(?Subject $subject, bool $countOnly = false)
    {
        if (is_null($subject)) {
            return [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0];
        }

        $year = config('active-company-fiscal-year');

        $months = [
            1 => [1, 31],
            2 => [1, 31],
            3 => [1, 31],
            4 => [1, 31],
            5 => [1, 31],
            6 => [1, 31],
            7 => [1, 30],
            8 => [1, 30],
            9 => [1, 30],
            10 => [1, 30],
            11 => [1, 30],
            12 => [1, 29],
        ];

        $subjectIds = $subject->getAllDescendantIds();
        $transactionQuery = \App\Models\Transaction::query()->whereIn('subject_id', $subjectIds);
        $monthlySum = [];

        $select = $countOnly
            ? 'COUNT(*) as total'
            : 'SUM(transactions.value) as total';

        foreach ($months as $month => [$startDay, $endDay]) {
            $startDate = jalali_to_gregorian($year, $month, $startDay, '-');
            $endDate = jalali_to_gregorian($year, $month, $endDay, '-');

            $transactions = (clone $transactionQuery)
                ->join('documents', 'documents.id', '=', 'transactions.document_id')
                ->whereBetween('documents.date', [$startDate, $endDate])
                ->selectRaw("DATE(documents.date) as date, {$select}")
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'date')
                ->map(fn ($v) => (int) $v);

            foreach ($transactions as $date => $total) {
                $monthlySum[$month] = ($monthlySum[$month] ?? 0) + $total;
            }
        }

        return $monthlySum;
    }

    /**
     * Calculate the total sum of transactions for a subject with all its descendants recursively.
     */
    public static function sumSubject(string|int|Subject|null $code, bool $both = true, bool $debit = false): int
    {
        if (is_null($code)) {
            return 0;
        } elseif ($code instanceof Subject) {
            $subject = $code->loadMissing(['transactions']);
        } elseif (is_int($code)) {
            $subject = Subject::with(['transactions'])->find($code);
        } else {
            $subject = Subject::with(['transactions'])->where('code', $code)->first();
        }

        if (! $subject) {
            return 0;
        }

        self::eagerLoadDescendants($subject);

        return self::sumSubjectRecursively($subject, $both, $debit);
    }

    /**
     * Recursively sum transactions for a subject and all its descendants
     */
    private static function sumSubjectRecursively(Subject $subject, bool $both, bool $debit): int
    {
        $sum = 0;
        if ($both) {
            $sum = $subject->transactions->sum('value');
        } elseif ($debit) {
            $sum = $subject->transactions->where('value', '<', 0)->sum('value');
        } else {
            $sum = $subject->transactions->where('value', '>', 0)->sum('value');
        }

        $children = $subject->children()->with('transactions')->get();
        /** @var Subject $child */
        foreach ($children as $child) {
            $sum += self::sumSubjectRecursively($child, $both, $debit);
        }

        return $sum;
    }

    /**
     * Recursively eager-load all descendants with their transactions.
     */
    private static function eagerLoadDescendants(Subject $subject): void
    {
        $subject->loadMissing(['children' => function ($query) {
            $query->with('transactions');
        }]);

        foreach ($subject->children as $child) {
            self::eagerLoadDescendants($child);
        }
    }

    /**
     * Create a new Subject with only the required inputs.
     *
     * Accepted keys in $data:
     * - name (string, required)
     * - parent_id (int|null, optional)
     */
    public function createSubject(array $data): Subject
    {
        $name = $data['name'] ?? null;
        if (! $name) {
            throw new \InvalidArgumentException('The name field is required.');
        }

        $parentId = $data['parent_id'] ?? null;
        if ($parentId === '' || $parentId === 0) {
            $parentId = null; // normalize to null for roots
        }

        $companyId = $data['company_id'] ?? getActiveCompany();
        if (! $companyId) {
            throw new \InvalidArgumentException('The company_id is required or must be available in session.');
        }

        $parentSubject = null;
        if ($parentId !== null) {
            $parentSubject = Subject::withoutGlobalScopes()->where('company_id', $companyId)->find($parentId);

            if (! $parentSubject) {
                throw new \InvalidArgumentException(__('Parent subject not found in the given company.'));
            }
        }

        if (isset($data['code']) && $data['code'] !== '') {
            $code = $this->buildCodeWithParent($data['code'], $parentId, (int) $companyId);
            $this->validateCodeUniqueness($code, (int) $companyId);
        } else {
            $code = $this->generateCode($parentId, (int) $companyId);
        }

        if (isset($data['type'], $data['is_permanent'])) {
            $resolvedType = $parentSubject ? $this->resolveTypeForParent($parentSubject, $data['type']) : ($data['type'] ?? 'both');
            $is_permanent = $parentSubject ? $parentSubject->is_permanent : ($data['is_permanent']);
        } else {
            $resolvedType = $parentSubject ? $this->resolveTypeForParent($parentSubject, null) : 'both';
            $is_permanent = $parentSubject ? $parentSubject->is_permanent : false;
        }

        $attributes = [
            'name' => $name,
            'parent_id' => $parentId,
            'company_id' => $companyId,
            'type' => $resolvedType,
            'code' => $code,
            'is_permanent' => $is_permanent,
        ];

        return Subject::create($attributes);
    }

    /**
     * Edit an existing Subject.
     *
     * Accepted keys in $data:
     * - name (string, optional)
     * - parent_id (int|null, optional) - will trigger code regeneration if changed
     * - type (string, optional) - 'debtor', 'creditor', or 'both'
     */
    public function editSubject(Subject $subject, array $data): Subject
    {
        if (! $subject->exists) {
            throw new \InvalidArgumentException(__('Subject does not exist.'));
        }

        $companyId = $subject->company_id;

        $parentIdChanged = array_key_exists('parent_id', $data) && $data['parent_id'] !== $subject->parent_id;

        if ($parentIdChanged) {
            $newParentId = $data['parent_id'];
            if ($newParentId === '' || $newParentId === 0) {
                $newParentId = null;
            }

            if ($newParentId !== null) {
                $descendantIds = $subject->getAllDescendantIds();
                if (in_array($newParentId, $descendantIds)) {
                    throw new \InvalidArgumentException(__('Cannot move a subject to one of its descendants.'));
                }

                $newParent = Subject::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->find($newParentId);
                if (! $newParent) {
                    throw new \InvalidArgumentException(__('New parent subject not found in the given company.'));
                }

                $data['is_permanent'] = $newParent->is_permanent;
                $data['type'] = $this->resolveTypeForParent($newParent, $data['type'] ?? null);
            }

            if (isset($data['code']) && ! empty($data['code'])) {
                $newCode = $this->buildCodeWithParent($data['code'], $newParentId, $companyId);
                $this->validateCodeUniqueness($newCode, $companyId, $subject->id);
            } else {
                $newCode = $this->generateCode($newParentId, $companyId);
            }
            $data['code'] = $newCode;

            $subject->update(array_intersect_key($data, array_flip(['name', 'parent_id', 'code', 'type', 'is_permanent'])));

            $this->updateDescendantCodesTypesIsPermanents($subject);
        } else {
            $allowedFields = array_intersect_key($data, array_flip(['name', 'code', 'type', 'is_permanent']));
            if (! empty($allowedFields)) {
                $oldCode = $subject->code;
                $oldType = $subject->type;
                $oldIsPermanent = $subject->is_permanent;

                if (isset($allowedFields['code']) && ! empty($allowedFields['code'])) {
                    $newCode = $this->buildCodeWithParent($allowedFields['code'], $subject->parent_id, $companyId);
                    $this->validateCodeUniqueness($newCode, $companyId, $subject->id);
                } else {
                    $newCode = $subject->code; // If code is not being changed, keep the old code to avoid unnecessary updates and descendant code regenerations
                }

                $allowedFields['code'] = $newCode;

                $subject->update($allowedFields);
                if ($allowedFields['code'] !== $oldCode || (isset($allowedFields['type']) && $allowedFields['type'] !== $oldType) || (isset($allowedFields['is_permanent']) && $allowedFields['is_permanent'] !== $oldIsPermanent)) {
                    $this->updateDescendantCodesTypesIsPermanents($subject);
                }
            }
        }

        return $subject->fresh();
    }

    private function resolveTypeForParent(Subject $parent, ?string $requestedType): string
    {
        if ($parent->type !== 'both') {
            return $parent->type;
        }

        return $requestedType ?? 'both';
    }

    /**
     * Recursively update codes, types and is_permanent for all descendants of a subject.
     */
    private function updateDescendantCodesTypesIsPermanents(Subject $subject): void
    {
        $children = $subject->children()->get();

        foreach ($children as $child) {
            $childOwnPortion = substr($child->code, -3);
            $newCode = $subject->code.$childOwnPortion;
            $newType = $this->resolveTypeForParent($subject, $child->type);
            $newIsPermanent = $subject->is_permanent;

            $child->update(['code' => $newCode, 'type' => $newType, 'is_permanent' => $newIsPermanent]);

            if ($child->hasChildren()) {
                $this->updateDescendantCodesTypesIsPermanents($child);
            }
        }
    }

    public function getAllowedTypesForSubject(?Subject $parentSubject): array
    {
        if (is_null($parentSubject) || $parentSubject->type === 'both') {
            return ['debtor', 'creditor', 'both'];
        }

        return [$parentSubject->type];
    }

    /**
     * Build a complete code by combining parent code with provided code portion.
     */
    private function buildCodeWithParent(string $codePortion, ?int $parentId, int $companyId): string
    {
        // Sanitize the code portion (remove any non-numeric characters)
        $codePortion = preg_replace('/[^0-9]/', '', $codePortion);

        if ($parentId) {
            $parent = Subject::withoutGlobalScopes()->where('company_id', $companyId)->find($parentId);
            if (! $parent) {
                throw new \InvalidArgumentException(__('Parent subject not found in the given company.'));
            }

            // Ensure the code portion is 3 digits
            if (strlen($codePortion) > 3) {
                throw new \InvalidArgumentException(__('Code portion cannot exceed 3 digits.'));
            }
            $codePortion = str_pad($codePortion, 3, '0', STR_PAD_LEFT);

            return $parent->code.$codePortion;
        }

        // Root subject - ensure it's 3 digits
        if (strlen($codePortion) > 3) {
            throw new \InvalidArgumentException('Root subject code cannot exceed 3 digits.');
        }

        return str_pad($codePortion, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Validate that the code is unique within the company.
     */
    private function validateCodeUniqueness(string $code, int $companyId, ?int $excludeId = null): void
    {
        $query = Subject::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', $code);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException(__('The code :code already exists in this company.', ['code' => $code]));
        }
    }

    /**
     * Generate hierarchical subject code for the given company and parent.
     */
    private function generateCode(?int $parentId, int $companyId): string
    {
        if ($parentId) {
            $parent = Subject::withoutGlobalScopes()->where('company_id', $companyId)->find($parentId);
            if (! $parent) {
                throw new \InvalidArgumentException('Parent subject not found in the given company.');
            }

            $parentCode = $parent->code;
            $lastChild = Subject::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('parent_id', $parentId)
                ->orderBy('code', 'desc')
                ->first();

            if ($lastChild) {
                $childPart = substr($lastChild->code, -3);
                $next = (int) $childPart + 1;
                if ($next > 999) {
                    throw new \Exception("Maximum of 999 children reached for parent {$parentCode}");
                }

                $code = $parentCode.str_pad($next, 3, '0', STR_PAD_LEFT);

                try {
                    $this->validateCodeUniqueness($code, $companyId);
                } catch (\Exception $e) {
                    while (Subject::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->exists()) {
                        $next++;
                        if ($next > 999) {
                            throw new \Exception("Maximum of 999 children reached for parent {$parentCode}");
                        }
                        $code = $parentCode.str_pad($next, 3, '0', STR_PAD_LEFT);
                    }
                }

                return $code;
            }

            return $parentCode.'001';
        }

        // Root subject generation
        $lastRoot = Subject::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->orderBy('code', 'desc')
            ->first();

        $next = 1;
        if ($lastRoot) {
            $next = (int) $lastRoot->code + 1;
            if ($next > 999) {
                throw new \Exception('Maximum of 999 root subjects reached');
            }
        }

        return str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}
