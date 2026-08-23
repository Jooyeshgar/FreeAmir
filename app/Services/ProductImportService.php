<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\Warehouse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductImportService
{
    /**
     * Canonical CSV columns, in export order. The header row is matched by these
     * keys (case-insensitive), so the importer is tolerant of column reordering
     * and of extra columns it does not recognise.
     */
    public const COLUMNS = [
        'code',
        'name',
        'group_name',
        'income_subject_code',
        'cogs_subject_code',
        'inventory_subject_code',
        'sales_returns_subject_code',
        'sstid',
        'location',
        'quantity',
        'quantity_warning',
        'oversell',
        'selling_price',
        'discount_formula',
        'description',
        'vat',
        'warehouse',
    ];

    /** Translation keys used by the product CSV export for importable columns. */
    private const HEADER_TRANSLATIONS = [
        'code' => 'Product code',
        'name' => 'Product name',
        'group_name' => 'Category',
        'income_subject_code' => 'Revenue subject code',
        'cogs_subject_code' => 'COGS subject code',
        'inventory_subject_code' => 'Inventory subject code',
        'sales_returns_subject_code' => 'Sales returns subject code',
        'sstid' => 'Product SSTID',
        'location' => 'Location in warehouse',
        'quantity' => 'Stock',
        'quantity_warning' => 'Quantity warning',
        'oversell' => 'Oversell',
        'selling_price' => 'Sale price',
        'discount_formula' => 'Discount formula',
        'description' => 'Description',
        'vat' => 'VAT',
        'warehouse' => 'Warehouse',
    ];

    private const SUBJECT_COLUMNS = [
        'income_subject_code' => [
            'id_column' => 'income_subject_id',
            'group_relation' => 'incomeSubject',
        ],
        'cogs_subject_code' => [
            'id_column' => 'cogs_subject_id',
            'group_relation' => 'cogsSubject',
        ],
        'inventory_subject_code' => [
            'id_column' => 'inventory_subject_id',
            'group_relation' => 'inventorySubject',
        ],
        'sales_returns_subject_code' => [
            'id_column' => 'sales_returns_subject_id',
            'group_relation' => 'salesReturnsSubject',
        ],
    ];

    /** Product fields copied straight from the CSV row (no special handling). */
    private const PLAIN_FIELDS = [
        'sstid', 'location', 'discount_formula', 'description',
    ];

    /** Numeric product fields normalised through convertToFloat. */
    private const NUMERIC_FIELDS = [
        'quantity', 'quantity_warning', 'selling_price', 'vat',
    ];

    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductGroupService $productGroupService,
        private readonly SubjectService $subjectService,
    ) {}

    /**
     * Import products from a CSV file. The whole import runs inside a single
     * transaction: any row-level error aborts the import and rolls back every
     * change made so far.
     *
     * Rows are matched to existing data by their product code:
     *   - code resolves to an existing product -> the product is updated;
     *   - no usable code, or no product carries that code yet -> a brand-new
     *     product is created (a null code auto-generates the next code).
     *
     * @param  UploadedFile|string  $file  fresh upload or a Storage-relative path
     * @return array{imported:int, updated:int, groups_created:int}
     *
     * @throws ValidationException
     */
    public function import(UploadedFile|string $file, int $companyId): array
    {
        $rows = $this->parse($file);

        if (empty($rows)) {
            $this->fail(__('The import file is empty or has no data rows.'));
        }

        return DB::transaction(function () use ($rows, $companyId) {
            $imported = 0;
            $updated = 0;
            $groupsCreated = 0;
            $groupCache = [];

            foreach ($rows as $index => $row) {
                // Human-friendly line number: +1 for the header row, +1 for 0-based index.
                $line = $index + 2;

                $name = trim((string) ($row['name'] ?? ''));
                $groupName = trim((string) ($row['group_name'] ?? ''));
                $code = $this->normalizeCode($row['code'] ?? null);

                if ($name === '') {
                    $this->fail(__('Line :line: product name is required.', ['line' => $line]));
                }

                if ($groupName === '') {
                    $this->fail(__('Line :line: group name is required.', ['line' => $line]));
                }

                // 1. Resolve the group: reuse an existing one with the same name, otherwise create it.
                $group = $groupCache[$groupName] ?? null;

                if (! $group) {
                    $group = ProductGroup::where('name', $groupName)->first();

                    if (! $group) {
                        $group = $this->productGroupService->create([
                            'name' => $groupName,
                            'company_id' => $companyId,
                        ]);
                        $groupsCreated++;
                    }

                    $groupCache[$groupName] = $group;
                }

                // 2. Build the base product attributes.
                $data = [
                    'name' => $name,
                    'group' => $group->id,
                    'company_id' => $companyId,
                    'oversell' => $this->normalizeBool($row['oversell'] ?? null),
                ];

                foreach (self::PLAIN_FIELDS as $field) {
                    $value = $row[$field] ?? null;
                    if ($value !== null && trim((string) $value) !== '') {
                        $data[$field] = trim((string) $value);
                    }
                }

                foreach (self::NUMERIC_FIELDS as $field) {
                    $value = $row[$field] ?? null;
                    if ($value !== null && trim((string) $value) !== '') {
                        $data[$field] = convertToFloat(str_replace(',', '', trim((string) $value)));
                    }
                }

                // 3. Match the product and restore any explicitly exported subject relations.
                $existing = $code !== null
                    ? Product::where('code', $code)->first()
                    : null;

                $data = array_merge($data, $this->resolveSubjects($row, $group, $name, $companyId, $existing, $line));

                $warehouseName = trim((string) ($row['warehouse'] ?? ''));
                $warehouse = $warehouseName !== ''
                    ? Warehouse::where('company_id', $companyId)->where('name', $warehouseName)->first()
                    : Warehouse::where('company_id', $companyId)->orderBy('id')->first();

                if (! $warehouse) {
                    $warehouse = Warehouse::create([
                        'name' => $warehouseName !== '' ? $warehouseName : __('Main warehouse'),
                        'company_id' => $companyId,
                    ]);
                }

                $data['warehouse_id'] = $warehouse->id;

                try {
                    if ($existing) {
                        $this->productService->update($existing, $data);
                        $updated++;
                    } else {
                        // The products table requires these columns; default them
                        // when the CSV left them blank for a new product.
                        $data['quantity'] ??= 0;
                        $data['selling_price'] ??= 0;
                        $data['code'] = $code ?? (Product::max('code') + 1);
                        $this->productService->create($data);
                        $imported++;
                    }
                } catch (ValidationException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    $this->fail(__('Line :line: :message', [
                        'line' => $line,
                        'message' => $e->getMessage(),
                    ]));
                }
            }

            return [
                'imported' => $imported,
                'updated' => $updated,
                'groups_created' => $groupsCreated,
            ];
        });
    }

    /**
     * Read the CSV and return a list of associative rows keyed by canonical column name.
     *
     * @return array<int, array<string, string>>
     */
    private function parse(UploadedFile|string $file): array
    {
        $contents = $this->readContents($file);

        // Strip a UTF-8 BOM if present so the first header is matched correctly.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle);

        if ($header === false || $header === null) {
            fclose($handle);

            return [];
        }

        $map = [];
        foreach ($header as $position => $label) {
            $key = $this->canonicalHeader((string) $label);
            if ($key !== null) {
                $map[$key] = $position;
            }
        }

        if (! isset($map['name'], $map['group_name'])) {
            fclose($handle);

            $this->fail(__('The import file must contain at least "name" and "group_name" columns.'));
        }

        $rows = [];
        while (($cols = fgetcsv($handle)) !== false) {
            // Skip fully blank lines.
            if (count(array_filter($cols, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($map as $key => $position) {
                $row[$key] = $cols[$position] ?? null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function readContents(UploadedFile|string $file): string
    {
        if ($file instanceof UploadedFile) {
            return $file->get();
        }

        return Storage::get($file) ?? '';
    }

    /**
     * Normalize a product code from the CSV: strip whitespace.
     * Returns null when no usable code was supplied.
     */
    private function normalizeCode($code): ?string
    {
        if ($code === null) {
            return null;
        }

        $code = trim((string) $code);

        return $code === '' ? null : $code;
    }

    /** Resolve canonical, English, Persian, and currently active translated headers. */
    private function canonicalHeader(string $label): ?string
    {
        $normalized = $this->normalizeHeader($label);

        foreach (self::COLUMNS as $column) {
            if ($normalized === $this->normalizeHeader($column)) {
                return $column;
            }
        }

        $locales = array_unique(array_filter(['en', 'fa', app()->getLocale(), config('app.fallback_locale')]));

        foreach (self::HEADER_TRANSLATIONS as $column => $translationKey) {
            if ($normalized === $this->normalizeHeader($translationKey)) {
                return $column;
            }

            foreach ($locales as $locale) {
                if ($normalized === $this->normalizeHeader(Lang::get($translationKey, [], $locale))) {
                    return $column;
                }
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        return mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $header)));
    }

    /**
     * Resolve exported subject codes to the correct product account branches.
     * Missing subjects are created with the requested code under the selected group.
     */
    private function resolveSubjects(array $row, ProductGroup $group, string $name, int $companyId, ?Product $existing, int $line): array
    {
        $group->loadMissing(array_column(self::SUBJECT_COLUMNS, 'group_relation'));
        $resolved = [];

        foreach (self::SUBJECT_COLUMNS as $codeColumn => $config) {
            $subjectCode = $this->normalizeSubjectCode($row[$codeColumn] ?? null);
            if ($subjectCode === null) {
                continue;
            }

            $parent = $group->{$config['group_relation']};
            if (! $parent) {
                $this->fail(__('Line :line: could not resolve the :account accounting subject for group ":group".', [
                    'line' => $line,
                    'account' => $codeColumn,
                    'group' => $group->name,
                ]));
            }

            if (strlen($subjectCode) <= strlen($parent->code) || substr($subjectCode, 0, -3) !== $parent->code) {
                $this->fail(__('Line :line: subject code :code is not a child of the expected account for group ":group".', [
                    'line' => $line,
                    'code' => formatCode($subjectCode),
                    'group' => $group->name,
                ]));
            }

            $subject = Subject::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $subjectCode)->first();

            if ($existing && $existing->{$config['id_column']} && (int) $existing->{$config['id_column']} !== (int) $subject?->id) {
                $this->fail(__('Line :line: subject code :code does not match the existing product account relation.', [
                    'line' => $line,
                    'code' => formatCode($subjectCode),
                ]));
            }

            if ($subject) {
                $linked = $subject->subjectable;
                if ($linked !== null && (! $existing || ! $linked->is($existing))) {
                    $this->fail(__('Line :line: subject code :code is already linked to another record.', [
                        'line' => $line,
                        'code' => formatCode($subjectCode),
                    ]));
                }
            } else {
                $subject = $this->subjectService->createSubject([
                    'name' => $name,
                    'parent_id' => $parent->id,
                    'company_id' => $companyId,
                    'code' => substr($subjectCode, -3),
                ]);
            }

            $resolved[$config['id_column']] = $subject->id;
        }

        return $resolved;
    }

    private function normalizeSubjectCode($code): ?string
    {
        $code = $this->normalizeCode($code);

        return $code === null ? null : preg_replace('/[^0-9]/', '', toEnglish($code));
    }

    private function normalizeBool($value): int
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'y'], true) ? 1 : 0;
    }

    /**
     * Abort the import with a validation error reported against the file field.
     *
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
