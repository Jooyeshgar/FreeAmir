<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ServiceImportService
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
        'sales_returns_subject_code',
        'sstid',
        'selling_price',
        'vat',
        'description',
    ];

    /** Service fields copied straight from the CSV row (no special handling). */
    private const PLAIN_FIELDS = [
        'sstid', 'description',
    ];

    /** Numeric service fields normalised through convertToFloat. */
    private const NUMERIC_FIELDS = [
        'selling_price', 'vat',
    ];

    public function __construct(
        private readonly ServiceService $serviceService,
        private readonly ServiceGroupService $serviceGroupService,
    ) {}

    /**
     * Import services from a CSV file. The whole import runs inside a single
     * transaction: any row-level error aborts the import and rolls back every
     * change made so far.
     *
     * Rows are matched to existing data by their service code:
     *   - code resolves to an existing service -> the service is updated;
     *   - no usable code, or no service carries that code yet -> a brand-new
     *     service is created (a null code auto-generates the next code).
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
                    $this->fail(__('Line :line: service name is required.', ['line' => $line]));
                }

                if ($groupName === '') {
                    $this->fail(__('Line :line: group name is required.', ['line' => $line]));
                }

                // 1. Resolve the group: reuse an existing one with the same name, otherwise create it.
                $group = $groupCache[$groupName] ?? null;

                if (! $group) {
                    $group = ServiceGroup::where('name', $groupName)->first();

                    if (! $group) {
                        $group = $this->serviceGroupService->create([
                            'name' => $groupName,
                            'company_id' => $companyId,
                        ]);
                        $groupsCreated++;
                    }

                    $groupCache[$groupName] = $group;
                }

                // 2. Build the base service attributes.
                $data = [
                    'name' => $name,
                    'group' => $group->id,
                    'company_id' => $companyId,
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

                // 3. Match an existing service by code, then upsert accordingly.
                $existing = $code !== null
                    ? Service::where('code', $code)->first()
                    : null;

                try {
                    if ($existing) {
                        $this->serviceService->update($existing, $data);
                        $updated++;
                    } else {
                        // The services table requires selling_price; default it
                        // when the CSV left it blank for a new service.
                        $data['selling_price'] ??= 0;
                        $data['code'] = $code ?? (Service::max('code') + 1);
                        $this->serviceService->create($data);
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
     * Normalize a service code from the CSV: strip whitespace.
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
