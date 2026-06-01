<?php

namespace App\Services\DocumentImportExport;

use App\Models\Subject;
use Illuminate\Support\Facades\Log;

class ImportSubjectResolver
{
    private array $cache = [];

    public function findOrCreate(string $code, string $name, string $parentCode = ''): Subject
    {
        if (isset($this->cache[$code])) {
            return $this->cache[$code];
        }

        $subject = Subject::where('code', $code)->first();
        if ($subject) {
            return $this->cache[$code] = $subject;
        }

        $parent = null;
        if ($parentCode !== '') {
            $parent = $this->cache[$parentCode] ?? Subject::where('code', $parentCode)->first();
            if (! $parent) {
                Log::warning("ImportSubjectResolver: parent code {$parentCode} not found when creating {$code}");
            } else {
                $this->cache[$parentCode] = $parent;
            }
        }

        $parentId = $parent?->id;
        $byName = Subject::where('name', $name)->when($parentId !== null, fn ($q) => $q->where('parent_id', $parentId))
            ->when($parentId === null, fn ($q) => $q->whereNull('parent_id'))->first();

        if ($byName) {
            return $this->cache[$code] = $byName;
        }

        return $this->cache[$code] = $this->createSubject($code, $name, $parent);
    }

    public function processSubjectRows(array $rows): array
    {
        foreach ($rows as $row) {
            $code = trim($row['code'] ?? '');
            $name = trim($row['name'] ?? '');
            $parentCode = trim($row['parent_code'] ?? '');

            if ($code === '' || $name === '') {
                continue;
            }

            $this->findOrCreate($code, $name, $parentCode);
        }

        return $this->cache;
    }

    public function reset(): void
    {
        $this->cache = [];
    }

    private function createSubject(string $code, string $name, ?Subject $parent): Subject
    {
        $subject = new Subject([
            'name' => $name,
            'parent_id' => $parent?->id,
            'company_id' => getActiveCompany(),
            'type' => 'both',
            'is_permanent' => false,
        ]);

        $subject->code = ! Subject::where('code', $code)->exists() ? $code : $subject->generateCode();
        $subject->save();

        Log::info("ImportSubjectResolver: created subject [{$subject->code}] {$name}");

        return $subject;
    }
}
