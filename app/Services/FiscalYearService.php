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

class FiscalYearService
{
    /**
     * Get available sections for copying with translations
     * 
     * @return array
     */
    public static function getAvailableSections()
    {
        return [
            'bank_accounts' => __('Bank Accounts'),
            'customers' => __('Customers'),
            'products' => __('Products'),
        ];
    }

    /**
     * Create a new fiscal year with data copied from an existing one
     * 
     * @param array $data New fiscal year data
     * @param int $sourceYearId The source fiscal year ID
     * @param array $sectionsToImport Sections to copy from source
     * @return Company
     */
    public static function createWithCopiedData(array $data, int $sourceYearId, array $sectionsToImport)
    {
        $availableSections = self::getAvailableSections();

        // Filter sections to copy based on availability
        $sectionsToImport = array_filter($sectionsToImport, function ($section) use ($availableSections) {
            return array_key_exists($section, $availableSections);
        });

        // Start a transaction
        return DB::transaction(function () use ($data, $sourceYearId, $sectionsToImport) {
            // Create new fiscal year
            $newFiscalYear = Company::create($data);

            // Set the session company ID to new fiscal year
            $originalCompanyId = session('active-company-id');
            session(['active-company-id' => $newFiscalYear->id]);

            // Track the mapping between old and new IDs for relationships
            $mappings = [
                'subjects' => [],
                'customer_groups' => [],
                'product_groups' => [],
                'banks' => [],
            ];

            try {
                $mappings['banks'] = self::copyBanks($sourceYearId, $newFiscalYear->id);
                $mappings['subjects'] = self::copyStandaloneSubjects($sourceYearId, $newFiscalYear->id);

                self::copyConfigs($sourceYearId, $newFiscalYear->id, $mappings['subjects']);

                // Copy sections in the right order to maintain relationships
                foreach ($sectionsToImport as $section) {
                    switch ($section) {
                        case 'bank_accounts':
                            self::copyBankAccounts($sourceYearId, $newFiscalYear->id, $mappings['banks']);
                            break;
                        case 'customers':
                            $mappings['customer_groups'] = self::copyCustomerGroups($sourceYearId, $newFiscalYear->id, $mappings['subjects']);
                            self::copyCustomers($sourceYearId, $newFiscalYear->id, $mappings['customer_groups']);
                            break;
                        case 'products':
                            $mappings['product_groups'] = self::copyProductGroups($sourceYearId, $newFiscalYear->id, $mappings['subjects']);
                            self::copyProducts($sourceYearId, $newFiscalYear->id, $mappings['product_groups']);
                            break;
                    }
                }

                return $newFiscalYear;
            } finally {
                // Restore original company ID
                session(['active-company-id' => $originalCompanyId]);
            }
        });
    }

    /**
     * Copy standalone subjects (not related to any entity)
     * 
     * @param int $sourceYearId
     * @param int $targetYearId
     * @return array Mapping of old ID to new ID
     */
    protected static function copyStandaloneSubjects(int $sourceYearId, int $targetYearId): array
    {
        $mapping = [];

        // Get subjects not related to any entity (subjectable_id is null)
        $subjects = Subject::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
            ->where('company_id', $sourceYearId)
            ->whereNull('subjectable_type')
            ->whereNull('subjectable_id')
            ->get();

        // Create subjects tree structure safely (parent-child relationships)
        $subjectsByParentId = $subjects->groupBy('parent_id');

        // Copy root subjects first
        $rootSubjects = $subjectsByParentId->get(null, collect());
        foreach ($rootSubjects as $subject) {
            $newSubject = self::copySubjectWithChildren($subject, $targetYearId, $mapping, $subjectsByParentId);
            $mapping[$subject->id] = $newSubject->id;
        }

        return $mapping;
    }

    /**
     * Recursively copy a subject and its children
     */
    protected static function copySubjectWithChildren($subject, $targetYearId, &$mapping, $subjectsByParentId)
    {
        // Replicate the subject
        $newSubject = $subject->replicate();
        $newSubject->parent_id = $subject->parent_id > 0 ? ($mapping[$subject->parent_id] ?? 0) : 0;
        $newSubject->company_id = $targetYearId;
        $newSubject->save();

        $mapping[$subject->id] = $newSubject->id;

        // Copy children recursively
        $children = $subjectsByParentId->get($subject->id, collect());
        foreach ($children as $child) {
            self::copySubjectWithChildren($child, $targetYearId, $mapping, $subjectsByParentId);
        }

        return $newSubject;
    }

    /**
     * Copy configs
     */
    protected static function copyConfigs(int $sourceYearId, int $targetYearId, array $subjectMapping): void
    {
        $configs = Config::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
            ->where('company_id', $sourceYearId)
            ->get();

        foreach ($configs as $config) {
            $newConfig = $config->replicate();
            $newConfig->company_id = $targetYearId;
            $newConfig->value = $subjectMapping[$config->value] ?? $config->value;
            $newConfig->save();
        }
    }

    /**
     * Copy banks
     */
    protected static function copyBanks(int $sourceYearId, int $targetYearId): array
    {
        $mapping = [];

        $banks = Bank::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
            ->where('company_id', $sourceYearId)
            ->get();

        foreach ($banks as $bank) {
            $newBank = $bank->replicate();
            $newBank->company_id = $targetYearId;
            $newBank->save();

            $mapping[$bank->id] = $newBank->id;
        }

        return $mapping;
    }

    /**
     * Copy bank accounts
     */
    protected static function copyBankAccounts(int $sourceYearId, int $targetYearId, array $bankMapping): void
    {
        $bankAccounts = BankAccount::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
            ->where('company_id', $sourceYearId)
            ->get();

        foreach ($bankAccounts as $account) {
            $newAccount = $account->replicate();
            $newAccount->bank_id = $bankMapping[$account->bank_id] ?? $account->bank_id;
            $newAccount->company_id = $targetYearId;
            $newAccount->save();
        }
    }

    /**
     * Copy customer groups
     */
    protected static function copyCustomerGroups(int $sourceYearId, int $targetYearId, array $subjectMapping): array
    {
        $mapping = [];

        $groups = CustomerGroup::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
            ->where('company_id', $sourceYearId)
            ->get();

        foreach ($groups as $group) {
            $newGroup = $group->replicate();
            $newGroup->company_id = $targetYearId;
            $newGroup->subject_id = $subjectMapping[$group->subject_id] ?? null;
            $newGroup->save();

            $mapping[$group->id] = $newGroup->id;
        }

        return $mapping;
    }

    /**
     * Copy customers
     */
    protected static function copyCustomers(int $sourceYearId, int $targetYearId, array $groupMapping): void
    {
        $customers = Customer::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
            ->where('company_id', $sourceYearId)
            ->get();

        foreach ($customers as $customer) {
            if (isset($groupMapping[$customer->group_id])) {
                $newCustomer = $customer->replicate();
                $newCustomer->company_id = $targetYearId;
                $newCustomer->group_id = $groupMapping[$customer->group_id];
                $newCustomer->save();
            }
        }
    }

    /**
     * Copy product groups
     */
    protected static function copyProductGroups(int $sourceYearId, int $targetYearId, array $subjectMapping): array
    {
        $mapping = [];

        $groups = ProductGroup::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
            ->where('company_id', $sourceYearId)
            ->get();

        foreach ($groups as $group) {
            $newGroup = $group->replicate();
            $newGroup->company_id = $targetYearId;
            $newGroup->buyId = $subjectMapping[$group->buyId] ?? null;
            $newGroup->sellId = $subjectMapping[$group->sellId] ?? null;
            $newGroup->save();

            $mapping[$group->id] = $newGroup->id;
        }

        return $mapping;
    }

    /**
     * Copy products
     */
    protected static function copyProducts(int $sourceYearId, int $targetYearId, array $groupMapping): void
    {
        $products = Product::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
            ->where('company_id', $sourceYearId)
            ->get();

        foreach ($products as $product) {
            if (isset($groupMapping[$product->group])) {
                $newProduct = $product->replicate();
                $newProduct->group = $groupMapping[$product->group];
                $newProduct->company_id = $targetYearId;
                $newProduct->save();
            }
        }
    }
}
