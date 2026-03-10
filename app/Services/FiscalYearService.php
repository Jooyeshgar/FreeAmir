<?php

namespace App\Services;

use App\Enums\FiscalYearSection;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Config;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductWebsite;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Cookie;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FiscalYearService
{
    /**
     * Get available sections for copying/exporting with translations.
     *
     * @return array<string, string>
     */
    public static function getAvailableSections(): array
    {
        return FiscalYearSection::cli();
    }

    public static function getUiSections(): array
    {
        return FiscalYearSection::ui();
    }

    /**
     * Filter an array of section keys to include only valid ones.
     */
    protected static function filterValidSections(array $sections): array
    {
        return array_filter($sections, function ($section) {
            return FiscalYearSection::tryFrom($section) !== null;
        });
    }

    /**
     * Create a new fiscal year with data copied directly from an existing one in the database.
     *
     * @param  array  $newFiscalYearData  Data for the new Company record.
     * @param  int  $sourceYearId  The source fiscal year (Company) ID.
     * @param  array  $sectionsToCopy  Sections to copy from source.
     *
     * @throws Exception
     */
    public static function createWithCopiedData(array $newFiscalYearData, int $sourceYearId, array $sectionsToCopy): Company
    {
        $sectionsToCopy = self::filterValidSections($sectionsToCopy);

        $sourceData = self::fetchSourceData($sourceYearId, $sectionsToCopy);

        return self::importData($sourceData, $newFiscalYearData);
    }

    /**
     * Export data from a specific fiscal year.
     *
     * @param  int  $sourceYearId  The fiscal year (Company) ID to export.
     * @param  array|null  $sectionsToExport  Specific sections to export (null for all available).
     * @return array Data structure ready for JSON encoding.
     */
    public static function exportData(int $sourceYearId, ?array $sectionsToExport = null): array
    {
        if ($sectionsToExport === null) {
            $sectionsToExport = array_keys(self::getAvailableSections());
        } else {
            $sectionsToExport = self::filterValidSections($sectionsToExport);
        }

        $exportData = self::fetchSourceData($sourceYearId, $sectionsToExport);

        $sourceCompany = Company::find($sourceYearId);
        $exportData['meta'] = [
            'source_company_id' => $sourceYearId,
            'source_company_name' => $sourceCompany?->name,
            'exported_at' => now()->toIso8601String(),
            'sections_exported' => $sectionsToExport,
        ];

        return $exportData;
    }

    /**
     * Import data from an array (e.g., decoded JSON) into a new fiscal year.
     *
     * @param  array  $importData  Data structure (from exportData or similar).
     * @param  array  $newFiscalYearData  Data for the new Company record.
     * @return Company The newly created Company (Fiscal Year).
     *
     * @throws Exception
     */
    public static function importData(array $importData, array $newFiscalYearData): Company
    {
        $sectionsToImport = array_intersect(
            array_keys(self::getAvailableSections()),
            array_keys($importData)
        );

        return DB::transaction(function () use ($importData, $newFiscalYearData, $sectionsToImport) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            Model::unguard();

            $newFiscalYear = Company::create($newFiscalYearData);
            $targetYearId = $newFiscalYear->id;

            $originalCompanyId = getActiveCompany();
            Cookie::expire('active-company-id');
            Cookie::queue('active-company-id', $targetYearId);

            $idMappings = [
                'subjects' => [],
                'banks' => [],
                'customer_groups' => [],
                'product_groups' => [],
                'service_groups' => [],
                'documents' => [],
            ];

            try {
                // --- Import Process (Order Matters!) ---
                if (in_array('subjects', $sectionsToImport) && isset($importData['subjects'])) {
                    $idMappings['subjects'] = self::_importSubjects($importData['subjects'], $targetYearId);
                }
                if (in_array('configs', $sectionsToImport) && isset($importData['configs'])) {
                    self::_importConfigs($importData['configs'], $targetYearId, $idMappings['subjects']);
                }
                if (in_array('banks', $sectionsToImport)) {
                    if (isset($importData['banks'])) {
                        $idMappings['banks'] = self::_importBanks($importData['banks'], $targetYearId);
                    }
                    if (isset($importData['bank_accounts']) && ! empty($idMappings['banks'])) {
                        self::_importBankAccounts($importData['bank_accounts'], $targetYearId, $idMappings['banks']);
                    }
                }
                if (in_array('customers', $sectionsToImport)) {
                    if (isset($importData['customer_groups'])) {
                        $idMappings['customer_groups'] = self::_importCustomerGroups($importData['customer_groups'], $targetYearId, $idMappings['subjects']);
                    }
                    if (isset($importData['customers'])) {
                        self::_importCustomers($importData['customers'], $targetYearId, $idMappings['customer_groups'], $idMappings['subjects']);
                    }
                }
                if (in_array('products', $sectionsToImport)) {
                    if (isset($importData['product_groups'])) {
                        $idMappings['product_groups'] = self::_importProductGroups($importData['product_groups'], $targetYearId, $idMappings['subjects']);
                    }
                    if (isset($importData['products']) && ! empty($idMappings['product_groups'])) {
                        self::_importProducts($importData['products'], $targetYearId, $idMappings['product_groups']);
                    }
                }
                if (in_array('services', $sectionsToImport)) {
                    if (isset($importData['service_groups'])) {
                        $idMappings['service_groups'] = self::_importServiceGroups($importData['service_groups'], $targetYearId, $idMappings['subjects']);
                    }
                    if (isset($importData['services']) && ! empty($idMappings['service_groups'])) {
                        self::_importServices($importData['services'], $targetYearId, $idMappings['service_groups'], $idMappings['subjects']);
                    }
                }
                if (in_array('documents', $sectionsToImport)) {
                    if (isset($importData['documents'])) {
                        $idMappings['documents'] = self::_importDocuments($importData['documents'], $targetYearId);
                    }
                    if (isset($importData['transactions'])) {
                        if (! empty($idMappings['documents']) && ! empty($idMappings['subjects'])) {
                            self::_importTransactions($importData['transactions'], $targetYearId, $idMappings['documents'], $idMappings['subjects']);
                        } else {
                            Log::warning('Skipping transactions import due to missing document or subject mappings.', [
                                'target_year_id' => $targetYearId,
                                'has_document_mapping' => ! empty($idMappings['documents']),
                                'has_subject_mapping' => ! empty($idMappings['subjects']),
                            ]);
                        }
                    }
                }

                return $newFiscalYear;
            } catch (Exception $e) {
                Log::error('Fiscal Year Import Failed: '.$e->getMessage(), [
                    'exception' => $e,
                    'new_fiscal_year_data' => $newFiscalYearData,
                    'import_data_keys' => array_keys($importData),
                ]);
                throw $e;
            } finally {
                Cookie::expire('active-company-id');
                Cookie::queue('active-company-id', $originalCompanyId);
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                Model::reguard();
            }
        });
    }

    /**
     * Fetch data for specified sections from the source fiscal year.
     *
     * @return array<string, array>
     */
    protected static function fetchSourceData(int $sourceYearId, array $sections): array
    {
        $sourceData = [];

        if (in_array('subjects', $sections)) {
            $sourceData['subjects'] = Subject::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->orderBy('parent_id') // Ensure parents likely come before children
                ->get()->toArray();
        }
        if (in_array('configs', $sections)) {
            $sourceData['configs'] = Config::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('banks', $sections)) {
            $sourceData['banks'] = Bank::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['bank_accounts'] = BankAccount::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('customers', $sections)) {
            $sourceData['customer_groups'] = CustomerGroup::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['customers'] = Customer::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['comments'] = Comment::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->whereIn('customer_id', collect($sourceData['customers'])->pluck('id')->toArray())
                ->get()->toArray();
        }
        if (in_array('products', $sections)) {
            $sourceData['product_groups'] = ProductGroup::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['products'] = Product::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['product_websites'] = ProductWebsite::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->whereIn('product_id', collect($sourceData['products'])->pluck('id')->toArray())
                ->get()->toArray();
        }
        if (in_array('services', $sections)) {
            $sourceData['service_groups'] = ServiceGroup::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['services'] = Service::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('documents', $sections)) {
            $sourceData['documents'] = Document::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $documentIds = collect($sourceData['documents'])->pluck('id')->toArray();
            if (! empty($documentIds)) {
                $sourceData['transactions'] = Transaction::whereIn('document_id', $documentIds)
                    ->get()->toArray();

                $sourceData['document_files'] = DocumentFile::whereIn('document_id', $documentIds)
                    ->get()->toArray();
            } else {
                $sourceData['transactions'] = [];
                $sourceData['document_files'] = [];
            }
        }

        return $sourceData;
    }

    /**
     * Import Subjects, handling parent-child relationships and returning ID mapping.
     *
     * @param  array  $subjectsData  Array of subject data from import.
     * @return array<int, int> Mapping of old subject ID to new subject ID.
     */
    protected static function _importSubjects(array $subjectsData, int $targetYearId): array
    {
        $mapping = [];
        $subjectsByOldParentId = collect($subjectsData)->groupBy('parent_id');

        $rootSubjects = $subjectsByOldParentId->get(null, collect())->merge($subjectsByOldParentId->get(0, collect()));
        foreach ($rootSubjects as $subjectData) {
            $newSubject = self::_createSubject($subjectData, $targetYearId, $mapping);
            $mapping[$subjectData['id']] = $newSubject->id;
            self::_processSubjectChildren($subjectData['id'], $subjectsByOldParentId, $targetYearId, $mapping);
        }

        return $mapping;
    }

    protected static function _createSubject(array $subjectData, int $targetYearId, array &$mapping): Subject
    {
        $newSubject = new Subject;
        $newSubject->fill(collect($subjectData)->except(['id', 'parent_id', 'company_id', '_lft', '_rgt'])->toArray());
        $newSubject->company_id = $targetYearId;
        $newSubject->parent_id = ($subjectData['parent_id'] == 0) ? null : $mapping[$subjectData['parent_id']];
        $newSubject->save();

        return $newSubject;
    }

    protected static function _processSubjectChildren($oldParentId, $subjectsByOldParentId, $targetYearId, array &$mapping): void
    {
        $children = $subjectsByOldParentId->get($oldParentId, collect());
        foreach ($children as $childData) {
            if (! isset($mapping[$childData['id']])) {
                $newSubject = self::_createSubject($childData, $targetYearId, $mapping);
                $mapping[$childData['id']] = $newSubject->id;
                if ($subjectsByOldParentId->has($childData['id'])) {
                    self::_processSubjectChildren($childData['id'], $subjectsByOldParentId, $targetYearId, $mapping);
                }
            }
        }
    }

    /**
     * Import Configs.
     *
     * @param  array  $subjectMapping  Mapping of old subject ID to new subject ID.
     */
    protected static function _importConfigs(array $configsData, int $targetYearId, array $subjectMapping): void
    {
        foreach ($configsData as $configData) {
            $newConfig = new Config;
            $newConfig->fill(collect($configData)->except(['id'])->toArray());
            $newConfig->company_id = $targetYearId;

            $oldValue = $configData['value'] ?? null;
            if ($oldValue !== null && isset($subjectMapping[$oldValue])) {
                $newConfig->value = $subjectMapping[$oldValue];
            } else {
                $newConfig->value = $oldValue; // Keep original value otherwise
            }

            config(['amir.'.$newConfig->key => $newConfig->value]);

            $newConfig->save();
        }
    }

    /**
     * Import Banks.
     *
     * @return array<int, int> Mapping of old bank ID to new bank ID.
     */
    protected static function _importBanks(array $banksData, int $targetYearId): array
    {
        $mapping = [];
        foreach ($banksData as $bankData) {
            $newBank = new Bank;
            $newBank->fill(collect($bankData)->except(['id'])->toArray());
            $newBank->company_id = $targetYearId;
            $newBank->save();
            $mapping[$bankData['id']] = $newBank->id;
        }

        return $mapping;
    }

    /**
     * Import Bank Accounts.
     *
     * @param  array  $bankMapping  Mapping of old bank ID to new bank ID.
     */
    protected static function _importBankAccounts(array $bankAccountsData, int $targetYearId, array $bankMapping): void
    {
        foreach ($bankAccountsData as $accountData) {
            $oldBankId = $accountData['bank_id'] ?? null;
            if ($oldBankId === null || ! isset($bankMapping[$oldBankId])) {
                Log::warning('Skipping bank account import due to missing bank mapping.', ['old_bank_account_id' => $accountData['id'] ?? 'N/A', 'old_bank_id' => $oldBankId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newAccount = new BankAccount;
            $newAccount->fill(collect($accountData)->except(['id'])->toArray());
            $newAccount->bank_id = $bankMapping[$oldBankId];
            $newAccount->company_id = $targetYearId;
            $newAccount->saveQuietly();
        }
    }

    /**
     * Import Customer Groups.
     *
     * @param  array  $subjectMapping  Mapping of old subject ID to new subject ID.
     * @return array<int, int> Mapping of old group ID to new group ID.
     */
    protected static function _importCustomerGroups(array $groupsData, int $targetYearId, array $subjectMapping): array
    {
        $mapping = [];
        foreach ($groupsData as $groupData) {
            $newGroup = new CustomerGroup;
            $newGroup->fill(collect($groupData)->except(['id'])->toArray());
            $newGroup->company_id = $targetYearId;

            $oldSubjectId = $groupData['subject_id'] ?? null;
            $newGroup->subject_id = ($oldSubjectId && isset($subjectMapping[$oldSubjectId])) ? $subjectMapping[$oldSubjectId] : null;

            $newGroup->save();
            echo '-----------------'.config('amir.cust_subject')."\n";
            $mapping[$groupData['id']] = $newGroup->id;
        }

        return $mapping;
    }

    /**
     * Import Customers.
     *
     * @param  array  $groupMapping  Mapping of old customer group ID to new group ID.
     */
    protected static function _importCustomers(array $customersData, int $targetYearId, array $groupMapping, array $subjectMapping): void
    {
        $subjectService = new SubjectService;
        $mappedGroupIds = array_values($groupMapping);
        $groupSubjectMap = empty($mappedGroupIds) ? [] : CustomerGroup::withoutGlobalScope('App\\Models\\Scopes\\FiscalYearScope')
            ->where('company_id', $targetYearId)->whereIn('id', $mappedGroupIds)
            ->pluck('subject_id', 'id')->toArray();

        foreach ($customersData as $customerData) {
            $oldGroupId = $customerData['group_id'] ?? null;
            if ($oldGroupId !== null && ! isset($groupMapping[$oldGroupId])) {
                Log::warning('Skipping customer import due to missing group mapping.', ['old_customer_id' => $customerData['id'] ?? 'N/A', 'old_group_id' => $oldGroupId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newCustomer = new Customer;
            $newCustomer->fill(collect($customerData)->except(['id', 'group_id', 'subject_id', 'company_id', 'introducer_id'])->toArray());
            $newCustomer->company_id = $targetYearId;
            $newCustomer->group_id = $oldGroupId !== null ? ($groupMapping[$oldGroupId] ?? null) : null;
            $newCustomer->saveQuietly();

            $parentSubjectId = $newCustomer->group_id ? ($groupSubjectMap[$newCustomer->group_id] ?? null) : null;
            $oldSubjectId = $customerData['subject_id'] ?? null;

            $subject = null;
            if ($oldSubjectId && isset($subjectMapping[$oldSubjectId])) {
                $subject = Subject::withoutGlobalScope('App\\Models\\Scopes\\FiscalYearScope')
                    ->where('company_id', $targetYearId)
                    ->find($subjectMapping[$oldSubjectId]);
            }

            if (! $subject) {
                $subject = $subjectService->createSubject([
                    'name' => $newCustomer->name,
                    'parent_id' => $parentSubjectId,
                    'company_id' => $targetYearId,
                ]);
            } else {
                $subject->name = $newCustomer->name;
                $subject->parent_id = $parentSubjectId;
            }

            $subject->subjectable()->associate($newCustomer);
            $subject->save();
        }
    }

    /**
     * Import Product Groups.
     *
     * @param  array  $subjectMapping  Mapping of old subject ID to new subject ID.
     * @return array<int, int> Mapping of old group ID to new group ID.
     */
    protected static function _importProductGroups(array $groupsData, int $targetYearId, array $subjectMapping): array
    {
        $mapping = [];
        $subjectService = new SubjectService;

        foreach ($groupsData as $groupData) {
            $newGroup = new ProductGroup;
            $newGroup->fill(collect($groupData)->except([
                'id',
                'income_subject_id',
                'sales_returns_subject_id',
                'cogs_subject_id',
                'inventory_subject_id',
                'buyId',
                'sellId',
            ])->toArray());
            $newGroup->company_id = $targetYearId;
            $newGroup->save();

            $subjectColumns = [
                'income_subject_id' => [
                    'old_value' => $groupData['income_subject_id'] ?? $groupData['sellId'] ?? null,
                    'parent_id' => config('amir.sales_revenue'),
                ],
                'sales_returns_subject_id' => [
                    'old_value' => $groupData['sales_returns_subject_id'] ?? null,
                    'parent_id' => config('amir.sales_returns'),
                ],
                'cogs_subject_id' => [
                    'old_value' => $groupData['cogs_subject_id'] ?? $groupData['buyId'] ?? null,
                    'parent_id' => config('amir.cost_of_goods_sold'),
                ],
                'inventory_subject_id' => [
                    'old_value' => $groupData['inventory_subject_id'] ?? null,
                    'parent_id' => config('amir.inventory'),
                ],
            ];

            foreach ($subjectColumns as $column => $config) {
                $oldSubjectId = $config['old_value'];
                $parentSubjectId = $config['parent_id'] ?: null;

                $subject = null;
                if ($oldSubjectId && isset($subjectMapping[$oldSubjectId])) {
                    $subject = Subject::withoutGlobalScope('App\\Models\\Scopes\\FiscalYearScope')
                        ->where('company_id', $targetYearId)
                        ->find($subjectMapping[$oldSubjectId]);
                }

                if (! $subject) {
                    $subject = $subjectService->createSubject([
                        'name' => $newGroup->name,
                        'parent_id' => $parentSubjectId,
                        'company_id' => $targetYearId,
                    ]);
                } else {
                    $subject->name = $newGroup->name;
                    $subject->parent_id = $parentSubjectId;
                }

                $subject->subjectable()->associate($newGroup);
                $subject->save();

                $newGroup->{$column} = $subject->id;
            }

            $newGroup->saveQuietly();
            $mapping[$groupData['id']] = $newGroup->id;
        }

        return $mapping;
    }

    /**
     * Import Products.
     *
     * @param  array  $groupMapping  Mapping of old product group ID to new group ID.
     */
    protected static function _importProducts(array $productsData, int $targetYearId, array $groupMapping): void
    {
        $subjectService = new SubjectService;
        $mappedGroupIds = array_values($groupMapping);
        $groupsById = empty($mappedGroupIds) ? collect() : ProductGroup::withoutGlobalScope('App\\Models\\Scopes\\FiscalYearScope')
            ->where('company_id', $targetYearId)
            ->whereIn('id', $mappedGroupIds)
            ->get(['id', 'income_subject_id', 'sales_returns_subject_id', 'cogs_subject_id', 'inventory_subject_id'])
            ->keyBy('id');

        foreach ($productsData as $productData) {
            // Assuming 'group' column holds the group ID in Product model
            $oldGroupId = $productData['group'] ?? null;
            if ($oldGroupId === null || ! isset($groupMapping[$oldGroupId])) {
                Log::warning('Skipping product import due to missing group mapping.', ['old_product_id' => $productData['id'] ?? 'N/A', 'old_group_id' => $oldGroupId, 'target_year_id' => $targetYearId]);

                continue; // Skip if the corresponding group wasn't imported or mapped
            }

            $newProduct = new Product;
            $newProduct->fill(collect($productData)->except([
                'id',
                'income_subject_id',
                'sales_returns_subject_id',
                'cogs_subject_id',
                'inventory_subject_id',
                'subject_id',
            ])->toArray());
            $newProduct->group = $groupMapping[$oldGroupId]; // Use the new group ID
            $newProduct->company_id = $targetYearId;
            $newProduct->save();

            $group = $groupsById->get($newProduct->group);
            $subjectColumns = [
                'income_subject_id' => [
                    'old_value' => $productData['income_subject_id'] ?? $productData['subject_id'] ?? null,
                    'parent_id' => $group?->income_subject_id,
                ],
                'sales_returns_subject_id' => [
                    'old_value' => $productData['sales_returns_subject_id'] ?? null,
                    'parent_id' => $group?->sales_returns_subject_id,
                ],
                'cogs_subject_id' => [
                    'old_value' => $productData['cogs_subject_id'] ?? null,
                    'parent_id' => $group?->cogs_subject_id,
                ],
                'inventory_subject_id' => [
                    'old_value' => $productData['inventory_subject_id'] ?? null,
                    'parent_id' => $group?->inventory_subject_id,
                ],
            ];

            foreach ($subjectColumns as $column => $config) {
                $parentSubjectId = $config['parent_id'] ?: null;

                $subject = $subjectService->createSubject([
                    'name' => $newProduct->name,
                    'parent_id' => $parentSubjectId,
                    'company_id' => $targetYearId,
                ]);

                $subject->subjectable()->associate($newProduct);
                $subject->save();

                $newProduct->{$column} = $subject->id;
            }

            $newProduct->saveQuietly();
        }
    }

    /**
     * Import Service Groups.
     *
     * @param  array  $subjectMapping  Mapping of old subject ID to new subject ID.
     * @return array<int, int> Mapping of old group ID to new group ID.
     */
    protected static function _importServiceGroups(array $groupsData, int $targetYearId, array $subjectMapping): array
    {
        $mapping = [];
        $subjectService = new SubjectService;

        foreach ($groupsData as $groupData) {
            $newGroup = new ServiceGroup;
            $newGroup->fill(collect($groupData)->except([
                'id',
                'subject_id',
                'cogs_subject_id',
                'sales_returns_subject_id',
            ])->toArray());
            $newGroup->company_id = $targetYearId;
            $newGroup->save();

            $subjectColumns = [
                'subject_id' => [
                    'old_value' => $groupData['subject_id'] ?? null,
                    'parent_id' => config('amir.service_revenue'),
                ],
                'cogs_subject_id' => [
                    'old_value' => $groupData['cogs_subject_id'] ?? null,
                    'parent_id' => config('amir.cogs_service'),
                ],
                'sales_returns_subject_id' => [
                    'old_value' => $groupData['sales_returns_subject_id'] ?? null,
                    'parent_id' => config('amir.sales_returns'),
                ],
            ];

            foreach ($subjectColumns as $column => $config) {
                $oldSubjectId = $config['old_value'];
                $parentSubjectId = $config['parent_id'] ?: null;

                $subject = null;
                if ($oldSubjectId && isset($subjectMapping[$oldSubjectId])) {
                    $subject = Subject::withoutGlobalScope('App\\Models\\Scopes\\FiscalYearScope')
                        ->where('company_id', $targetYearId)
                        ->find($subjectMapping[$oldSubjectId]);
                }

                if (! $subject) {
                    $subject = $subjectService->createSubject([
                        'name' => $newGroup->name,
                        'parent_id' => $parentSubjectId,
                        'company_id' => $targetYearId,
                    ]);
                } else {
                    $subject->name = $newGroup->name;
                    $subject->parent_id = $parentSubjectId;
                }

                $subject->subjectable()->associate($newGroup);
                $subject->save();

                $newGroup->{$column} = $subject->id;
            }

            $newGroup->saveQuietly();
            $mapping[$groupData['id']] = $newGroup->id;
        }

        return $mapping;
    }

    /**
     * Import Services.
     *
     * @param  array  $groupMapping  Mapping of old service group ID to new group ID.
     * @param  array  $subjectMapping  Mapping of old subject ID to new subject ID.
     */
    protected static function _importServices(array $servicesData, int $targetYearId, array $groupMapping, array $subjectMapping): void
    {
        $subjectService = new SubjectService;
        $mappedGroupIds = array_values($groupMapping);
        $groupsById = empty($mappedGroupIds) ? collect() : ServiceGroup::withoutGlobalScope('App\\Models\\Scopes\\FiscalYearScope')
            ->where('company_id', $targetYearId)
            ->whereIn('id', $mappedGroupIds)
            ->get(['id', 'subject_id', 'cogs_subject_id', 'sales_returns_subject_id'])
            ->keyBy('id');

        foreach ($servicesData as $serviceData) {
            $oldGroupId = $serviceData['group'] ?? null;
            if ($oldGroupId === null || ! isset($groupMapping[$oldGroupId])) {
                Log::warning('Skipping service import due to missing group mapping.', ['old_service_id' => $serviceData['id'] ?? 'N/A', 'old_group_id' => $oldGroupId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newService = new Service;
            $newService->fill(collect($serviceData)->except(['id', 'group', 'subject_id', 'cogs_subject_id', 'sales_returns_subject_id', 'company_id'])->toArray());
            $newService->company_id = $targetYearId;
            $newService->group = $groupMapping[$oldGroupId];
            $newService->save();

            $group = $groupsById->get($newService->group);
            $subjectColumns = [
                'subject_id' => [
                    'old_value' => $serviceData['subject_id'] ?? null,
                    'parent_id' => $group?->subject_id,
                ],
                'cogs_subject_id' => [
                    'old_value' => $serviceData['cogs_subject_id'] ?? null,
                    'parent_id' => $group?->cogs_subject_id,
                ],
                'sales_returns_subject_id' => [
                    'old_value' => $serviceData['sales_returns_subject_id'] ?? null,
                    'parent_id' => $group?->sales_returns_subject_id,
                ],
            ];

            foreach ($subjectColumns as $column => $config) {
                $oldSubjectId = $config['old_value'];
                $parentSubjectId = $config['parent_id'] ?: null;

                $subject = null;
                if ($oldSubjectId && isset($subjectMapping[$oldSubjectId])) {
                    $subject = Subject::withoutGlobalScope('App\\Models\\Scopes\\FiscalYearScope')
                        ->where('company_id', $targetYearId)
                        ->find($subjectMapping[$oldSubjectId]);

                    if ($subject && $subject->subjectable_id && ($subject->subjectable_id !== $newService->id || $subject->subjectable_type !== $newService->getMorphClass())) {
                        $subject = null;
                    }
                }

                if (! $subject) {
                    $subject = $subjectService->createSubject([
                        'name' => $newService->name,
                        'parent_id' => $parentSubjectId,
                        'company_id' => $targetYearId,
                    ]);
                } else {
                    $subject->name = $newService->name;
                    $subject->parent_id = $parentSubjectId;
                }

                $subject->subjectable()->associate($newService);
                $subject->save();

                $newService->{$column} = $subject->id;
            }

            $newService->saveQuietly();
        }
    }

    /**
     * Import Documents.
     *
     * @return array<int, int> Mapping of old document ID to new document ID.
     */
    protected static function _importDocuments(array $documentsData, int $targetYearId): array
    {
        $mapping = [];
        foreach ($documentsData as $docData) {
            $newDoc = new Document;
            $newDoc->fill(collect($docData)->except(['id'])->toArray());
            $newDoc->company_id = $targetYearId;
            $newDoc->save();
            $mapping[$docData['id']] = $newDoc->id;
        }

        return $mapping;
    }

    /**
     * Import Transactions.
     *
     * @param  int  $targetYearId  The target company ID (though Transaction doesn't have it directly)
     * @param  array  $documentMapping  Mapping of old document ID to new document ID.
     * @param  array  $subjectMapping  Mapping of old subject ID to new subject ID.
     */
    protected static function _importTransactions(array $transactionsData, int $targetYearId, array $documentMapping, array $subjectMapping): void
    {
        foreach ($transactionsData as $transData) {
            $oldDocId = $transData['document_id'] ?? null;
            $oldSubjectId = $transData['subject_id'] ?? null;
            $oldUserId = $transData['user_id'] ?? null; // Assumed global

            if ($oldDocId === null || ! isset($documentMapping[$oldDocId])) {
                Log::warning('Skipping transaction import due to missing document mapping.', ['old_transaction_id' => $transData['id'] ?? 'N/A', 'old_document_id' => $oldDocId, 'target_year_id' => $targetYearId]);

                continue;
            }
            if ($oldSubjectId === null || ! isset($subjectMapping[$oldSubjectId])) {
                Log::warning('Skipping transaction import due to missing subject mapping.', ['old_transaction_id' => $transData['id'] ?? 'N/A', 'old_subject_id' => $oldSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newTrans = new Transaction;
            $newTrans->fill(collect($transData)->except(['id'])->toArray());
            $newTrans->document_id = $documentMapping[$oldDocId];
            $newTrans->subject_id = $subjectMapping[$oldSubjectId];
            $newTrans->user_id = $oldUserId; // Keep original user ID

            $newTrans->save();
        }
    }

    protected static function validateClosingFiscalYear(Company $company): array
    {
        $errors = [];

        $isCloseYear = $company->closed_at !== null;
        if ($isCloseYear) {
            $errors[] = __('Cannot close fiscal year because the year is not open.');
        }

        $draftDocsCount = Document::where('company_id', $company->id)->whereNull('approved_at')->count();
        if ($draftDocsCount > 0) {
            $errors[] = __('Cannot close fiscal year with draft documents. Please approve or delete all draft documents before closing the year.');
        }

        $unbalancedDocsCount = Document::where('company_id', $company->id)->has('transactions')
            ->withSum('transactions', 'value')->having('transactions_sum_value', '!=', 0)->count();

        if ($unbalancedDocsCount > 0) {
            $errors[] = __('Cannot close fiscal year with unbalanced documents. Please ensure all documents are balanced before closing the year.');
        }

        return $errors;
    }

    protected static function balanceCurrentProfitAndLoss(Company $company, Document $document, User $user): void
    {
        $difference = (float) $document->transactions()->sum('value');

        if ($difference !== 0.0) {
            $subject = Subject::where('company_id', $company->id)
                ->where('name', __('Current Profit and Loss Summary'))->first();

            if (! $subject) {
                $subjectService = new SubjectService;
                $subject = $subjectService->createSubject([
                    'name' => __('Current Profit and Loss Summary'),
                    'company_id' => $company->id,
                ]);
            }

            $document->transactions()->create([
                'subject_id' => $subject->id, // For balancing the opening account
                'value' => -1 * $difference,
                'user_id' => $user->id,
            ]);
        }
    }

    protected static function createOpeningDocument(Company $company, Document $closeDocument, User $user): void
    {
        $documentData = [
            'number' => 1,
            'date' => now(),
            'title' => __('Fiscal year opening Document'),
            'creator_id' => $user->id,
            'company_id' => $company->id,
            'approved_at' => now(),
            'approver_id' => $user->id,
        ];

        $sourceSubjectIds = $closeDocument->transactions()->pluck('subject_id')->filter()->unique()->values();

        $sourceSubjectsById = Subject::withoutGlobalScope('App\\Models\\Scopes\\FiscalYearScope')
            ->where('company_id', $closeDocument->company_id)
            ->whereIn('id', $sourceSubjectIds)
            ->get(['id', 'code'])
            ->keyBy('id');

        $targetSubjectsByCode = Subject::withoutGlobalScope('App\\Models\\Scopes\\FiscalYearScope')
            ->where('company_id', $company->id)
            ->whereIn('code', $sourceSubjectsById->pluck('code')->filter()->unique()->values())
            ->pluck('id', 'code')
            ->toArray();

        $subjectMapping = [];
        foreach ($sourceSubjectsById as $oldSubjectId => $sourceSubject) {
            $code = $sourceSubject->code;
            if ($code !== null && isset($targetSubjectsByCode[$code])) {
                $subjectMapping[$oldSubjectId] = $targetSubjectsByCode[$code];
            }
        }

        // Reverse the transactions of closing document to create opening document for the new fiscal year
        $transactions = $closeDocument->transactions()->get()->map(function ($transaction) use ($user, $subjectMapping, $company) {
            return [
                'subject_id' => $subjectMapping[$transaction->subject_id] ?? null,
                'value' => -1 * $transaction->value,
                'user_id' => $user->id,
                'desc' => __('Fiscal year opening Document').' '.$company->fiscal_year,
            ];
        })->filter(fn ($transaction) => $transaction['subject_id'] !== null)->values()->toArray();

        if (empty($transactions)) {
            Log::warning('Skipping opening document creation due to missing subject mappings.', [
                'source_company_id' => $closeDocument->company_id,
                'target_company_id' => $company->id,
                'close_document_id' => $closeDocument->id,
            ]);

            return;
        }

        DocumentService::createDocument($user, $documentData, $transactions);
    }

    protected static function createClosingDocument(Company $company, Document $currentPL, Document $accumulatedPL, User $user): Document
    {
        $documentData = [
            'number' => Document::where('company_id', $company->id)->max('number') + 1,
            'date' => now(),
            'title' => __('Fiscal year closing Document'),
            'creator_id' => $user->id,
            'company_id' => $company->id,
            'approved_at' => now(),
            'approver_id' => $user->id,
        ];

        // Permanent and temporary accounts for creating closing document
        $transactions = Transaction::query()->whereIn('document_id', [$currentPL->id, $accumulatedPL->id])
            ->selectRaw('subject_id, SUM(value) as value, ? as user_id', [$user->id]) // `subject_id`, `value` and `user_id` for transactions are needed
            ->groupBy('subject_id')->get();

        $transactions = $transactions->map(fn ($transaction) => [
            'value' => -1 * $transaction->value,
            'subject_id' => $transaction->subject_id,
            'desc' => __('Fiscal year closing Document').' '.$company->fiscal_year,
        ])->toArray();

        return DocumentService::createDocument($user, $documentData, $transactions);
    }

    protected static function newFiscalYear(Company $company, User $user): Company
    {
        $newFiscalYearData = collect($company->getAttributes())->except(['id', 'closed_at', 'fiscal_year'])
            ->merge(['fiscal_year' => $company->fiscal_year + 1])->toArray();

        $sectionsToCopy = ['subjects', 'configs', 'banks', 'customers', 'products', 'services']; // Sections to copy to the new fiscal year
        $newFiscalYear = self::createWithCopiedData($newFiscalYearData, $company->id, $sectionsToCopy);
        $newFiscalYear->users()->attach($user->id);

        return $newFiscalYear;
    }

    public static function closeFiscalYear(Company $company, User $user): array
    {
        $newFiscalYear = null;
        $validationErrors = [];

        DB::transaction(function () use ($company, &$newFiscalYear, &$validationErrors, $user) {
            // $validationErrors = self::validateClosingFiscalYear($company);

            if (! empty($validationErrors)) {
                return;
            }

            $currentProfitAndLoss = self::currentProfitAndLoss($company, $user);
            $accumulatedProfitAndLoss = self::accumulatedProfitAndLoss($company, $user);

            // Move any remaining balance in current profit and loss to accumulated profit and loss to balance them
            self::balanceCurrentProfitAndLoss($company, $currentProfitAndLoss, $user);

            $closeDocument = self::createClosingDocument($company, $currentProfitAndLoss, $accumulatedProfitAndLoss, $user);

            $newFiscalYear = self::newFiscalYear($company, $user);

            self::createOpeningDocument($newFiscalYear, $closeDocument, $user);

            $company->closed_at = now();
            $company->closed_by = $user->id;
            $company->save();
        });

        return [$newFiscalYear, $validationErrors];
    }

    protected static function accumulatedProfitAndLoss(Company $company, User $user): Document
    {
        $documentData = [
            'number' => Document::where('company_id', $company->id)->max('number') + 1,
            'date' => now(),
            'title' => __('Accumulated Profit and Loss'),
            'creator_id' => $user->id,
            'company_id' => $company->id,
            'approved_at' => now(),
            'approver_id' => $user->id,
        ];

        // Permanent accounts
        $transactions = Transaction::query()
            ->whereHas('document', fn ($document) => $document->where('company_id', $company->id))
            ->whereHas('subject', fn ($subject) => $subject->where('company_id', $company->id)->where('is_permanent', true))
            ->selectRaw('subject_id, SUM(value) as value')->groupBy('subject_id')
            ->havingRaw('SUM(value) != 0') // Remove zero balances
            ->get()
            ->map(fn ($transaction) => [
                'subject_id' => $transaction->subject_id,
                'value' => $transaction->value,
                'user_id' => $user->id,
                'desc' => __('Balance for closing fiscal year'),
            ])->all();

        return DocumentService::createDocument($user, $documentData, $transactions);
    }

    protected static function currentProfitAndLoss(Company $company, User $user): Document
    {
        $documentData = [
            'number' => Document::where('company_id', $company->id)->max('number') + 1,
            'date' => now(),
            'title' => __('Current Profit and Loss Summary'),
            'creator_id' => $user->id,
            'company_id' => $company->id,
            'approved_at' => now(),
            'approver_id' => $user->id,
        ];

        // Temporary income and expense accounts
        $temporarySubjects = Subject::where('company_id', $company->id)
            ->where('is_permanent', false)->pluck('id')->toArray();

        $transactions = Transaction::query()
            ->whereHas('document', fn ($document) => $document->where('company_id', $company->id))
            ->whereIn('subject_id', $temporarySubjects)
            ->selectRaw('subject_id, SUM(value) as value')->groupBy('subject_id')
            ->havingRaw('SUM(value) != 0') // Remove zero balances
            ->get()
            ->map(fn ($transaction) => [
                'subject_id' => $transaction->subject_id,
                'value' => $transaction->value,
                'user_id' => $user->id,
                'desc' => __('Balance for closing fiscal year'),
            ])->all();

        return DocumentService::createDocument($user, $documentData, $transactions);
    }
}
