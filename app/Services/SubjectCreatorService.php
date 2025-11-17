<?php

namespace App\Services;

use App\Models\Subject;

class SubjectCreatorService
{
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
        if (!$name) {
            throw new \InvalidArgumentException('The name field is required.');
        }

        $parentId = $data['parent_id'] ?? null;
        if ($parentId === '' || $parentId === 0) {
            $parentId = null; // normalize to null for roots
        }

        $companyId = $data['company_id'] ?? session('active-company-id');
        if (!$companyId) {
            throw new \InvalidArgumentException('The company_id is required or must be available in session.');
        }

        if (isset($data['code']) && $data['code'] !== '') {
            $code = $this->buildCodeWithParent($data['code'], $parentId, (int) $companyId);
            $this->validateCodeUniqueness($code, (int) $companyId);
        } else {
            $code = $this->generateCode($parentId, (int) $companyId);
        }

        $attributes = [
            'name' => $name,
            'parent_id' => $parentId,
            'company_id' => $companyId,
            'code' => $code,
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
        if (!$subject->exists) {
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
                if (!$newParent) {
                    throw new \InvalidArgumentException(__('New parent subject not found in the given company.'));
                }
            }

            if (isset($data['code']) && $data['code'] !== '') {
                $newCode = $this->buildCodeWithParent($data['code'], $newParentId, $companyId);
                $this->validateCodeUniqueness($newCode, $companyId, $subject->id);
            } else {
                $newCode = $this->generateCode($newParentId, $companyId);
            }
            $data['code'] = $newCode;

            $subject->update(array_intersect_key($data, array_flip(['name', 'parent_id', 'code', 'type'])));

            $this->updateDescendantCodes($subject);
        } else {
            $allowedFields = array_intersect_key($data, array_flip(['name', 'type']));
            if (!empty($allowedFields)) {
                $subject->update($allowedFields);
            }
        }

        return $subject->fresh();
    }

    /**
     * Recursively update codes for all descendants of a subject.
     */
    private function updateDescendantCodes(Subject $subject): void
    {
        $children = $subject->children()->get();

        foreach ($children as $child) {
            $childOwnPortion = substr($child->code, -3);
            $newCode = $subject->code . $childOwnPortion;
            
            $child->update(['code' => $newCode]);

            if ($child->hasChildren()) {
                $this->updateDescendantCodes($child);
            }
        }
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
            if (!$parent) {
                throw new \InvalidArgumentException(__('Parent subject not found in the given company.'));
            }

            // Ensure the code portion is 3 digits
            if (strlen($codePortion) > 3) {
                throw new \InvalidArgumentException(__('Code portion cannot exceed 3 digits.'));
            }
            $codePortion = str_pad($codePortion, 3, '0', STR_PAD_LEFT);

            return $parent->code . $codePortion;
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
            if (!$parent) {
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
                $next = (int)$childPart + 1;
                if ($next > 999) {
                    throw new \Exception("Maximum of 999 children reached for parent {$parentCode}");
                }
                return $parentCode . str_pad($next, 3, '0', STR_PAD_LEFT);
            }

            return $parentCode . '001';
        }

        // Root subject generation
        $lastRoot = Subject::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->orderBy('code', 'desc')
            ->first();

        $next = 1;
        if ($lastRoot) {
            $next = (int)$lastRoot->code + 1;
            if ($next > 999) {
                throw new \Exception('Maximum of 999 root subjects reached');
            }
        }

        return str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}
