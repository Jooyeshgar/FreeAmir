<?php

namespace App\Services\DocumentImportExport;

use App\Models\Subject;
use Illuminate\Support\Facades\Log;

class ImportSubjectResolver
{
    private array $cache = [];

    /**
     * Map of full code => name for every subject mentioned anywhere in the current import file.
     * Used to build a missing ancestor on the fly instead of rejecting the document.
     *
     * @var array<string,string>
     */
    private array $knownSubjects = [];

    /**
     * Names that are attached to more than one distinct code within the current import file.
     * For such names the name is not a reliable key, so matching falls back to the code.
     *
     * @var array<string,int>
     */
    private array $ambiguousNames = [];

    /**
     * Seed the codes/names available in the file being imported so ancestors that are not yet
     * in the database can be reconstructed from sibling rows, and flag names that the file reuses
     * for more than one code.
     *
     * @param  array<string,string>  $map  full code => name
     */
    public function setKnownSubjects(array $map): void
    {
        $this->knownSubjects = $map;
        $this->ambiguousNames = array_filter(array_count_values(array_values($map)), fn ($count) => $count > 1);
    }

    public function findOrCreate(string $code, string $name, string $parentCode = '', ?bool $isPermanent = null, ?string $type = null): Subject
    {
        $code = trim($code);
        $name = trim($name);

        if (isset($this->cache[$code])) {
            return $this->cache[$code];
        }

        $parent = $this->resolveParent(trim($parentCode));
        $parentId = $parent?->id;
        $nameIsReliable = $name !== '' && ! isset($this->ambiguousNames[$name]);

        $candidates = $nameIsReliable
            ? Subject::where('name', $name)
                ->when($parentId !== null, fn ($q) => $q->where('parent_id', $parentId))
                ->when($parentId === null, fn ($q) => $q->whereNull('parent_id'))
                ->get()
            : collect();

        if ($candidates->count() === 1) {
            return $this->cache[$code] = $candidates->first();
        }

        if ($candidates->count() > 1) {
            $byCode = $candidates->firstWhere('code', $code);

            return $this->cache[$code] = $byCode ?? $this->createSubject($code, $name, $parent, $isPermanent, $type);
        }

        // No account with that name: fall back to an exact code match, then create.
        $byCode = Subject::where('code', $code)->first();
        if ($byCode) {
            return $this->cache[$code] = $byCode;
        }

        return $this->cache[$code] = $this->createSubject($code, $name, $parent, $isPermanent, $type);
    }

    public function resolveTopLevel(string $code, string $name, bool $isPermanent, string $type = 'both'): Subject
    {
        $code = trim($code);
        $name = trim($name);

        if (isset($this->cache[$code])) {
            return $this->cache[$code];
        }

        $existing = Subject::where('code', $code)->first();

        if ($existing) {
            if ((bool) $existing->is_permanent === $isPermanent && $existing->type === $type) {
                return $this->cache[$code] = $existing;
            }

            return $this->findOrCreate($code, self::synthesizeName($code), '');
        }

        return $this->cache[$code] = $this->createSubject($code, $name, null, $isPermanent, $type);
    }

    public static function synthesizeName(string $code): string
    {
        $code = trim($code);
        $level = $code === '' ? 1 : (int) ceil(strlen($code) / 3);
        $formatted = formatCode($code);

        return match ($level) {
            1 => __('Kol :code', ['code' => $formatted]),
            2 => __('Moein :code', ['code' => $formatted]),
            3 => __('Tafsili :code', ['code' => $formatted]),
            default => __('Level :n :code', ['n' => $level, 'code' => $formatted]),
        };
    }

    /**
     * Resolve a subject's parent, building it (and its own ancestors, recursively) when it is not yet present in the database.
     *
     * Order:
     *   1. In-memory cache (already created this import session)
     *   2. Database
     *   3. knownSubjects map (names provided by other rows in the same file)
     *   4. Synthesized name derived from the code's hierarchy level
     */
    private function resolveParent(string $parentCode): ?Subject
    {
        if ($parentCode === '') {
            return null;
        }

        $parent = $this->cache[$parentCode] ?? Subject::where('code', $parentCode)->first();
        if ($parent) {
            return $this->cache[$parentCode] = $parent;
        }

        $name = $this->knownSubjects[$parentCode] ?? self::synthesizeName($parentCode);

        return $this->findOrCreate($parentCode, $name, self::parentCodeOf($parentCode));
    }

    public static function parentCodeOf(string $code): string
    {
        $code = trim($code);

        return strlen($code) > 3 ? substr($code, 0, strlen($code) - 3) : '';
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
        $this->knownSubjects = [];
        $this->ambiguousNames = [];
    }

    private function createSubject(string $code, string $name, ?Subject $parent, ?bool $isPermanent = null, ?string $type = null): Subject
    {
        $subject = new Subject([
            'name' => $name,
            'parent_id' => $parent?->id,
            'company_id' => getActiveCompany(),
            'type' => $type ?? 'both',
            'is_permanent' => $isPermanent ?? false,
        ]);

        $subject->code = ! Subject::where('code', $code)->exists() ? $code : $subject->generateCode();
        $subject->save();

        Log::info("ImportSubjectResolver: created subject [{$subject->code}] {$name}");

        return $subject;
    }
}
