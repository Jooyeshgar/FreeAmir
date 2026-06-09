<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        'sstid',
        'location',
        'quantity',
        'quantity_warning',
        'oversell',
        'selling_price',
        'discount_formula',
        'description',
        'vat',
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

                // 3. Match an existing product by code, then upsert accordingly.
                $existing = $code !== null
                    ? Product::where('code', $code)->first()
                    : null;

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
            $key = strtolower(trim((string) $label));
            if (in_array($key, self::COLUMNS, true)) {
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
