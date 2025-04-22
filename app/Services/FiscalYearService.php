<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\Subject;
use App\Models\ProductGroup;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Config;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class FiscalYearService
{
    /**
     * Get available sections for copying/exporting with translations.
     *
     * @return array<string, string>
     */
    public static function getAvailableSections(): array
    {
        return [
            'configs' => __('Configs'), // Added Configs
            'banks' => __('Banks'), // Added Banks explicitly
            'bank_accounts' => __('Bank Accounts'),
            'customer_groups' => __('Customer Groups'), // Added Customer Groups explicitly
            'customers' => __('Customers'),
            'product_groups' => __('Product Groups'), // Added Product Groups explicitly
            'products' => __('Products'),
            'subjects' => __('Subjects'), // Added Subjects explicitly
        ];
    }

    /**
     * Create a new fiscal year with data copied directly from an existing one in the database.
     *
     * @param array $newFiscalYearData Data for the new Company record.
     * @param int $sourceYearId The source fiscal year (Company) ID.
     * @param array $sectionsToCopy Sections to copy from source.
     * @return Company
     * @throws Exception
     */
    public static function createWithCopiedData(array $newFiscalYearData, int $sourceYearId, array $sectionsToCopy): Company
    {
        // Filter sections to ensure only valid ones are processed
        $sectionsToCopy = self::filterValidSections($sectionsToCopy);

        // Fetch source data directly from the database
        $sourceData = self::fetchSourceData($sourceYearId, $sectionsToCopy);

        // Use the common import logic
        return self::importData($sourceData, $newFiscalYearData);
    }

    /**
     * Export data from a specific fiscal year.
     *
     * @param int $sourceYearId The fiscal year (Company) ID to export.
     * @param array|null $sectionsToExport Specific sections to export (null for all available).
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

        // Add source year info for context (optional but helpful)
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
     * @param array $importData Data structure (from exportData or similar).
     * @param array $newFiscalYearData Data for the new Company record.
     * @return Company The newly created Company (Fiscal Year).
     * @throws Exception
     */
    public static function importData(array $importData, array $newFiscalYearData): Company
    {
        // Determine which sections are present in the import data
        $sectionsToImport = array_intersect(
            array_keys(self::getAvailableSections()),
            array_keys($importData)
        );

        // Start a transaction
        return DB::transaction(function () use ($importData, $newFiscalYearData, $sectionsToImport) {
            // Create new fiscal year
            $newFiscalYear = Company::create($newFiscalYearData);
            $targetYearId = $newFiscalYear->id;

            // Set the session company ID temporarily for potential model observers/scopes
            // that might rely on it during the creation process.
            $originalCompanyId = session('active-company-id');
            session(['active-company-id' => $targetYearId]);

            // Track the mapping between old IDs (from import data) and new IDs (in DB)
            $idMappings = [
                'subjects' => [],
                'banks' => [],
                'customer_groups' => [],
                'product_groups' => [],
            ];

            try {
                // --- Import Process (Order Matters!) ---

                // 1. Standalone Subjects (must be done first for FKs in other tables like configs, groups)
                if (in_array('subjects', $sectionsToImport) && isset($importData['subjects'])) {
                    $idMappings['subjects'] = self::_importSubjects($importData['subjects'], $targetYearId);
                }

                // 2. Configs (depends on subjects)
                if (in_array('configs', $sectionsToImport) && isset($importData['configs'])) {
                    self::_importConfigs($importData['configs'], $targetYearId, $idMappings['subjects']);
                }

                // 3. Banks (no dependencies other than company)
                if (in_array('banks', $sectionsToImport) && isset($importData['banks'])) {
                    $idMappings['banks'] = self::_importBanks($importData['banks'], $targetYearId);
                }

                // 4. Bank Accounts (depends on banks)
                if (in_array('bank_accounts', $sectionsToImport) && isset($importData['bank_accounts'])) {
                    self::_importBankAccounts($importData['bank_accounts'], $targetYearId, $idMappings['banks']);
                }

                // 5. Customer Groups (depends on subjects)
                if (in_array('customer_groups', $sectionsToImport) && isset($importData['customer_groups'])) {
                    $idMappings['customer_groups'] = self::_importCustomerGroups($importData['customer_groups'], $targetYearId, $idMappings['subjects']);
                }

                // 6. Customers (depends on customer groups)
                if (in_array('customers', $sectionsToImport) && isset($importData['customers'])) {
                    self::_importCustomers($importData['customers'], $targetYearId, $idMappings['customer_groups']);
                }

                // 7. Product Groups (depends on subjects)
                if (in_array('product_groups', $sectionsToImport) && isset($importData['product_groups'])) {
                    $idMappings['product_groups'] = self::_importProductGroups($importData['product_groups'], $targetYearId, $idMappings['subjects']);
                }

                // 8. Products (depends on product groups)
                if (in_array('products', $sectionsToImport) && isset($importData['products'])) {
                    self::_importProducts($importData['products'], $targetYearId, $idMappings['product_groups']);
                }

                // --- End Import Process ---

                return $newFiscalYear;
            } catch (Exception $e) {
                // Log the error before re-throwing to ensure transaction rollback
                Log::error("Fiscal Year Import Failed: " . $e->getMessage(), [
                    'exception' => $e,
                    'new_fiscal_year_data' => $newFiscalYearData,
                    'import_data_keys' => array_keys($importData), // Avoid logging potentially huge data
                ]);
                // Re-throw the exception to trigger transaction rollback
                throw $e;
            } finally {
                // Always restore original company ID
                session(['active-company-id' => $originalCompanyId]);
            }
        });
    }

    /**
     * Fetch data for specified sections from the source fiscal year.
     *
     * @param int $sourceYearId
     * @param array $sections
     * @return array<string, array>
     */
    protected static function fetchSourceData(int $sourceYearId, array $sections): array
    {
        $sourceData = [];

        // Fetch in a reasonable dependency order, although order doesn't strictly matter for fetching
        if (in_array('subjects', $sections)) {
            $sourceData['subjects'] = Subject::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                // ->whereNull('subjectable_type') // Fetch ALL subjects for export/copy
                // ->whereNull('subjectable_id')
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
        }
        if (in_array('bank_accounts', $sections)) {
            $sourceData['bank_accounts'] = BankAccount::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('customer_groups', $sections)) {
            $sourceData['customer_groups'] = CustomerGroup::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('customers', $sections)) {
            $sourceData['customers'] = Customer::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('product_groups', $sections)) {
            $sourceData['product_groups'] = ProductGroup::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('products', $sections)) {
            $sourceData['products'] = Product::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }

        return $sourceData;
    }

    /**
     * Import Subjects, handling parent-child relationships and returning ID mapping.
     *
     * @param array $subjectsData Array of subject data from import.
     * @param int $targetYearId
     * @return array<int, int> Mapping of old subject ID to new subject ID.
     */
    protected static function _importSubjects(array $subjectsData, int $targetYearId): array
    {
        $mapping = [];
        $subjectsByOldParentId = collect($subjectsData)->groupBy('parent_id');

        // Use a queue or recursive approach to handle hierarchy
        $processQueue = $subjectsByOldParentId->get(null, collect())->pluck('id')->toArray(); // Start with root nodes (old parent_id is null or 0)
        $processedIds = []; // Keep track of processed old IDs

        while (!empty($processQueue)) {
            $currentOldId = array_shift($processQueue);

            if (in_array($currentOldId, $processedIds)) {
                continue; // Already processed
            }

            // Find the subject data for the current old ID
            $subjectData = collect($subjectsData)->firstWhere('id', $currentOldId);
            if (!$subjectData) {
                Log::warning("Subject data not found for old ID during import.", ['old_id' => $currentOldId, 'target_year_id' => $targetYearId]);
                continue;
            }

            $newSubject = new Subject();
            // Fill attributes, excluding 'id' and potentially timestamps if you want fresh ones
            $newSubject->fill(collect($subjectData)->except(['id', 'created_at', 'updated_at'])->toArray());

            // Set the new company ID
            $newSubject->company_id = $targetYearId;

            // Map the parent ID using already processed mappings
            $oldParentId = $subjectData['parent_id'] ?? null;
            $newSubject->parent_id = ($oldParentId && isset($mapping[$oldParentId])) ? $mapping[$oldParentId] : null;

            $newSubject->save();

            // Store the mapping
            $mapping[$subjectData['id']] = $newSubject->id;
            $processedIds[] = $subjectData['id'];

            // Add children to the queue
            $children = $subjectsByOldParentId->get($subjectData['id'], collect());
            foreach ($children as $child) {
                if (!in_array($child['id'], $processedIds)) {
                    $processQueue[] = $child['id'];
                }
            }
        }

        // Handle potential orphans or items missed if data wasn't perfectly ordered
        if (count($mapping) < count($subjectsData)) {
            Log::warning("Potential subject import mismatch.", [
                'expected_count' => count($subjectsData),
                'imported_count' => count($mapping),
                'target_year_id' => $targetYearId
            ]);
            // Optionally, add a loop here to try and process remaining items, though the queue should handle it if parent_id links are correct.
        }

        return $mapping;
    }


    /**
     * Import Configs.
     *
     * @param array $configsData
     * @param int $targetYearId
     * @param array $subjectMapping Mapping of old subject ID to new subject ID.
     */
    protected static function _importConfigs(array $configsData, int $targetYearId, array $subjectMapping): void
    {
        foreach ($configsData as $configData) {
            $newConfig = new Config();
            $newConfig->fill(collect($configData)->except(['id', 'created_at', 'updated_at'])->toArray());
            $newConfig->company_id = $targetYearId;

            // Remap value if it corresponds to an old subject ID
            $oldValue = $configData['value'] ?? null;
            if ($oldValue !== null && isset($subjectMapping[$oldValue])) {
                // Heuristic: Assume if the value matches an old subject ID, it should be mapped.
                // You might need a more robust check depending on your config keys/values.
                // For example, check if config 'key' indicates a subject relationship.
                $newConfig->value = $subjectMapping[$oldValue];
            } else {
                $newConfig->value = $oldValue; // Keep original value otherwise
            }

            $newConfig->save();
        }
    }

    /**
     * Import Banks.
     *
     * @param array $banksData
     * @param int $targetYearId
     * @return array<int, int> Mapping of old bank ID to new bank ID.
     */
    protected static function _importBanks(array $banksData, int $targetYearId): array
    {
        $mapping = [];
        foreach ($banksData as $bankData) {
            $newBank = new Bank();
            $newBank->fill(collect($bankData)->except(['id', 'created_at', 'updated_at'])->toArray());
            $newBank->company_id = $targetYearId;
            $newBank->save();
            $mapping[$bankData['id']] = $newBank->id;
        }
        return $mapping;
    }

    /**
     * Import Bank Accounts.
     *
     * @param array $bankAccountsData
     * @param int $targetYearId
     * @param array $bankMapping Mapping of old bank ID to new bank ID.
     */
    protected static function _importBankAccounts(array $bankAccountsData, int $targetYearId, array $bankMapping): void
    {
        foreach ($bankAccountsData as $accountData) {
            $oldBankId = $accountData['bank_id'] ?? null;
            if ($oldBankId === null || !isset($bankMapping[$oldBankId])) {
                Log::warning("Skipping bank account import due to missing bank mapping.", ['old_bank_account_id' => $accountData['id'] ?? 'N/A', 'old_bank_id' => $oldBankId, 'target_year_id' => $targetYearId]);
                continue; // Skip if the corresponding bank wasn't imported or mapped
            }

            $newAccount = new BankAccount();
            $newAccount->fill(collect($accountData)->except(['id', 'created_at', 'updated_at'])->toArray());
            $newAccount->bank_id = $bankMapping[$oldBankId]; // Use the new bank ID
            $newAccount->company_id = $targetYearId;
            $newAccount->save();
        }
    }

    /**
     * Import Customer Groups.
     *
     * @param array $groupsData
     * @param int $targetYearId
     * @param array $subjectMapping Mapping of old subject ID to new subject ID.
     * @return array<int, int> Mapping of old group ID to new group ID.
     */
    protected static function _importCustomerGroups(array $groupsData, int $targetYearId, array $subjectMapping): array
    {
        $mapping = [];
        foreach ($groupsData as $groupData) {
            $newGroup = new CustomerGroup();
            $newGroup->fill(collect($groupData)->except(['id', 'created_at', 'updated_at'])->toArray());
            $newGroup->company_id = $targetYearId;

            // Map subject ID if present
            $oldSubjectId = $groupData['subject_id'] ?? null;
            $newGroup->subject_id = ($oldSubjectId && isset($subjectMapping[$oldSubjectId])) ? $subjectMapping[$oldSubjectId] : null;

            $newGroup->save();
            $mapping[$groupData['id']] = $newGroup->id;
        }
        return $mapping;
    }

    /**
     * Import Customers.
     *
     * @param array $customersData
     * @param int $targetYearId
     * @param array $groupMapping Mapping of old customer group ID to new group ID.
     */
    protected static function _importCustomers(array $customersData, int $targetYearId, array $groupMapping): void
    {
        foreach ($customersData as $customerData) {
            $oldGroupId = $customerData['group_id'] ?? null;
            if ($oldGroupId === null || !isset($groupMapping[$oldGroupId])) {
                Log::warning("Skipping customer import due to missing group mapping.", ['old_customer_id' => $customerData['id'] ?? 'N/A', 'old_group_id' => $oldGroupId, 'target_year_id' => $targetYearId]);
                continue; // Skip if the corresponding group wasn't imported or mapped
            }

            $newCustomer = new Customer();
            $newCustomer->fill(collect($customerData)->except(['id', 'created_at', 'updated_at'])->toArray());
            $newCustomer->company_id = $targetYearId;
            $newCustomer->group_id = $groupMapping[$oldGroupId]; // Use the new group ID
            $newCustomer->save();
        }
    }

    /**
     * Import Product Groups.
     *
     * @param array $groupsData
     * @param int $targetYearId
     * @param array $subjectMapping Mapping of old subject ID to new subject ID.
     * @return array<int, int> Mapping of old group ID to new group ID.
     */
    protected static function _importProductGroups(array $groupsData, int $targetYearId, array $subjectMapping): array
    {
        $mapping = [];
        foreach ($groupsData as $groupData) {
            $newGroup = new ProductGroup();
            $newGroup->fill(collect($groupData)->except(['id', 'created_at', 'updated_at'])->toArray());
            $newGroup->company_id = $targetYearId;

            // Map subject IDs (buyId, sellId)
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
     * @param array $productsData
     * @param int $targetYearId
     * @param array $groupMapping Mapping of old product group ID to new group ID.
     */
    protected static function _importProducts(array $productsData, int $targetYearId, array $groupMapping): void
    {
        foreach ($productsData as $productData) {
            // Assuming 'group' column holds the group ID in Product model
            $oldGroupId = $productData['group'] ?? null;
            if ($oldGroupId === null || !isset($groupMapping[$oldGroupId])) {
                Log::warning("Skipping product import due to missing group mapping.", ['old_product_id' => $productData['id'] ?? 'N/A', 'old_group_id' => $oldGroupId, 'target_year_id' => $targetYearId]);
                continue; // Skip if the corresponding group wasn't imported or mapped
            }

            $newProduct = new Product();
            $newProduct->fill(collect($productData)->except(['id', 'created_at', 'updated_at'])->toArray());
            $newProduct->group = $groupMapping[$oldGroupId]; // Use the new group ID
            $newProduct->company_id = $targetYearId;
            $newProduct->save();
        }
    }


    // --- Utility ---

    /**
     * Filter an array of section keys to include only valid ones.
     *
     * @param array $sections
     * @return array
     */
    protected static function filterValidSections(array $sections): array
    {
        $availableSections = self::getAvailableSections();
        return array_filter($sections, function ($section) use ($availableSections) {
            return array_key_exists($section, $availableSections);
        });
    }
}
