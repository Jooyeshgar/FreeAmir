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

		$code = $this->generateCode($parentId, (int) $companyId);

		$attributes = [
			'name' => $name,
			'parent_id' => $parentId,
			'company_id' => $companyId,
			'code' => $code,
		];

		return Subject::create($attributes);
	}

	/**
	 * Generate hierarchical subject code for the given company and parent.
	 */
	private function generateCode(?int $parentId, int $companyId): string
	{
		// Use explicit company scoping; avoid relying on session-bound global scopes
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
