<?php

namespace App\Services;

use App\Models\Subject;
use Illuminate\Support\Str;

class SubjectCreatorService
{
	public function createSubject(array $data)
	{
		$parentCode = $data['parent_code'] ?? 0;
		$name = $data['name'];
		$code = $data['code'] ?? null;

		if (!$code) {
			$code = $this->generateCode($parentCode);
		}

		$subject = Subject::create([
			'parent_code' => $parentCode,
			'name' => $name,
			'code' => $code,
		]);

		return $subject;
	}

	private function generateCode($parentCode)
	{
		$lastSubject = Subject::where('parent_code', $parentCode)->orderByDesc('code')->first();

		if ($lastSubject) {
			$lastCode = (int) Str::substr($lastSubject->code, -3);
			$newCode = str_pad($lastCode + 1, 3, '0', STR_PAD_LEFT);
			$code = $parentCode . $newCode;
		} else {
			$code = $parentCode . '001';
		}

		// Check for code uniqueness
		$existingSubject = Subject::where('code', $code)->first();
		if ($existingSubject) {
			throw new \Exception('Code already exists');
		}

		return $code;
	}
}
