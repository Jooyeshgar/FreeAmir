<?php

namespace App\Services;

use App\Enums\FiscalYearSection;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Config;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Subject;
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
            Cookie::forget('active-company-id');
            Cookie::queue('active-company-id', $targetYearId);

            $idMappings = [
                'subjects' => [],
                'banks' => [],
                'customer_groups' => [],
                'product_groups' => [],
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
                    if (isset($importData['customers']) && ! empty($idMappings['customer_groups'])) {
                        $idMappings['customers'] = self::_importCustomers($importData['customers'], $targetYearId, $idMappings['customer_groups'], $idMappings['subjects']);
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
                Cookie::forget('active-company-id');
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
        }
        if (in_array('products', $sections)) {
            $sourceData['product_groups'] = ProductGroup::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['products'] = Product::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('documents', $sections)) {
            $sourceData['documents'] = \App\Models\Document::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $documentIds = collect($sourceData['documents'])->pluck('id')->toArray();
            if (! empty($documentIds)) {
                $sourceData['transactions'] = \App\Models\Transaction::whereIn('document_id', $documentIds)
                    ->get()->toArray();
            } else {
                $sourceData['transactions'] = [];
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
        foreach ($customersData as $customerData) {
            $oldGroupId = $customerData['group_id'] ?? null;
            if ($oldGroupId === null || ! isset($groupMapping[$oldGroupId])) {
                Log::warning('Skipping customer import due to missing group mapping.', ['old_customer_id' => $customerData['id'] ?? 'N/A', 'old_group_id' => $oldGroupId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $oldSubjectId = $customerData['subject_id'] ?? null;
            if ($oldSubjectId === null || ! isset($subjectMapping[$oldSubjectId])) {
                Log::warning('Skipping customer import due to missing subject mapping.', ['old_customer_id' => $customerData['id'] ?? 'N/A', 'old_subject_id' => $oldSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newCustomer = new Customer;
            $newCustomer->fill(collect($customerData)->except(['id', 'group_id', 'subject_id', 'company_id', 'introducer_id'])->toArray());
            $newCustomer->company_id = $targetYearId;
            $newCustomer->group_id = $groupMapping[$oldGroupId];
            $newCustomer->subject_id = $subjectMapping[$oldSubjectId];
            $newCustomer->saveQuietly();
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
        foreach ($groupsData as $groupData) {
            $newGroup = new ProductGroup;
            $newGroup->fill(collect($groupData)->except(['id'])->toArray());
            $newGroup->company_id = $targetYearId;

            $oldBuyId = $groupData['buyId'] ?? null;
            $newGroup->buyId = ($oldBuyId && isset($subjectMapping[$oldBuyId])) ? $subjectMapping[$oldBuyId] : null;

            $oldSellId = $groupData['sellId'] ?? null;
            $newGroup->sellId = ($oldSellId && isset($subjectMapping[$oldSellId])) ? $subjectMapping[$oldSellId] : null;

            $newGroup->save();
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
        foreach ($productsData as $productData) {
            // Assuming 'group' column holds the group ID in Product model
            $oldGroupId = $productData['group'] ?? null;
            if ($oldGroupId === null || ! isset($groupMapping[$oldGroupId])) {
                Log::warning('Skipping product import due to missing group mapping.', ['old_product_id' => $productData['id'] ?? 'N/A', 'old_group_id' => $oldGroupId, 'target_year_id' => $targetYearId]);

                continue; // Skip if the corresponding group wasn't imported or mapped
            }

            $newProduct = new Product;
            $newProduct->fill(collect($productData)->except(['id'])->toArray());
            $newProduct->group = $groupMapping[$oldGroupId]; // Use the new group ID
            $newProduct->company_id = $targetYearId;
            $newProduct->save();
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
            $newDoc = new \App\Models\Document;
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

            $newTrans = new \App\Models\Transaction;
            $newTrans->fill(collect($transData)->except(['id'])->toArray());
            $newTrans->document_id = $documentMapping[$oldDocId];
            $newTrans->subject_id = $subjectMapping[$oldSubjectId];
            $newTrans->user_id = $oldUserId; // Keep original user ID

            $newTrans->save();
        }
    }
}
