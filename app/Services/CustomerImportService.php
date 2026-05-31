<?php

namespace App\Services;

use App\Enums\CustomerType;
use App\Exceptions\CustomerImportException;
use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CustomerImportService
{
    /**
     * Canonical CSV columns, in export order. The header row is matched by these
     * keys (case-insensitive), so the importer is tolerant of column reordering
     * and of extra columns it does not recognise.
     */
    public const COLUMNS = [
        'name',
        'group_name',
        'subject_code',
        'type',
        'phone',
        'cell',
        'fax',
        'address',
        'postal_code',
        'email',
        'ecnmcs_code',
        'personal_code',
        'web_page',
        'responsible',
        'connector',
        'desc',
        'credit',
        'disc_rate',
        'acc_name_1',
        'acc_no_1',
        'acc_bank_1',
        'acc_name_2',
        'acc_no_2',
        'acc_bank_2',
    ];

    /** Customer fields copied straight from the CSV row (no special handling). */
    private const PLAIN_FIELDS = [
        'phone', 'cell', 'fax', 'address', 'postal_code', 'email', 'ecnmcs_code',
        'personal_code', 'web_page', 'responsible', 'connector', 'desc', 'credit',
        'disc_rate', 'acc_name_1', 'acc_no_1', 'acc_bank_1', 'acc_name_2',
        'acc_no_2', 'acc_bank_2',
    ];

    public function __construct(
        private readonly CustomerService $customerService,
        private readonly CustomerGroupService $customerGroupService,
    ) {}

    /**
     * Import customers from a CSV file. The whole import runs inside a single
     * transaction: any row-level error aborts the import and rolls back every
     * change made so far.
     *
     * @param  UploadedFile|string  $file  fresh upload or a Storage-relative path
     * @return array{imported:int, groups_created:int}
     *
     * @throws CustomerImportException
     */
    public function import(UploadedFile|string $file, int $companyId): array
    {
        $rows = $this->parse($file);

        if (empty($rows)) {
            throw new CustomerImportException(__('The import file is empty or has no data rows.'));
        }

        return DB::transaction(function () use ($rows, $companyId) {
            $imported = 0;
            $groupsCreated = 0;
            $groupCache = [];

            foreach ($rows as $index => $row) {
                // Human-friendly line number: +1 for the header row, +1 for 0-based index.
                $line = $index + 2;

                $name = trim((string) ($row['name'] ?? ''));
                $groupName = trim((string) ($row['group_name'] ?? ''));
                $subjectCode = $this->normalizeCode($row['subject_code'] ?? null);

                if ($name === '') {
                    throw new CustomerImportException(__('Line :line: customer name is required.', ['line' => $line]));
                }

                if ($groupName === '') {
                    throw new CustomerImportException(__('Line :line: group name is required.', ['line' => $line]));
                }

                // 1. Resolve the group: reuse an existing one with the same name, otherwise create it.
                $group = $groupCache[$groupName] ?? null;

                if (! $group) {
                    $group = CustomerGroup::with('subject')->where('name', $groupName)->first();

                    if (! $group) {
                        $group = $this->customerGroupService->create([
                            'name' => $groupName,
                            'company_id' => $companyId,
                        ]);
                        $groupsCreated++;
                    }

                    $groupCache[$groupName] = $group;
                }

                $group->loadMissing('subject');

                if (! $group->subject) {
                    throw new CustomerImportException(__('Line :line: could not resolve the accounting subject for group ":group".', [
                        'line' => $line,
                        'group' => $groupName,
                    ]));
                }

                // 2. Validate the customer subject code against the group subject, if a code was supplied.
                $codePortion = null;

                if ($subjectCode !== null) {
                    $expectedParent = strlen($subjectCode) > 3 ? substr($subjectCode, 0, -3) : '';

                    if ($expectedParent !== $group->subject->code) {
                        throw new CustomerImportException(__('Line :line: subject code :code is not a child of group ":group" subject :parent.', [
                            'line' => $line,
                            'code' => formatCode($subjectCode),
                            'group' => $groupName,
                            'parent' => formatCode($group->subject->code),
                        ]));
                    }

                    $codePortion = substr($subjectCode, -3);
                }

                // 3. Reject duplicate customer names within the same group.
                $duplicate = Customer::where('group_id', $group->id)
                    ->where('name', $name)
                    ->exists();

                if ($duplicate) {
                    throw new CustomerImportException(__('Line :line: a customer named ":name" already exists in group ":group".', [
                        'line' => $line,
                        'name' => $name,
                        'group' => $groupName,
                    ]));
                }

                // 4. Build and persist the customer. A null code auto-generates the next
                //    available subject code under the group (same as creating a new customer).
                $data = [
                    'name' => $name,
                    'group_id' => $group->id,
                    'company_id' => $companyId,
                    'type' => $this->normalizeType($row['type'] ?? null)->value,
                    'subject_code' => $codePortion,
                ];

                foreach (self::PLAIN_FIELDS as $field) {
                    $value = $row[$field] ?? null;
                    if ($value !== null && trim((string) $value) !== '') {
                        $data[$field] = trim((string) $value);
                    }
                }

                try {
                    $this->customerService->create($data);
                } catch (CustomerImportException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    throw new CustomerImportException(__('Line :line: :message', [
                        'line' => $line,
                        'message' => $e->getMessage(),
                    ]), 0, $e);
                }

                $imported++;
            }

            return [
                'imported' => $imported,
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

            throw new CustomerImportException(__('The import file must contain at least "name" and "group_name" columns.'));
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
     * Normalize a subject code from the CSV: strip slashes/whitespace and keep digits only.
     * Returns null when no usable code was supplied.
     */
    private function normalizeCode($code): ?string
    {
        if ($code === null) {
            return null;
        }

        $code = preg_replace('/[^0-9]/', '', (string) $code);

        return $code === '' ? null : $code;
    }

    private function normalizeType($value): CustomerType
    {
        $value = trim((string) $value);

        return CustomerType::tryFrom($value) ?? CustomerType::INDIVIDUAL;
    }
}
