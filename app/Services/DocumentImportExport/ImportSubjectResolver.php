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

    public function findOrCreate(string $code, string $name, string $parentCode = ''): Subject
    {
        $code = trim($code);
        $name = trim($name);

        if (isset($this->cache[$code])) {
            return $this->cache[$code];
        }

        try {
            $parent = $this->resolveParent($code, trim($parentCode));
        } catch (\RuntimeException $e) {
            // The parent cannot be resolved; an already-existing subject can still be matched by its code.
            $existing = Subject::where('code', $code)->first();
            if ($existing) {
                return $this->cache[$code] = $existing;
            }

            throw $e;
        }

        $parentId = $parent?->id;

        // Match existing accounts by name within the same parent: the same account may carry a
        // different code between systems, so the name (at the same position in the tree) is the
        // more reliable identity. The parent scope keeps distinct same-named accounts apart
        // (e.g. FreeAmir creates Product / ProductGroup / Service / ServiceGroup sharing one name).
        // When the file itself reuses the name for several codes, the name is unreliable and we
        // fall straight through to code matching.
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
            // Several accounts share this name under the same parent; the code disambiguates them.
            // Only reuse one when its code also matches, otherwise keep the accounts distinct.
            $byCode = $candidates->firstWhere('code', $code);

            return $this->cache[$code] = $byCode ?? $this->createSubject($code, $name, $parent);
        }

        // No account with that name: fall back to an exact code match, then create.
        $byCode = Subject::where('code', $code)->first();
        if ($byCode) {
            return $this->cache[$code] = $byCode;
        }

        return $this->cache[$code] = $this->createSubject($code, $name, $parent);
    }

    /**
     * Resolve a subject's parent, building it (and its own ancestors, recursively) from the
     * import file when it is not yet present in the database. Throws when the parent exists
     * neither in the system nor anywhere in the file.
     */
    private function resolveParent(string $code, string $parentCode): ?Subject
    {
        if ($parentCode === '') {
            return null;
        }

        $parent = $this->cache[$parentCode] ?? Subject::where('code', $parentCode)->first();
        if ($parent) {
            return $this->cache[$parentCode] = $parent;
        }

        // Parent is missing from the system: try to find it among the other rows of the same file
        // and create it first (recursing up the chain to the root as needed).
        if (array_key_exists($parentCode, $this->knownSubjects)) {
            return $this->findOrCreate($parentCode, $this->knownSubjects[$parentCode], self::parentCodeOf($parentCode));
        }

        throw new \RuntimeException(__('Parent subject :parent for code :code was not found in the system or in the imported file.', [
            'parent' => $parentCode,
            'code' => $code,
        ]));
    }

    /**
     * The immediate parent code of a hierarchical code (each level is three digits).
     * Returns an empty string for a top-level (root) code.
     */
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
