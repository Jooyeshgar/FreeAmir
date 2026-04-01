<?php

namespace App\Services;

use App\Enums\FiscalYearSection;
use App\Models\AncillaryCost;
use App\Models\AncillaryCostItem;
use App\Models\AttendanceLog;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\ChequeHistory;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Config;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DecreeBenefit;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MonthlyAttendance;
use App\Models\OrgChart;
use App\Models\Payroll;
use App\Models\PayrollElement;
use App\Models\PayrollItem;
use App\Models\PersonnelRequest;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductWebsite;
use App\Models\PublicHoliday;
use App\Models\SalaryDecree;
use App\Models\Scopes\FiscalYearScope;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use App\Models\TaxSlab;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Models\WorkSiteContract;
use Cookie;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FiscalYearService
{
    /**
     * Get available sections for copying/exporting with translations.
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
        $sectionsToExport = $sectionsToExport === null ? array_keys(self::getAvailableSections()) : self::filterValidSections($sectionsToExport);

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

            $idMappings = [];

            try {
                // --- Import Process (Order Matters!) ---
                if (in_array('subjects', $sectionsToImport) && isset($importData['subjects'])) {
                    $idMappings['subjects'] = self::_importSubjects($importData['subjects'], $targetYearId);
                }
                if (in_array('configs', $sectionsToImport) && isset($importData['configs'])) {
                    $subjectMapping = $idMappings['subjects'] ?? [];

                    if (empty($subjectMapping)) {
                        Log::warning('Skipping configs import due to missing subject mapping.', ['target_year_id' => $targetYearId]);
                    } else {
                        self::_importConfigs($importData['configs'], $targetYearId, $subjectMapping);
                    }
                }
                if (in_array('banks', $sectionsToImport)) {
                    if (isset($importData['banks'])) {
                        $idMappings['banks'] = self::_importBanks($importData['banks'], $targetYearId);
                    }

                    if (isset($importData['bank_accounts'])) {
                        $bankMapping = $idMappings['banks'] ?? [];

                        if (empty($bankMapping)) {
                            Log::warning('Skipping bank accounts import due to missing bank mapping.', ['target_year_id' => $targetYearId]);
                        } else {
                            $idMappings['bank_accounts'] = self::_importBankAccounts($importData['bank_accounts'], $targetYearId, $bankMapping);
                        }
                    }
                }
                if (in_array('customers', $sectionsToImport)) {
                    if (isset($importData['customer_groups'])) {
                        $subjectMapping = $idMappings['subjects'] ?? [];

                        if (empty($subjectMapping)) {
                            Log::warning('Skipping customer groups import due to missing subject mapping.', ['target_year_id' => $targetYearId]);
                        } else {
                            $idMappings['customer_groups'] = self::_importCustomerGroups($importData['customer_groups'], $targetYearId, $subjectMapping);
                        }
                    }

                    if (isset($importData['customers'])) {
                        $groupMapping = $idMappings['customer_groups'] ?? [];
                        $subjectMapping = $idMappings['subjects'] ?? [];

                        if (empty($groupMapping) || empty($subjectMapping)) {
                            Log::warning('Skipping customers import due to missing group or subject mapping.', [
                                'target_year_id' => $targetYearId,
                                'has_group_mapping' => ! empty($groupMapping),
                                'has_subject_mapping' => ! empty($subjectMapping),
                            ]);
                        } else {
                            $idMappings['customers'] = self::_importCustomers($importData['customers'], $targetYearId, $groupMapping, $subjectMapping);
                        }
                    }

                    if (isset($importData['comments'])) {
                        $customerMapping = $idMappings['customers'] ?? [];

                        if (empty($customerMapping)) {
                            Log::warning('Skipping comments import due to missing customer mapping.', ['target_year_id' => $targetYearId]);
                        } else {
                            self::_importComments($importData['comments'], $customerMapping);
                        }
                    }
                }
                if (in_array('products', $sectionsToImport)) {
                    if (isset($importData['product_groups'])) {
                        $subjectMapping = $idMappings['subjects'] ?? [];

                        if (empty($subjectMapping)) {
                            Log::warning('Skipping product groups import due to missing subject mapping.', ['target_year_id' => $targetYearId]);
                        } else {
                            $idMappings['product_groups'] = self::_importProductGroups($importData['product_groups'], $targetYearId, $subjectMapping);
                        }
                    }

                    if (isset($importData['products'])) {
                        $groupMapping = $idMappings['product_groups'] ?? [];
                        $subjectMapping = $idMappings['subjects'] ?? [];

                        if (empty($groupMapping) || empty($subjectMapping)) {
                            Log::warning('Skipping products import due to missing group or subject mapping.', [
                                'target_year_id' => $targetYearId,
                                'has_group_mapping' => ! empty($groupMapping),
                                'has_subject_mapping' => ! empty($subjectMapping),
                            ]);
                        } else {
                            $idMappings['products'] = self::_importProducts($importData['products'], $targetYearId, $groupMapping, $subjectMapping);
                        }
                    }

                    if (isset($importData['product_websites'])) {
                        $productMapping = $idMappings['products'] ?? [];

                        if (empty($productMapping)) {
                            Log::warning('Skipping product websites import due to missing product mapping.', ['target_year_id' => $targetYearId]);
                        } else {
                            self::_importProductWebsites($importData['product_websites'], $productMapping);
                        }
                    }
                }
                if (in_array('services', $sectionsToImport)) {
                    if (isset($importData['service_groups'])) {
                        $subjectMapping = $idMappings['subjects'] ?? [];

                        if (empty($subjectMapping)) {
                            Log::warning('Skipping service groups import due to missing subject mapping.', ['target_year_id' => $targetYearId]);
                        } else {
                            $idMappings['service_groups'] = self::_importServiceGroups($importData['service_groups'], $targetYearId, $subjectMapping);
                        }
                    }

                    if (isset($importData['services'])) {
                        $groupMapping = $idMappings['service_groups'] ?? [];
                        $subjectMapping = $idMappings['subjects'] ?? [];

                        if (empty($groupMapping) || empty($subjectMapping)) {
                            Log::warning('Skipping services import due to missing group or subject mapping.', [
                                'target_year_id' => $targetYearId,
                                'has_group_mapping' => ! empty($groupMapping),
                                'has_subject_mapping' => ! empty($subjectMapping),
                            ]);
                        } else {
                            $idMappings['services'] = self::_importServices($importData['services'], $targetYearId, $groupMapping, $subjectMapping);
                        }
                    }
                }
                if (in_array('documents', $sectionsToImport)) {
                    if (isset($importData['documents'])) {
                        $idMappings['documents'] = self::_importDocuments($importData['documents'], $targetYearId);
                    }
                    if (isset($importData['transactions'])) {
                        $documentMapping = $idMappings['documents'] ?? [];
                        $subjectMapping = $idMappings['subjects'] ?? [];

                        if (! empty($documentMapping) && ! empty($subjectMapping)) {
                            $idMappings['transactions'] = self::_importTransactions($importData['transactions'], $targetYearId, $documentMapping, $subjectMapping);
                        } else {
                            Log::warning('Skipping transactions import due to missing document or subject mappings.', [
                                'target_year_id' => $targetYearId,
                                'has_document_mapping' => ! empty($documentMapping),
                                'has_subject_mapping' => ! empty($subjectMapping),
                            ]);
                        }
                    }
                    if (isset($importData['document_files'])) {
                        $documentMapping = $idMappings['documents'] ?? [];

                        if (empty($documentMapping)) {
                            Log::warning('Skipping document files import due to missing document mapping.', ['target_year_id' => $targetYearId]);
                        } else {
                            self::_importDocumentFiles($importData['document_files'], $targetYearId, $documentMapping);
                        }
                    }
                }
                if (in_array('cheques', $sectionsToImport)) {
                    $customerMapping = $idMappings['customers'] ?? [];
                    $transactionMapping = $idMappings['transactions'] ?? [];
                    $bankAccountMapping = $idMappings['bank_accounts'] ?? [];

                    if (isset($importData['cheques'])) {
                        if (! empty($customerMapping) && ! empty($transactionMapping) && ! empty($bankAccountMapping)) {
                            $idMappings['cheques'] = self::_importCheques($importData['cheques'], $targetYearId, $customerMapping, $transactionMapping, $bankAccountMapping);
                        } else {
                            Log::warning('Skipping cheques import due to missing customer or transaction or bank account mappings.', [
                                'target_year_id' => $targetYearId,
                                'has_customer_mapping' => ! empty($customerMapping),
                                'has_trasnaction_mapping' => ! empty($transactionMapping),
                                'has_bank_account_mapping' => ! empty($bankAccountMapping),
                            ]);
                        }
                    }

                    // if (isset($importData['cheque_histories'])) {
                    //     $chequeMapping = $idMappings['cheques'] ?? [];
                    //     if (! empty($chequeMapping) && ! empty($customerMapping) && ! empty($transactionMapping) && ! empty($bankAccountMapping)) {
                    //         self::_importChequeHistories($importData['cheque_histories'], $targetYearId, $chequeMapping, $customerMapping, $transactionMapping, $bankAccountMapping);
                    //     } else {
                    //         Log::warning('Skipping cheque histories import due to missing cheque or customer or transaction or bank account mappings.', [
                    //             'target_year_id' => $targetYearId,
                    //             'has_cheque_mapping' => ! empty($chequeMapping),
                    //             'has_customer_mapping' => ! empty($customerMapping),
                    //             'has_trasnaction_mapping' => ! empty($transactionMapping),
                    //             'has_bank_account_mapping' => ! empty($bankAccountMapping),
                    //         ]);
                    //     }
                    // }
                }
                if (in_array('invoices', $sectionsToImport)) {
                    $customerMapping = $idMappings['customers'] ?? [];
                    $documentMapping = $idMappings['documents'] ?? [];
                    $productMapping = $idMappings['products'] ?? [];
                    $serviceMapping = $idMappings['services'] ?? [];

                    if (isset($importData['invoices'])) {
                        if (! empty($customerMapping) && ! empty($documentMapping)) {
                            $idMappings['invoices'] = self::_importInvoices($importData['invoices'], $targetYearId, $documentMapping, $customerMapping);
                        } else {
                            Log::warning('Skipping not-return type invoice import due to missing customer or document mappings.', [
                                'target_year_id' => $targetYearId,
                                'has_customer_mapping' => ! empty($customerMapping),
                                'has_document_mapping' => ! empty($documentMapping),
                            ]);
                        }
                    }

                    if (isset($importData['invoice_items'])) {
                        if (! empty($idMappings['invoices']) && ! (empty($productMapping) && empty($serviceMapping))) {
                            self::_importInvoiceItems($importData['invoice_items'], $idMappings['invoices'], $productMapping, $serviceMapping);
                        } else {
                            Log::warning('Skipping invoice item import due to missing invoice or product or service mappings.', [
                                'target_year_id' => $targetYearId,
                                'has_invoice_mapping' => ! empty($idMappings['invoices']),
                                'has_product_mapping' => ! empty($productMapping),
                                'has_service_mapping' => ! empty($serviceMapping),
                            ]);
                        }
                    }

                    if (isset($importData['ancillary_costs'])) {
                        if (! empty($idMappings['invoices']) && ! empty($customerMapping) && ! empty($documentMapping)) {
                            $idMappings['ancillary_costs'] = self::_importAncillaryCosts($importData['ancillary_costs'], $targetYearId, $idMappings['invoices'], $customerMapping, $documentMapping);
                        } else {
                            Log::warning('Skipping ancillary cost import due to missing invoice or customer or document mappings.',
                                [
                                    'target_year_id' => $targetYearId,
                                    'has_invoice_mapping' => ! empty($idMappings['invoices']),
                                    'has_customer_mapping' => ! empty($customerMapping),
                                    'has_document_mapping' => ! empty($documentMapping),
                                ]);
                        }
                    }

                    if (isset($importData['ancillary_cost_items'])) {
                        if (! empty($idMappings['ancillary_costs']) && ! empty($productMapping)) {
                            self::_importAncillaryCostItems($importData['ancillary_cost_items'], $idMappings['ancillary_costs'], $productMapping);
                        } else {
                            Log::warning('Skipping ancillary cost item import due to missing ancillary cost or product mappings.',
                                [
                                    'target_year_id' => $targetYearId,
                                    'has_ancillary_costs_mapping' => ! empty($idMappings['ancillary_costs']),
                                    'has_product_mapping' => ! empty($productMapping),
                                ]);
                        }
                    }
                }
                if (isset($importData['tax_slabs'])) {
                    self::_importTaxSlabs($importData['tax_slabs'], $targetYearId);
                }
                if (isset($importData['public_holidays'])) {
                    self::_importPublicHolidays($importData['public_holidays'], $targetYearId);
                }
                if (in_array('employees', $sectionsToImport)) {
                    if (isset($importData['org_charts'])) {
                        $idMappings['org_charts'] = self::_importOrgCharts($importData['org_charts'], $targetYearId);
                    }
                    if (isset($importData['work_sites'])) {
                        $idMappings['work_sites'] = self::_importWorkSites($importData['work_sites'], $targetYearId);
                    }
                    if (isset($importData['work_site_contracts'])) {
                        $workSiteMapping = $idMappings['work_sites'] ?? [];
                        if (! empty($workSiteMapping)) {
                            $idMappings['work_site_contracts'] = self::_importWorkSiteContracts($importData['work_site_contracts'], $workSiteMapping);
                        } else {
                            Log::warning('Skipping work site contract import due to missing work site mapping.', ['target_year_id' => $targetYearId]);
                        }
                    }
                    if (isset($importData['work_shifts'])) {
                        $idMappings['work_shifts'] = self::_importWorkShifts($importData['work_shifts'], $targetYearId);
                    }
                    if (isset($importData['employees'])) {
                        $orgChartMapping = $idMappings['org_charts'] ?? [];
                        $workSiteMapping = $idMappings['work_sites'] ?? [];
                        $workShiftMapping = $idMappings['work_shifts'] ?? [];
                        $work_site_contracts = $idMappings['work_site_contracts'] ?? [];

                        if (! empty($orgChartMapping) && ! empty($workSiteMapping) && ! empty($workShiftMapping) && ! empty($work_site_contracts)) {
                            $idMappings['employees'] = self::_importEmployees($importData['employees'], $targetYearId, $orgChartMapping, $workSiteMapping, $workShiftMapping, $work_site_contracts);
                        } else {
                            Log::warning('Skipping employee import due to missing orgChart or work site or work shift or work site contract mappings.',
                                [
                                    'target_year_id' => $targetYearId,
                                    'has_orgChart_mapping' => ! empty($orgChartMapping),
                                    'has_work_site_mapping' => ! empty($workSiteMapping),
                                    'has_work_shift_mapping' => ! empty($workShiftMapping),
                                    'has_work_site_contract_mapping' => ! empty($work_site_contracts),
                                ]);
                        }
                    }
                    if (isset($importData['salary_decrees'])) {
                        $employeeMapping = $idMappings['employees'] ?? [];
                        if (! empty($employeeMapping)) {
                            $idMappings['salary_decrees'] = self::_importSalaryDecrees($importData['salary_decrees'], $targetYearId, $employeeMapping);
                        } else {
                            Log::warning('Skipping salary decree import due to missing employee mapping.', ['target_year_id' => $targetYearId]);
                        }
                    }
                    if (isset($importData['monthly_attendances'])) {
                        $employeeMapping = $idMappings['employees'] ?? [];
                        if (! empty($employeeMapping)) {
                            $idMappings['monthly_attendances'] = self::_importMonthlyAttendances($importData['monthly_attendances'], $targetYearId, $employeeMapping);
                        } else {
                            Log::warning('Skipping monthly attendance import due to missing employee mapping.', ['target_year_id' => $targetYearId]);
                        }
                    }
                    if (isset($importData['attendance_logs'])) {
                        $monthlyAttendanceMapping = $idMappings['monthly_attendances'] ?? [];
                        $employeeMapping = $idMappings['employees'] ?? [];
                        if (! empty($monthlyAttendanceMapping) && ! empty($employeeMapping)) {
                            $idMappings['attendance_logs'] = self::_importAttendanceLogs($importData['attendance_logs'], $targetYearId, $monthlyAttendanceMapping, $employeeMapping);
                        } else {
                            Log::warning('Skipping attendance log import due to missing monthly attendance or employee mappings.',
                                [
                                    'target_year_id' => $targetYearId,
                                    'has_monthly_attendance_mapping' => ! empty($monthlyAttendanceMapping),
                                    'has_employee_mapping' => ! empty($employeeMapping),
                                ]);
                        }
                    }
                    if (isset($importData['payrolls'])) {
                        $salaryDecreeMapping = $idMappings['salary_decrees'] ?? [];
                        $employeeMapping = $idMappings['employees'] ?? [];
                        $monthlyAttendanceMapping = $idMappings['monthly_attendances'] ?? [];

                        if (! empty($salaryDecreeMapping) && ! empty($employeeMapping) && ! empty($monthlyAttendanceMapping)) {
                            $idMappings['payrolls'] = self::_importPayrolls($importData['payrolls'], $targetYearId, $salaryDecreeMapping, $employeeMapping, $monthlyAttendanceMapping);
                        } else {
                            Log::warning('Skipping payroll import due to missing salary decree or employee or monthly attendance mappings.',
                                [
                                    'target_year_id' => $targetYearId,
                                    'has_salary_decree_mapping' => ! empty($salaryDecreeMapping),
                                    'has_employee_mapping' => ! empty($employeeMapping),
                                    'has_monthly_attendance_mapping' => ! empty($monthlyAttendanceMapping),
                                ]);
                        }
                    }
                    if (isset($importData['payroll_elements'])) {
                        $idMappings['payroll_elements'] = self::_importPayrollElements($importData['payroll_elements'], $targetYearId);
                    }
                    if (isset($importData['payroll_items'])) {
                        $payrollMapping = $idMappings['payrolls'] ?? [];
                        $payrollElementMapping = $idMappings['payroll_elements'] ?? [];

                        if (! empty($payrollMapping) && ! empty($payrollElementMapping)) {
                            $idMappings['payroll_items'] = self::_importPayrollItems($importData['payroll_items'], $payrollMapping, $payrollElementMapping);
                        } else {
                            Log::warning('Skipping payroll item import due to missing payroll or payroll element mappings.',
                                [
                                    'target_year_id' => $targetYearId,
                                    'has_payroll_mapping' => ! empty($payrollMapping),
                                    'has_payroll_element_mapping' => ! empty($payrollElementMapping),
                                ]);
                        }
                    }
                    if (isset($importData['decree_benefits'])) {
                        $salaryDecreeMapping = $idMappings['salary_decrees'] ?? [];
                        $payrollElementMapping = $idMappings['payroll_elements'] ?? [];

                        if (! empty($salaryDecreeMapping) && ! empty($payrollElementMapping)) {
                            $idMappings['decree_benefits'] = self::_importDecreeBenefits($importData['decree_benefits'], $salaryDecreeMapping, $payrollElementMapping);
                        } else {
                            Log::warning('Skipping decree benefit import due to missing salary decree or payroll element mapping.',
                                [
                                    'target_year_id' => $targetYearId,
                                    'has_salary_decree_mapping' => ! empty($salaryDecreeMapping),
                                    'has_payroll_element_mapping' => ! empty($payrollElementMapping),
                                ]);
                        }
                    }
                    if (isset($importData['personnel_requests'])) {
                        $employeeMapping = $idMappings['employees'] ?? [];
                        $payrollMapping = $idMappings['payrolls'] ?? [];

                        if (! empty($employeeMapping) && ! empty($payrollMapping)) {
                            $idMappings['personnel_requests'] = self::_importPersonnelRequests($importData['personnel_requests'], $targetYearId, $employeeMapping, $payrollMapping);
                        } else {
                            Log::warning('Skipping personnel request import due to missing employee or payroll mappings.',
                                [
                                    'target_year_id' => $targetYearId,
                                    'has_employee_mapping' => ! empty($employeeMapping),
                                    'has_payroll_mapping' => ! empty($payrollMapping),
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
            $sourceData['subjects'] = Subject::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->orderBy('parent_id') // Ensure parents likely come before children
                ->get()->toArray();
        }
        if (in_array('configs', $sections)) {
            $sourceData['configs'] = Config::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('banks', $sections)) {
            $sourceData['banks'] = Bank::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['bank_accounts'] = BankAccount::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('customers', $sections)) {
            $sourceData['customer_groups'] = CustomerGroup::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['customers'] = Customer::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->orderBy('introducer_id') // Ensure introducer likely come before its introduced customers
                ->get()->toArray();

            $customerIds = collect($sourceData['customers'])->pluck('id')->toArray();
            $sourceData['comments'] = ! empty($customerIds) ? Comment::whereIn('customer_id', $customerIds)->get()->toArray() : [];
        }
        if (in_array('products', $sections)) {
            $sourceData['product_groups'] = ProductGroup::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['products'] = Product::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $productIds = collect($sourceData['products'])->pluck('id')->toArray();
            $sourceData['product_websites'] = ProductWebsite::whereIn('product_id', $productIds)->get()->toArray();
        }
        if (in_array('services', $sections)) {
            $sourceData['service_groups'] = ServiceGroup::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['services'] = Service::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('documents', $sections)) {
            $sourceData['documents'] = Document::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $documentIds = collect($sourceData['documents'])->pluck('id')->toArray();
            $sourceData['transactions'] = ! empty($documentIds) ? Transaction::whereIn('document_id', $documentIds)->get()->toArray() : [];

            $sourceData['document_files'] = ! empty($documentIds) ? DocumentFile::whereIn('document_id', $documentIds)->get()->toArray() : [];
        }
        if (in_array('invoices', $sections)) {
            $sourceData['invoices'] = Invoice::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $invoiceIds = collect($sourceData['invoices'])->pluck('id')->toArray();
            $sourceData['invoice_items'] = ! empty($invoiceIds) ? InvoiceItem::whereIn('invoice_id', $invoiceIds)->get()->toArray() : [];

            $sourceData['ancillary_costs'] = AncillaryCost::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $ancillaryCostIds = collect($sourceData['ancillary_costs'])->pluck('id')->toArray();
            $sourceData['ancillary_cost_items'] = ! empty($ancillaryCostIds) ? AncillaryCostItem::whereIn('ancillary_cost_id', $ancillaryCostIds)->get()->toArray() : [];
        }
        // if (in_array('cheques', $sections)) {
        //     $sourceData['cheques'] = Cheque::withoutGlobalScope(FiscalYearScope::class)
        //         ->where('company_id', $sourceYearId)
        //         ->get()->toArray();

        //     $sourceData['cheque_histories'] = ChequeHistory::withoutGlobalScope(FiscalYearScope::class)
        //         ->where('company_id', $sourceYearId)
        //         ->get()->toArray();
        // }
        if (in_array('employees', $sections)) {
            $sourceData['employees'] = Employee::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['org_charts'] = OrgChart::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['work_sites'] = WorkSite::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $workSiteIds = collect($sourceData['work_sites'])->pluck('id')->toArray();
            $sourceData['work_site_contracts'] = ! empty($workSiteIds) ? WorkSiteContract::whereIn('work_site_id', $workSiteIds)->get()->toArray() : [];

            $sourceData['work_shifts'] = WorkShift::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['salary_decrees'] = SalaryDecree::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['monthly_attendances'] = MonthlyAttendance::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['payrolls'] = Payroll::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['payroll_elements'] = PayrollElement::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $payrollIds = collect($sourceData['payrolls'])->pluck('id')->toArray();
            $elementIds = collect($sourceData['payroll_elements'])->pluck('id')->toArray();
            $sourceData['payroll_items'] = ! empty($payrollIds) && ! empty($elementIds) ?
                PayrollItem::whereIn('payroll_id', $payrollIds)->whereIn('element_id', $elementIds)->get()->toArray() : [];

            $salaryDecreeIds = collect($sourceData['salary_decrees'])->pluck('id')->toArray();
            $sourceData['decree_benefits'] = ! empty($salaryDecreeIds) && ! empty($elementIds) ?
                DecreeBenefit::whereIn('decree_id', $salaryDecreeIds)->whereIn('element_id', $elementIds)->get()->toArray() : [];

            $sourceData['attendance_logs'] = AttendanceLog::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();

            $sourceData['personnel_requests'] = PersonnelRequest::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('tax_slabs', $sections)) {
            $sourceData['tax_slabs'] = TaxSlab::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }
        if (in_array('public_holidays', $sections)) {
            $sourceData['public_holidays'] = PublicHoliday::withoutGlobalScope(FiscalYearScope::class)
                ->where('company_id', $sourceYearId)
                ->get()->toArray();
        }

        return $sourceData;
    }

    /**
     * Sync documents morph relation.
     *
     * @param  array  $mapping  Mapping old object ID to new object ID.
     * @param  string  $type  Type of related object: invoice or ancillaryCost.
     */
    protected static function _syncDocumentsRelation(array $mapping, string $type): void
    {
        foreach ($mapping as $id) {
            $model = match ($type) {
                'invoice' => Invoice::find($id),
                'ancillaryCost' => AncillaryCost::find($id),
            };
            if ($model?->document_id === null) {
                continue; // Not approved invoice or ancillary cost
            }
            $document = Document::find($model->document_id);
            if ($document->documentable_type !== get_class($model) && $document->documentable_id !== $id) {
                $document->documentable()->associate($model);
                if ($document->isDirty(['documentable_id', 'documentable_type'])) {
                    $document->save();
                }
            }
        }
    }

    /**
     * Sync subjects morph relation.
     *
     * @param  array  $mapping  Mapping old object ID to new object ID.
     * @param  string  $type  Type of related object: bankAccount, customerGroup, customer, productGroup, product, serviceGroup, service.
     * @param  string  $relationColumn  Name of relation column in db.
     */
    protected static function _syncSubjectsRelation(array $mapping, string $type, string $relationColumn): void
    {
        foreach ($mapping as $id) {
            $model = match ($type) {
                'customerGroup' => CustomerGroup::find($id),
                'customer' => Customer::find($id),
                'productGroup' => ProductGroup::find($id),
                'product' => Product::find($id),
                'serviceGroup' => ServiceGroup::find($id),
                'service' => Service::find($id),
                'bankAccount' => BankAccount::find($id),
            };
            if ($model?->{$relationColumn} === null) {
                Log::warning('Skipping subject subjectable sync due to missing relation column.',
                    [
                        'subjectable_model_type' => $type,
                        'subjectable_model_id' => $id,
                        'relation_column_name' => $relationColumn,
                    ]);

                continue;
            }
            $subject = Subject::find($model?->{$relationColumn});
            if ($subject->subjectable_type !== get_class($model) && $subject->subjectable_id !== $id) {
                $subject->subjectable()->associate($model);
                if ($subject->isDirty(['subjectable_id', 'subjectable_type'])) {
                    $subject->save();
                }
            }
        }
    }

    /**
     * Import OrgCharts.
     *
     * @param  array  $orgChartsData  Array of orgCharts data from import.
     * @return array<int, int> Mapping of old OrgChart ID to new OrgChart ID.
     */
    protected static function _importOrgCharts(array $orgChartsData, int $targetYearId): array
    {
        $mapping = [];
        $orgChartsByOldParentId = collect($orgChartsData)->groupBy('parent_id');

        $rootOrgCharts = $orgChartsByOldParentId->get(null, collect())->merge($orgChartsByOldParentId->get(0, collect()));
        foreach ($rootOrgCharts as $orgChartData) {
            $newOrgChart = self::_createOrgChart($orgChartData, $targetYearId, $mapping);
            $mapping[$orgChartData['id']] = $newOrgChart->id;
            self::_processOrgChartChildren($orgChartData['id'], $orgChartsByOldParentId, $targetYearId, $mapping);
        }

        return $mapping;
    }

    protected static function _createOrgChart(array $orgChartData, int $targetYearId, array &$mapping): OrgChart
    {
        $newOrgChart = new OrgChart;
        $newOrgChart->fill(collect($orgChartData)->except(['id', 'parent_id', 'company_id', '_lft', '_rgt'])->toArray());
        $newOrgChart->company_id = $targetYearId;
        $newOrgChart->parent_id = ($orgChartData['parent_id'] == 0) ? null : $mapping[$orgChartData['parent_id']];
        $newOrgChart->save();

        return $newOrgChart;
    }

    protected static function _processOrgChartChildren($oldParentId, $orgChartsByOldParentId, int $targetYearId, array &$mapping): void
    {
        $children = $orgChartsByOldParentId->get($oldParentId, collect());
        foreach ($children as $childData) {
            if (! isset($mapping[$childData['id']])) {
                $newOrgChart = self::_createOrgChart($childData, $targetYearId, $mapping);
                $mapping[$childData['id']] = $newOrgChart->id;
                if ($orgChartsByOldParentId->has($childData['id'])) {
                    self::_processOrgChartChildren($childData['id'], $orgChartsByOldParentId, $targetYearId, $mapping);
                }
            }
        }
    }

    /**
     * Import PersonnelRequests.
     *
     * @param  array  $payrollMapping  Mapping of old payroll ID to new payroll ID.
     * @param  array  $employeeMapping  Mapping of old employee ID to new employee ID.
     * @return array<int, int> Mapping of old personnel request ID to new personnel request ID.
     */
    protected static function _importPersonnelRequests(array $personnelRequestsData, int $targetYearId, array $employeeMapping, $payrollMapping): array
    {
        $mapping = [];
        foreach ($personnelRequestsData as $pr) {
            $oldEmployeeId = $pr['employee_id'] ?? null;
            if ($oldEmployeeId === null || ! isset($employeeMapping[$oldEmployeeId])) {
                Log::warning('Skipping personnel request import due to missing employee mapping.', ['old_personnel_request_id' => $pr['id'] ?? 'N/A', 'old_employee_id' => $oldEmployeeId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $oldPayrollId = $pr['payroll_id'] ?? null;
            if ($oldPayrollId === null || ! isset($payrollMapping[$oldPayrollId])) {
                Log::warning('Skipping personnel request import due to missing payroll mapping.', ['old_personnel_request_id' => $pr['id'] ?? 'N/A', 'old_payroll_id' => $oldPayrollId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newPr = new PersonnelRequest;
            $newPr->fill(collect($pr)->except(['id', 'payroll_id', 'employee_id'])->toArray());
            $newPr->employee_id = $employeeMapping[$oldEmployeeId];
            $newPr->payroll_id = $payrollMapping[$oldPayrollId];
            $newPr->company_id = $targetYearId;
            $newPr->save();
            $mapping[$pr['id']] = $newPr->id;
        }

        return $mapping;
    }

    /**
     * Import Payrolls.
     *
     * @param  array  $salaryDecreeMapping  Mapping of old salary decree ID to new salary decree ID.
     * @param  array  $employeeMapping  Mapping of old employee ID to new employee ID.
     * @param  array  $monthlyAttendanceMapping  Mapping of old monthly attendance ID to new monthly attendance ID.
     * @return array<int, int> Mapping of old payroll ID to new payroll ID.
     */
    protected static function _importPayrolls(array $payrollsData, int $targetYearId, array $salaryDecreeMapping, array $employeeMapping, $monthlyAttendanceMapping): array
    {
        $mapping = [];
        foreach ($payrollsData as $p) {
            $oldEmployeeId = $p['employee_id'] ?? null;
            if ($oldEmployeeId === null || ! isset($employeeMapping[$oldEmployeeId])) {
                Log::warning('Skipping payroll item import due to missing employee mapping.', ['old_payroll_id' => $p['id'] ?? 'N/A', 'old_employee_id' => $oldEmployeeId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $oldDecreeId = $p['decree_id'] ?? null;
            if ($oldDecreeId === null || ! isset($salaryDecreeMapping[$oldDecreeId])) {
                Log::warning('Skipping payroll import due to missing salary decree mapping.', ['old_payroll_id' => $p['id'] ?? 'N/A', 'old_decree_id' => $oldDecreeId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $oldMonthlyAttendanceId = $p['monthly_attendance_id'] ?? null;
            if ($oldMonthlyAttendanceId === null || ! isset($monthlyAttendanceMapping[$oldMonthlyAttendanceId])) {
                Log::warning('Skipping payroll import due to missing monthly attendance mapping.', ['old_payroll_id' => $p['id'] ?? 'N/A', 'old_monthly_attendance_id' => $oldMonthlyAttendanceId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newP = new Payroll;
            $newP->fill(collect($p)->except(['id', 'decree_id', 'employee_id'])->toArray());
            $newP->employee_id = $employeeMapping[$oldEmployeeId];
            $newP->decree_id = $salaryDecreeMapping[$oldDecreeId];
            $newP->monthly_attendance_id = $monthlyAttendanceMapping[$oldMonthlyAttendanceId];
            $newP->company_id = $targetYearId;
            $newP->save();
            $mapping[$p['id']] = $newP->id;
        }

        return $mapping;
    }

    /**
     * Import PayrollItems.
     *
     * @param  array  $payrollMapping  Mapping of old payroll ID to new payroll ID.
     * @param  array  $payrollElementMapping  Mapping of old payroll element ID to new payroll element ID.
     * @return array<int, int> Mapping of old payroll item ID to new payroll item ID.
     */
    protected static function _importPayrollItems(array $payrollItemsData, array $payrollMapping, array $payrollElementMapping): array
    {
        $mapping = [];
        foreach ($payrollItemsData as $pi) {
            $oldPayrollId = $pi['payroll_id'] ?? null;
            if ($oldPayrollId === null || ! isset($payrollMapping[$oldPayrollId])) {
                Log::warning('Skipping payroll item import due to missing payroll mapping.', ['old_payroll_item_id' => $pi['id'] ?? 'N/A', 'old_payroll_id' => $oldPayrollId]);

                continue;
            }

            $oldElementId = $pi['element_id'] ?? null;
            if ($oldElementId === null || ! isset($payrollElementMapping[$oldElementId])) {
                Log::warning('Skipping payroll item import due to missing payroll element mapping.', ['old_payroll_item_id' => $pi['id'] ?? 'N/A', 'old_element_id' => $oldElementId]);

                continue;
            }

            $newPi = new PayrollItem;
            $newPi->fill(collect($pi)->except(['id', 'decree_id', 'element_id'])->toArray());
            $newPi->payroll_id = $payrollMapping[$oldPayrollId];
            $newPi->element_id = $payrollElementMapping[$oldElementId];
            $newPi->save();
            $mapping[$pi['id']] = $newPi->id;
        }

        return $mapping;
    }

    /**
     * Import DecreeBenefits.
     *
     * @param  array  $salaryDecreeMapping  Mapping of old salary decree ID to new salary decree ID.
     * @param  array  $payrollElementMapping  Mapping of old payroll element ID to new payroll element ID.
     * @return array<int, int> Mapping of old decree benefit ID to new decree benefit ID.
     */
    protected static function _importDecreeBenefits(array $decreeBenefitsData, array $salaryDecreeMapping, array $payrollElementMapping): array
    {
        $mapping = [];
        foreach ($decreeBenefitsData as $db) {
            $oldDecreeId = $db['decree_id'] ?? null;
            if ($oldDecreeId === null || ! isset($salaryDecreeMapping[$oldDecreeId])) {
                Log::warning('Skipping decree benefit import due to missing salary decree mapping.', ['old_decree_benefit_id' => $db['id'] ?? 'N/A', 'old_decree_id' => $oldDecreeId]);

                continue;
            }

            $oldElementId = $db['element_id'] ?? null;
            if ($oldElementId === null || ! isset($payrollElementMapping[$oldElementId])) {
                Log::warning('Skipping decree benefit import due to missing payroll element mapping.', ['old_decree_benefit_id' => $db['id'] ?? 'N/A', 'old_element_id' => $oldElementId]);

                continue;
            }

            $newDb = new DecreeBenefit;
            $newDb->fill(collect($db)->except(['id', 'decree_id', 'element_id'])->toArray());
            $newDb->decree_id = $salaryDecreeMapping[$oldDecreeId];
            $newDb->element_id = $payrollElementMapping[$oldElementId];
            $newDb->save();
            $mapping[$db['id']] = $newDb->id;
        }

        return $mapping;
    }

    /**
     * Import AttendanceLogs.
     *
     * @param  array  $monthlyAttendanceMapping  Mapping of old monthly attendance ID to new monthly attendance ID.
     * @param  array  $employeeMapping  Mapping of old employee ID to new employee ID.
     * @return array<int, int> Mapping of old attendance log ID to new attendance log ID.
     */
    protected static function _importAttendanceLogs(array $attendanceLogsData, int $targetYearId, array $monthlyAttendanceMapping, array $employeeMapping): array
    {
        $mapping = [];
        foreach ($attendanceLogsData as $al) {
            $oldMonthlyAttendanceId = $al['monthly_attendance_id'] ?? null;
            if ($oldMonthlyAttendanceId === null || ! isset($monthlyAttendanceMapping[$oldMonthlyAttendanceId])) {
                Log::warning('Skipping attendance log import due to missing monthly attendance mapping.', ['old_attendance_log_id' => $al['id'] ?? 'N/A', 'old_monthly_attendance_id' => $oldMonthlyAttendanceId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $oldEmployeeId = $al['employee_id'] ?? null;
            if ($oldEmployeeId === null || ! isset($employeeMapping[$oldEmployeeId])) {
                Log::warning('Skipping attendance log import due to missing employee mapping.', ['old_attendance_log_id' => $al['id'] ?? 'N/A', 'old_employee_id' => $oldEmployeeId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newAl = new AttendanceLog;
            $newAl->fill(collect($al)->except(['id', 'employee_id', 'monthly_attendance_id'])->toArray());
            $newAl->company_id = $targetYearId;
            $newAl->employee_id = $employeeMapping[$oldEmployeeId];
            $newAl->monthly_attendance_id = $monthlyAttendanceMapping[$oldMonthlyAttendanceId];
            $newAl->save();
            $mapping[$al['id']] = $newAl->id;
        }

        return $mapping;
    }

    /**
     * Import MonthlyAttendances.
     *
     * @param  array  $employeeMapping  Mapping of old employee ID to new employee ID.
     * @return array<int, int> Mapping of old monthly attendance ID to new monthly attendance ID.
     */
    protected static function _importMonthlyAttendances(array $monthlyAttendancesData, int $targetYearId, array $employeeMapping): array
    {
        $mapping = [];
        foreach ($monthlyAttendancesData as $ma) {
            $oldEmployeeId = $ma['employee_id'] ?? null;
            if ($oldEmployeeId === null || ! isset($employeeMapping[$oldEmployeeId])) {
                Log::warning('Skipping monthly attendance import due to missing employee mapping.', ['old_monthly_attendance_id' => $ma['id'] ?? 'N/A', 'old_employee_id' => $oldEmployeeId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newMa = new MonthlyAttendance;
            $newMa->fill(collect($ma)->except(['id', 'employee_id'])->toArray());
            $newMa->company_id = $targetYearId;
            $newMa->employee_id = $employeeMapping[$oldEmployeeId];
            $newMa->save();
            $mapping[$ma['id']] = $newMa->id;
        }

        return $mapping;
    }

    /**
     * Import SalaryDecrees.
     *
     * @param  array  $employeeMapping  Mapping of old employee ID to new employee ID.
     * @return array<int, int> Mapping of old salary decree ID to new salary decree ID.
     */
    protected static function _importSalaryDecrees(array $salaryDecreesData, int $targetYearId, array $employeeMapping): array
    {
        $mapping = [];
        foreach ($salaryDecreesData as $sd) {
            $oldEmployeeId = $sd['employee_id'] ?? null;
            if ($oldEmployeeId === null || ! isset($employeeMapping[$oldEmployeeId])) {
                Log::warning('Skipping salary decree import due to missing employee mapping.', ['old_salary_decree_id' => $sd['id'] ?? 'N/A', 'old_employee_id' => $oldEmployeeId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newSd = new SalaryDecree;
            $newSd->fill(collect($sd)->except(['id', 'employee_id'])->toArray());
            $newSd->company_id = $targetYearId;
            $newSd->employee_id = $employeeMapping[$oldEmployeeId];
            $newSd->save();
            $mapping[$sd['id']] = $newSd->id;
        }

        return $mapping;
    }

    /**
     * Import PublicHolidays.
     */
    protected static function _importPublicHolidays(array $publicHolidaysData, int $targetYearId): void
    {
        foreach ($publicHolidaysData as $ph) {
            $newPh = new PublicHoliday;
            $newPh->fill(collect($ph)->except(['id'])->toArray());
            $newPh->company_id = $targetYearId;
            $newPh->save();
        }
    }

    /**
     * Import TaxSlabs.
     */
    protected static function _importTaxSlabs(array $taxSlabsData, int $targetYearId): void
    {
        foreach ($taxSlabsData as $ts) {
            $newTs = new TaxSlab;
            $newTs->fill(collect($ts)->except(['id'])->toArray());
            $newTs->company_id = $targetYearId;
            $newTs->save();
        }
    }

    /**
     * Import WorkSiteContracts.
     *
     * @param  array  $workSiteMapping  Mapping of old work site ID to new work site ID.
     * @return array<int, int> Mapping of old work site contract ID to new work site contract ID.
     */
    protected static function _importWorkSiteContracts(array $WorkSiteContractsData, array $workSiteMapping): array
    {
        $mapping = [];
        foreach ($WorkSiteContractsData as $wsc) {
            $oldWorkSiteId = $wsc['work_site_id'] ?? null;
            if ($oldWorkSiteId === null || ! isset($workSiteMapping[$oldWorkSiteId])) {
                Log::warning('Skipping work site contract import due to missing work site mapping.', ['old_work_site_contract_id' => $wsc['id'] ?? 'N/A', 'old_work_site_id' => $oldWorkSiteId]);

                continue;
            }

            $newWsc = new WorkSiteContract;
            $newWsc->fill(collect($wsc)->except(['id'])->toArray());
            $newWsc->work_site_id = $workSiteMapping[$oldWorkSiteId];
            $newWsc->save();
            $mapping[$wsc['id']] = $newWsc->id;
        }

        return $mapping;
    }

    /**
     * Import WorkShift.
     *
     * @return array<int, int> Mapping of old work shift ID to new work shift ID.
     */
    protected static function _importWorkShifts(array $workShiftsData, int $targetYearId): array
    {
        $mapping = [];
        foreach ($workShiftsData as $ws) {
            $newWs = new WorkShift;
            $newWs->fill(collect($ws)->except(['id'])->toArray());
            $newWs->company_id = $targetYearId;
            $newWs->save();
            $mapping[$ws['id']] = $newWs->id;
        }

        return $mapping;
    }

    /**
     * Import WorkSites.
     *
     * @return array<int, int> Mapping of old work site ID to new work site ID.
     */
    protected static function _importWorkSites(array $workSitesData, int $targetYearId): array
    {
        $mapping = [];
        foreach ($workSitesData as $ws) {
            $newWs = new WorkSite;
            $newWs->fill(collect($ws)->except(['id'])->toArray());
            $newWs->company_id = $targetYearId;
            $newWs->save();
            $mapping[$ws['id']] = $newWs->id;
        }

        return $mapping;
    }

    /**
     * Import Payroll Elements.
     *
     * @return array<int, int> Mapping of old payroll element ID to new payroll element ID.
     */
    protected static function _importPayrollElements(array $payrollElementsData, int $targetYearId): array
    {
        $mapping = [];
        foreach ($payrollElementsData as $pe) {
            $newPe = new PayrollElement;
            $newPe->fill(collect($pe)->except(['id'])->toArray());
            $newPe->company_id = $targetYearId;
            $newPe->save();
            $mapping[$pe['id']] = $newPe->id;
        }

        return $mapping;
    }

    /**
     * Import Employees.
     *
     * @param  array  $orgChartMapping  Mapping of old org chart ID to new org chart ID.
     * @param  array  $workSiteMapping  Mapping of old work site ID to new work site ID.
     * @param  array  $workShiftMapping  Mapping of old work shift ID to new work shift ID.
     * @param  array  $work_site_contracts  Mapping of old contract framwork ID to new contract framwork ID.
     * @return array<int, int> Mapping of old employee ID to new employee ID.
     */
    protected static function _importEmployees(array $employeesData, int $targetYearId, array $orgChartMapping, array $workSiteMapping, array $workShiftMapping, array $work_site_contracts): array
    {
        $mapping = [];
        foreach ($employeesData as $employeeData) {
            $oldOrgChartId = $employeeData['org_chart_id'] ?? null;
            $oldWorkSiteId = $employeeData['work_site_id'] ?? null;
            $oldWorkShiftId = $employeeData['work_shift_id'] ?? null;
            $oldContractFrameworkId = $employeeData['contract_framework_id'] ?? null;

            // if ($oldOrgChartId === null || ! isset($orgChartMapping[$oldOrgChartId])) {
            //     Log::warning('Skipping employee import due to missing org chart mapping.', ['old_employee_id' => $employeeData['id'] ?? 'N/A', 'old_org_chart_id' => $oldOrgChartId, 'target_year_id' => $targetYearId]);

            //     continue;
            // }
            // if ($oldWorkSiteId === null || ! isset($workSiteMapping[$oldWorkSiteId])) {
            //     Log::warning('Skipping employee import due to missing work site mapping.', ['old_employee_id' => $employeeData['id'] ?? 'N/A', 'old_work_site_id' => $oldWorkSiteId, 'target_year_id' => $targetYearId]);

            //     continue;
            // }
            // if ($oldWorkShiftId === null || ! isset($workShiftMapping[$oldWorkShiftId])) {
            //     Log::warning('Skipping employee import due to missing work shift mapping.', ['old_employee_id' => $employeeData['id'] ?? 'N/A', 'old_work_shift_id' => $oldWorkShiftId, 'target_year_id' => $targetYearId]);

            //     continue;
            // }
            // if ($oldContractFrameworkId === null || ! isset($work_site_contracts[$oldContractFrameworkId])) {
            //     Log::warning('Skipping employee import due to missing contract framework mapping.', ['old_employee_id' => $employeeData['id'] ?? 'N/A', 'old_contract_framework_id' => $oldContractFrameworkId, 'target_year_id' => $targetYearId]);

            //     continue;
            // }

            $newEmp = new Employee;
            $newEmp->fill(collect($employeeData)->except(['id', 'org_chart_id', 'work_site_id', 'work_shift_id', 'contract_framework_id'])->toArray());
            $newEmp->company_id = $targetYearId;
            $newEmp->org_chart_id = $orgChartMapping[$oldOrgChartId] ?? null;
            $newEmp->work_site_id = $workSiteMapping[$oldWorkSiteId] ?? null;
            $newEmp->work_shift_id = $workShiftMapping[$oldWorkShiftId] ?? null;
            $newEmp->contract_framework_id = $work_site_contracts[$oldContractFrameworkId] ?? null;
            $newEmp->save();

            $mapping[$employeeData['id']] = $newEmp->id;
        }

        return $mapping;
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
     * @return array<int, int> Mapping of old bank account ID to new bank account ID.
     */
    protected static function _importBankAccounts(array $bankAccountsData, int $targetYearId, array $bankMapping): array
    {
        $mapping = [];
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

            $mapping[$accountData['id']] = $newAccount->id;
        }

        self::_syncSubjectsRelation($mapping, 'bankAccount', 'subject_id');

        return $mapping;
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

            $mapping[$groupData['id']] = $newGroup->id;
        }

        self::_syncSubjectsRelation($mapping, 'customerGroup', 'subject_id');

        return $mapping;
    }

    /**
     * Import Customers.
     *
     * @param  array  $groupMapping  Mapping of old customer group ID to new group ID.
     * @param  array  $subjectMapping  Mapping of old subject ID to new subject ID.
     * @return array<int, int> Mapping of old customer ID to new customer ID.
     */
    protected static function _importCustomers(array $customersData, int $targetYearId, array $groupMapping, array $subjectMapping): array
    {
        $mapping = [];
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

            $oldIntroducerId = $customerData['introducer_id'] ?? null;

            $newCustomer = new Customer;
            $newCustomer->fill(collect($customerData)->except(['id', 'group_id', 'subject_id', 'company_id', 'introducer_id'])->toArray());
            $newCustomer->company_id = $targetYearId;
            $newCustomer->introducer_id = $oldIntroducerId ? $mapping[$oldIntroducerId] : null;
            $newCustomer->group_id = $groupMapping[$oldGroupId];
            $newCustomer->subject_id = $subjectMapping[$oldSubjectId];
            $newCustomer->saveQuietly();

            $mapping[$customerData['id']] = $newCustomer->id;
        }

        self::_syncSubjectsRelation($mapping, 'customer', 'subject_id');

        return $mapping;
    }

    /**
     * Import Comments.
     *
     * @param  array  $customerMapping  Mapping of old customer ID to new customer ID.
     */
    protected static function _importComments(array $commentsData, array $customerMapping): void
    {
        foreach ($commentsData as $commentData) {
            $oldCustomerId = $commentData['customer_id'] ?? null;
            if ($oldCustomerId === null || ! isset($customerMapping[$oldCustomerId])) {
                Log::warning('Skipping comment import due to missing customer mapping.', ['old_comment_id' => $commentData['id'] ?? 'N/A', 'old_customer_id' => $oldCustomerId]);

                continue;
            }

            $newComment = new Comment;
            $newComment->fill(collect($commentData)->except(['id', 'customer_id'])->toArray());
            $newComment->customer_id = $customerMapping[$oldCustomerId];
            $newComment->save();
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
        foreach ($groupsData as $groupData) {
            $oldSubjectId = $groupData['subject_id'] ?? null;
            $oldCogsSubjectId = $groupData['cogs_subject_id'] ?? null;
            $oldSalesReturnsSubjectId = $groupData['sales_returns_subject_id'] ?? null;

            if ($oldSubjectId === null || ! isset($subjectMapping[$oldSubjectId])) {
                Log::warning('Skipping service group import due to missing subject mapping.', ['old_group_id' => $groupData['id'] ?? 'N/A', 'old_subject_id' => $oldSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldCogsSubjectId === null || ! isset($subjectMapping[$oldCogsSubjectId])) {
                Log::warning('Skipping service group import due to missing Cogs subject mapping.', ['old_group_id' => $groupData['id'] ?? 'N/A', 'old_cogs_subject_id' => $oldCogsSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldSalesReturnsSubjectId === null || ! isset($subjectMapping[$oldSalesReturnsSubjectId])) {
                Log::warning('Skipping service group import due to missing sales returns subject mapping.', ['old_group_id' => $groupData['id'] ?? 'N/A', 'old_sales_returns_subject_id' => $oldSalesReturnsSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newGroup = new ServiceGroup;
            $newGroup->fill(collect($groupData)->except(['id', 'subject_id', 'cogs_subject_id', 'sales_returns_subject_id'])->toArray());
            $newGroup->company_id = $targetYearId;
            $newGroup->subject_id = $subjectMapping[$oldSubjectId];
            $newGroup->cogs_subject_id = $subjectMapping[$oldCogsSubjectId];
            $newGroup->sales_returns_subject_id = $subjectMapping[$oldSalesReturnsSubjectId];
            $newGroup->save();

            $mapping[$groupData['id']] = $newGroup->id;
        }

        $subjectColumns = ['subject_id', 'cogs_subject_id', 'sales_returns_subject_id'];
        foreach ($subjectColumns as $subjectColumn) {
            self::_syncSubjectsRelation($mapping, 'serviceGroup', $subjectColumn);
        }

        return $mapping;
    }

    /**
     * Import Services.
     *
     * @param  array  $groupMapping  Mapping of old service group ID to new group ID.
     * @param  array  $subjectMapping  Mapping of old subject ID to new subject ID.
     * @return array<int, int> Mapping of old service ID to new service ID.
     */
    protected static function _importServices(array $servicesData, int $targetYearId, array $groupMapping, array $subjectMapping): array
    {
        $mapping = [];
        foreach ($servicesData as $serviceData) {
            $oldGroupId = $serviceData['group'] ?? null;
            if ($oldGroupId === null || ! isset($groupMapping[$oldGroupId])) {
                Log::warning('Skipping service import due to missing group mapping.', ['old_service_id' => $serviceData['id'] ?? 'N/A', 'old_group_id' => $oldGroupId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $oldSubjectId = $serviceData['subject_id'] ?? null;
            $oldCogsSubjectId = $serviceData['cogs_subject_id'] ?? null;
            $oldSalesReturnsSubjectId = $serviceData['sales_returns_subject_id'] ?? null;

            if ($oldSubjectId === null || ! isset($subjectMapping[$oldSubjectId])) {
                Log::warning('Skipping service import due to missing subject mapping.', ['old_service_id' => $serviceData['id'] ?? 'N/A', 'old_subject_id' => $oldSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldCogsSubjectId === null || ! isset($subjectMapping[$oldCogsSubjectId])) {
                Log::warning('Skipping service import due to missing Cogs subject mapping.', ['old_service_id' => $serviceData['id'] ?? 'N/A', 'old_cogs_subject_id' => $oldCogsSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldSalesReturnsSubjectId === null || ! isset($subjectMapping[$oldSalesReturnsSubjectId])) {
                Log::warning('Skipping service import due to missing sales returns subject mapping.', ['old_service_id' => $serviceData['id'] ?? 'N/A', 'old_sales_returns_subject_id' => $oldSalesReturnsSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newService = new Service;
            $newService->fill(collect($serviceData)->except(['id', 'group', 'subject_id', 'cogs_subject_id', 'sales_returns_subject_id'])->toArray());
            $newService->group = $groupMapping[$oldGroupId];
            $newService->subject_id = $subjectMapping[$oldSubjectId];
            $newService->cogs_subject_id = $subjectMapping[$oldCogsSubjectId];
            $newService->sales_returns_subject_id = $subjectMapping[$oldSalesReturnsSubjectId];
            $newService->company_id = $targetYearId;
            $newService->save();

            $mapping[$serviceData['id']] = $newService->id;
        }

        $subjectColumns = ['subject_id', 'cogs_subject_id', 'sales_returns_subject_id'];
        foreach ($subjectColumns as $subjectColumn) {
            self::_syncSubjectsRelation($mapping, 'service', $subjectColumn);
        }

        return $mapping;
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
            $oldSalesReturnsSubjectId = $groupData['sales_returns_subject_id'] ?? null;
            $oldIncomeSubjectId = $groupData['income_subject_id'] ?? null;
            $oldCogsSubjectId = $groupData['cogs_subject_id'] ?? null;
            $oldInventorySubjectId = $groupData['inventory_subject_id'] ?? null;

            if ($oldSalesReturnsSubjectId === null || ! isset($subjectMapping[$oldSalesReturnsSubjectId])) {
                Log::warning('Skipping product group import due to missing sales returns subject mapping.', ['old_group_id' => $groupData['id'] ?? 'N/A', 'old_sales_returns_subject_id' => $oldSalesReturnsSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldIncomeSubjectId === null || ! isset($subjectMapping[$oldIncomeSubjectId])) {
                Log::warning('Skipping product group import due to missing income subject mapping.', ['old_group_id' => $groupData['id'] ?? 'N/A', 'old_income_subject_id' => $oldIncomeSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldCogsSubjectId === null || ! isset($subjectMapping[$oldCogsSubjectId])) {
                Log::warning('Skipping product group import due to missing Cogs subject mapping.', ['old_group_id' => $groupData['id'] ?? 'N/A', 'old_cogs_subject_id' => $oldCogsSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldInventorySubjectId === null || ! isset($subjectMapping[$oldInventorySubjectId])) {
                Log::warning('Skipping product group import due to missing inventory subject mapping.', ['old_group_id' => $groupData['id'] ?? 'N/A', 'old_inventory_subject_id' => $oldInventorySubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newGroup = new ProductGroup;
            $newGroup->fill(collect($groupData)->except(['id', 'sales_returns_subject_id', 'income_subject_id', 'cogs_subject_id', 'inventory_subject_id'])->toArray());
            $newGroup->company_id = $targetYearId;
            $newGroup->sales_returns_subject_id = $subjectMapping[$oldSalesReturnsSubjectId];
            $newGroup->income_subject_id = $subjectMapping[$oldIncomeSubjectId];
            $newGroup->cogs_subject_id = $subjectMapping[$oldCogsSubjectId];
            $newGroup->inventory_subject_id = $subjectMapping[$oldInventorySubjectId];
            $newGroup->save();

            $mapping[$groupData['id']] = $newGroup->id;
        }

        $subjectColumns = ['sales_returns_subject_id', 'income_subject_id', 'cogs_subject_id', 'inventory_subject_id'];
        foreach ($subjectColumns as $subjectColumn) {
            self::_syncSubjectsRelation($mapping, 'productGroup', $subjectColumn);
        }

        return $mapping;
    }

    /**
     * Import Products.
     *
     * @param  array  $groupMapping  Mapping of old product group ID to new group ID.
     * @param  array  $subjectMapping  Mapping of old subject ID to new subject ID.
     * @return array<int, int> Mapping of old product ID to new product ID.
     */
    protected static function _importProducts(array $productsData, int $targetYearId, array $groupMapping, array $subjectMapping): array
    {
        $mapping = [];
        foreach ($productsData as $productData) {
            $oldGroupId = $productData['group'] ?? null;
            if ($oldGroupId === null || ! isset($groupMapping[$oldGroupId])) {
                Log::warning('Skipping product import due to missing group mapping.', ['old_product_id' => $productData['id'] ?? 'N/A', 'old_group_id' => $oldGroupId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $oldSalesReturnsSubjectId = $productData['sales_returns_subject_id'] ?? null;
            $oldIncomeSubjectId = $productData['income_subject_id'] ?? null;
            $oldCogsSubjectId = $productData['cogs_subject_id'] ?? null;
            $oldInventorySubjectId = $productData['inventory_subject_id'] ?? null;

            if ($oldSalesReturnsSubjectId === null || ! isset($subjectMapping[$oldSalesReturnsSubjectId])) {
                Log::warning('Skipping product import due to missing sales returns subject mapping.', ['old_product_id' => $productData['id'] ?? 'N/A', 'old_sales_returns_subject_id' => $oldSalesReturnsSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldIncomeSubjectId === null || ! isset($subjectMapping[$oldIncomeSubjectId])) {
                Log::warning('Skipping product import due to missing income subject mapping.', ['old_product_id' => $productData['id'] ?? 'N/A', 'old_income_subject_id' => $oldIncomeSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldCogsSubjectId === null || ! isset($subjectMapping[$oldCogsSubjectId])) {
                Log::warning('Skipping product import due to missing Cogs subject mapping.', ['old_product_id' => $productData['id'] ?? 'N/A', 'old_cogs_subject_id' => $oldCogsSubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldInventorySubjectId === null || ! isset($subjectMapping[$oldInventorySubjectId])) {
                Log::warning('Skipping product import due to missing inventory subject mapping.', ['old_product_id' => $productData['id'] ?? 'N/A', 'old_inventory_subject_id' => $oldInventorySubjectId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newProduct = new Product;
            $newProduct->fill(collect($productData)->except(['id', 'group', 'sales_returns_subject_id', 'income_subject_id', 'cogs_subject_id', 'inventory_subject_id'])->toArray());
            $newProduct->group = $groupMapping[$oldGroupId];
            $newProduct->sales_returns_subject_id = $subjectMapping[$oldSalesReturnsSubjectId];
            $newProduct->income_subject_id = $subjectMapping[$oldIncomeSubjectId];
            $newProduct->cogs_subject_id = $subjectMapping[$oldCogsSubjectId];
            $newProduct->inventory_subject_id = $subjectMapping[$oldInventorySubjectId];
            $newProduct->company_id = $targetYearId;
            $newProduct->save();

            $mapping[$productData['id']] = $newProduct->id;
        }

        $subjectColumns = ['sales_returns_subject_id', 'income_subject_id', 'cogs_subject_id', 'inventory_subject_id'];
        foreach ($subjectColumns as $subjectColumn) {
            self::_syncSubjectsRelation($mapping, 'product', $subjectColumn);
        }

        return $mapping;
    }

    /**
     * Import Product websites.
     *
     * @param  array  $productMapping  Mapping of old product ID to new product ID.
     */
    protected static function _importProductWebsites(array $productWebsitesData, array $productMapping): void
    {
        foreach ($productWebsitesData as $productWebsiteData) {
            $oldProductId = $productWebsiteData['product_id'] ?? null;
            if ($oldProductId === null || ! isset($productMapping[$oldProductId])) {
                Log::warning('Skipping product website import due to missing product mapping.', ['old_product_website_id' => $productWebsiteData['id'] ?? 'N/A', 'old_product_id' => $oldProductId]);

                continue;
            }

            $newProductWebsite = new ProductWebsite;
            $newProductWebsite->fill(collect($productWebsiteData)->except(['id', 'product_id'])->toArray());
            $newProductWebsite->product_id = $productMapping[$oldProductId];
            $newProductWebsite->save();
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
     * @param  array  $documentMapping  Mapping of old document ID to new document ID.
     * @param  array  $subjectMapping  Mapping of old subject ID to new subject ID.
     * @return array<int, int> Mapping of old transaction ID to new transaction ID.
     */
    protected static function _importTransactions(array $transactionsData, int $targetYearId, array $documentMapping, array $subjectMapping): array
    {
        $mapping = [];
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

            $mapping[$transData['id']] = $newTrans->id;
        }

        return $mapping;
    }

    /**
     * Import Document files.
     *
     * @param  array  $documentMapping  Mapping of old document ID to new document ID.
     */
    protected static function _importDocumentFiles(array $documentFilesData, int $targetYearId, array $documentMapping): void
    {
        foreach ($documentFilesData as $documentFileData) {
            $oldDocFileId = $documentFileData['document_id'] ?? null;
            $oldUserId = $documentFileData['user_id'] ?? null;

            if ($oldDocFileId === null || ! isset($documentMapping[$oldDocFileId])) {
                Log::warning('Skipping document file import due to missing document mapping.', ['old_document_file_id' => $documentFileData['id'] ?? 'N/A', 'old_document_id' => $oldDocFileId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newFile = new DocumentFile;
            $newFile->fill(collect($documentFileData)->except(['id', 'document_id', 'user_id'])->toArray());
            $newFile->document_id = $documentMapping[$oldDocFileId];
            $newFile->user_id = $oldUserId;

            $newFile->save();
        }
    }

    /**
     * Import Cheques.
     *
     * @param  array  $customerMapping  Mapping of old customer ID to new customer ID.
     * @param  array  $transactionMapping  Mapping of old transaction ID to new transaction ID.
     * @param  array  $bankAccountMapping  Mapping of old bank account ID to new bank account ID.
     * @return array<int, int> Mapping of old cheque ID to new cheque ID.
     */
    protected static function _importCheques(array $chequesData, int $targetYearId, array $customerMapping, array $transactionMapping, array $bankAccountMapping): array
    {
        $mapping = [];
        foreach ($chequesData as $chequeData) {
            $oldCustomerId = $chequeData['customer_id'] ?? null;
            $oldTransactionId = $chequeData['transaction_id'] ?? null;
            $oldBankAccountId = $chequeData['account_id'] ?? null;

            if ($oldCustomerId === null || ! isset($customerMapping[$oldCustomerId])) {
                Log::warning('Skipping cheque import due to missing customer mapping.', ['old_cheque_id' => $chequeData['id'] ?? 'N/A', 'old_customer_id' => $oldCustomerId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldTransactionId === null || ! isset($transactionMapping[$oldTransactionId])) {
                Log::warning('Skipping cheque import due to missing transaction mapping.', ['old_cheque_id' => $chequeData['id'] ?? 'N/A', 'old_transaction_id' => $oldTransactionId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldBankAccountId === null || ! isset($bankAccountMapping[$oldBankAccountId])) {
                Log::warning('Skipping cheque import due to missing bank account mapping.', ['old_cheque_id' => $chequeData['id'] ?? 'N/A', 'old_bank_account_id' => $oldBankAccountId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newCheque = new Cheque;
            $newCheque->fill(collect($chequeData)->except(['id', 'customer_id', 'transaction_id', 'account_id'])->toArray());
            $newCheque->transaction_id = $transactionMapping[$oldTransactionId];
            $newCheque->account_id = $bankAccountMapping[$oldBankAccountId];
            $newCheque->company_id = $targetYearId;
            $newCheque->save();

            $mapping[$chequeData['id']] = $newCheque->id;
        }

        return $mapping;
    }

    /**
     * Import Cheque Histories.
     *
     * @param  array  $chequeMapping  Mapping of old cheque ID to new cheque ID.
     * @param  array  $customerMapping  Mapping of old customer ID to new customer ID.
     * @param  array  $transactionMapping  Mapping of old transaction ID to new transaction ID.
     * @param  array  $bankAccountMapping  Mapping of old bank account ID to new bank account ID.
     */
    protected static function _importChequeHistories(array $chequeHistoriesData, int $targetYearId, array $chequeMapping, array $customerMapping, array $transactionMapping, array $bankAccountMapping): void
    {
        foreach ($chequeHistoriesData as $chequeHistoriyData) {
            $oldChequeId = $chequeHistoriyData['cheque_id'] ?? null;
            $oldCustomerId = $chequeHistoriyData['customer_id'] ?? null;
            $oldTransactionId = $chequeHistoriyData['transaction_id'] ?? null;
            $oldBankAccountId = $chequeHistoriyData['account_id'] ?? null;

            if ($oldChequeId === null || ! isset($chequeMapping[$oldChequeId])) {
                Log::warning('Skipping cheque history import due to missing cheque mapping.', ['old_cheque_history_id' => $chequeHistoriyData['id'] ?? 'N/A', 'old_cheque_id' => $oldChequeId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldCustomerId === null || ! isset($customerMapping[$oldCustomerId])) {
                Log::warning('Skipping cheque history import due to missing customer mapping.', ['old_cheque_history_id' => $chequeHistoriyData['id'] ?? 'N/A', 'old_customer_id' => $oldCustomerId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldTransactionId === null || ! isset($transactionMapping[$oldTransactionId])) {
                Log::warning('Skipping cheque history import due to missing transaction mapping.', ['old_cheque_history_id' => $chequeHistoriyData['id'] ?? 'N/A', 'old_transaction_id' => $oldTransactionId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldBankAccountId === null || ! isset($bankAccountMapping[$oldBankAccountId])) {
                Log::warning('Skipping cheque history import due to missing bank account mapping.', ['old_cheque_history_id' => $chequeHistoriyData['id'] ?? 'N/A', 'old_bank_account_id' => $oldBankAccountId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newChequeHistory = new ChequeHistory;
            $newChequeHistory->fill(collect($chequeHistoriyData)->except(['id', 'customer_id', 'cheque_id', 'transaction_id', 'account_id'])->toArray());
            $newChequeHistory->cheque_id = $chequeMapping[$oldChequeId];
            $newChequeHistory->customer_id = $customerMapping[$oldCustomerId];
            $newChequeHistory->transaction_id = $transactionMapping[$oldTransactionId];
            $newChequeHistory->account_id = $bankAccountMapping[$oldBankAccountId];
            $newChequeHistory->company_id = $targetYearId;
            $newChequeHistory->save();
        }
    }

    /**
     * Import Invoices.
     *
     * @param  array  $documentMapping  Mapping of old document ID to new document ID.
     * @param  array  $customerMapping  Mapping of old customer ID to new customer ID.
     * @return array<int, int> Mapping of old invoice ID to new invoice ID.
     */
    protected static function _importInvoices(array $invoicesData, int $targetYearId, array $documentMapping, array $customerMapping): array
    {
        $mapping = [];
        foreach ($invoicesData as $invoiceData) {
            if ($invoiceData['returned_invoice_id'] !== null) { // skip returned invoices in first loops
                continue;
            }

            $oldCustomerId = $invoiceData['customer_id'] ?? null;
            if ($oldCustomerId === null || ! isset($customerMapping[$oldCustomerId])) {
                Log::warning('Skipping invoice import due to missing customer mapping.', ['old_invoice_id' => $invoiceData['id'] ?? 'N/A', 'old_customer_id' => $oldCustomerId, 'target_year_id' => $targetYearId]);

                continue;
            }
            $oldDocumentId = $invoiceData['document_id'] ?? null;

            $newInvoice = new Invoice;
            $newInvoice->fill(collect($invoiceData)->except(['id', 'customer_id', 'document_id'])->toArray());
            $newInvoice->customer_id = $customerMapping[$oldCustomerId];
            $newInvoice->document_id = $oldDocumentId ? $documentMapping[$oldDocumentId] : null;
            $newInvoice->save();

            $mapping[$invoiceData['id']] = $newInvoice->id;
        }

        foreach ($invoicesData as $invoiceData) {
            if ($invoiceData['returned_invoice_id'] === null) { // this loop is for returned invoices
                continue;
            }
            $oldDocumentId = $invoiceData['document_id'] ?? null;
            $oldCustomerId = $invoiceData['customer_id'] ?? null;
            $oldReturnedInvoiceId = $invoiceData['returned_invoice_id'];

            if ($oldCustomerId === null || ! isset($customerMapping[$oldCustomerId])) {
                Log::warning('Skipping invoice import due to missing customer mapping.', ['old_invoice_id' => $invoiceData['id'] ?? 'N/A', 'old_customer_id' => $oldCustomerId, 'target_year_id' => $targetYearId]);

                continue;
            }

            if ($oldReturnedInvoiceId === null || ! isset($mapping[$oldReturnedInvoiceId])) {
                Log::warning('Skipping returned invoice import due to missing related invoice mapping.', ['old_invoice_id' => $invoiceData['id'] ?? 'N/A', 'old_returned_invoice_id' => $oldReturnedInvoiceId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $newInvoice = new Invoice;
            $newInvoice->fill(collect($invoiceData)->except(['id', 'customer_id', 'document_id', 'returned_invoice_id'])->toArray());
            $newInvoice->customer_id = $customerMapping[$oldCustomerId];
            $newInvoice->returned_invoice_id = $mapping[$oldReturnedInvoiceId];
            $newInvoice->document_id = $oldDocumentId ? $documentMapping[$oldDocumentId] : null;
            $newInvoice->save();

            $mapping[$invoiceData['id']] = $newInvoice->id;
        }

        self::_syncDocumentsRelation($mapping, 'invoice');

        return $mapping;
    }

    /**
     * Import Invoice items.
     *
     * @param  array  $invoiceMapping  Mapping of old invoice ID to new invoice ID.
     * @param  array  $productMapping  Mapping of old product ID to new product ID.
     * @param  array  $serviceMapping  Mapping of old service ID to new service ID.
     */
    protected static function _importInvoiceItems(array $invoiceitemsData, array $invoiceMapping, array $productMapping, array $serviceMapping): void
    {
        foreach ($invoiceitemsData as $invoiceItemData) {
            $oldInvoiceId = $invoiceItemData['invoice_id'] ?? null;
            if ($oldInvoiceId === null || ! isset($invoiceMapping[$oldInvoiceId])) {
                Log::warning('Skipping invoice item import due to missing invoice mapping.', ['old_invoice_item_id' => $invoiceItemData['id'] ?? 'N/A', 'old_invoice_id' => $oldInvoiceId]);

                continue;
            }

            $oldItemableId = $invoiceItemData['itemable_id'] ?? null;
            $productCondition = in_array($invoiceItemData['itemable_type'], [Product::class, 'product']) && isset($productMapping[$oldItemableId]);
            $serviceCondition = in_array($invoiceItemData['itemable_type'], [Service::class, 'service']) && isset($serviceMapping[$oldItemableId]);
            if (! $productCondition && ! $serviceCondition) {
                Log::warning('Skipping invoice item import due to missing product or service mapping.',
                    [
                        'old_invoice_item_id' => $invoiceItemData['id'] ?? 'N/A',
                        'old_itemable_id' => $oldItemableId,
                        'item_type' => in_array($invoiceItemData['itemable_type'], [Product::class, 'product']) ? 'product' : 'service',
                    ]);

                continue;
            }

            $newInvoiceItem = new InvoiceItem;
            $newInvoiceItem->fill(collect($invoiceItemData)->except(['id', 'invoice_id', 'itemable_id'])->toArray());
            $newInvoiceItem->invoice_id = $invoiceMapping[$oldInvoiceId];
            $newInvoiceItem->itemable_id = in_array($invoiceItemData['itemable_type'], [Product::class, 'product']) ? $productMapping[$oldItemableId] : $serviceMapping[$oldItemableId];
            $newInvoiceItem->save();
        }
    }

    /**
     * Import Ancillary Costs.
     *
     * @param  array  $invoiceMapping  Mapping of old invoice ID to new invoice ID.
     * @param  array  $customerMapping  Mapping of old customer ID to new customer ID.
     * @param  array  $documentMapping  Mapping of old document ID to new document ID.
     * @return array<int, int> Mapping of old ancillary cost ID to new ancillary cost ID.
     */
    protected static function _importAncillaryCosts(array $ancillaryCostsData, int $targetYearId, array $invoiceMapping, array $customerMapping, array $documentMapping): array
    {
        $mapping = [];
        foreach ($ancillaryCostsData as $ancillaryCostData) {
            $oldInvoiceId = $ancillaryCostData['invoice_id'] ?? null;
            if ($oldInvoiceId === null || ! isset($invoiceMapping[$oldInvoiceId])) {
                Log::warning('Skipping ancillary cost import due to missing invoice mapping.', ['old_ancillary_cost_id' => $ancillaryCostData['id'] ?? 'N/A', 'old_invoice_id' => $oldInvoiceId, 'target_year_id' => $targetYearId]);

                continue;
            }

            $oldCustomerId = $ancillaryCostData['customer_id'] ?? null;
            if ($oldCustomerId === null || ! isset($customerMapping[$oldCustomerId])) {
                Log::warning('Skipping  ancillary cost import due to missing customer mapping.', ['old_ancillary_cost_id' => $ancillaryCostData['id'] ?? 'N/A', 'old_customer_id' => $oldCustomerId, 'target_year_id' => $targetYearId]);

                continue;
            }
            $oldDocumentId = $ancillaryCostData['document_id'] ?? null;

            $newAncillaryCost = new AncillaryCost;
            $newAncillaryCost->fill(collect($ancillaryCostData)->except(['id', 'invoice_id', 'document_id', 'customer_id'])->toArray());
            $newAncillaryCost->invoice_id = $invoiceMapping[$oldInvoiceId];
            $newAncillaryCost->document_id = $oldDocumentId && isset($documentMapping[$oldDocumentId]) ? $documentMapping[$oldDocumentId] : null;
            $newAncillaryCost->customer_id = $customerMapping[$oldCustomerId];
            $newAncillaryCost->save();

            $mapping[$ancillaryCostData['id']] = $newAncillaryCost->id;
        }

        self::_syncDocumentsRelation($mapping, 'ancillaryCost');

        return $mapping;
    }

    /**
     * Import Invoice items.
     *
     * @param  array  $ancillaryCostMapping  Mapping of old ancillary cost ID to new ancillary cost ID.
     * @param  array  $productMapping  Mapping of old product ID to new product ID.
     */
    protected static function _importAncillaryCostItems(array $ancillaryCostItemsData, array $ancillaryCostMapping, array $productMapping): void
    {
        foreach ($ancillaryCostItemsData as $ancillaryCostItemData) {
            $oldAncillaryCostId = $ancillaryCostItemData['ancillary_cost_id'] ?? null;
            if ($oldAncillaryCostId === null || ! isset($ancillaryCostMapping[$oldAncillaryCostId])) {
                Log::warning('Skipping ancillary cost item import due to missing ancillary cost mapping.', ['old_ancillary_cost_item_id' => $ancillaryCostItemData['id'] ?? 'N/A', 'old_ancillary_cost_id' => $oldAncillaryCostId]);

                continue;
            }

            $oldProductId = $ancillaryCostItemData['product_id'] ?? null;
            if ($oldProductId === null || ! isset($productMapping[$oldProductId])) {
                Log::warning('Skipping ancillary cost item import due to missing product mapping.', ['old_ancillary_cost_item_id' => $ancillaryCostItemData['id'] ?? 'N/A', 'old_product_id' => $oldProductId]);

                continue;
            }

            $newAncillaryCostItem = new AncillaryCostItem;
            $newAncillaryCostItem->fill(collect($ancillaryCostItemData)->except(['id', 'ancillary_cost_id', 'product_id'])->toArray());
            $newAncillaryCostItem->ancillary_cost_id = $ancillaryCostMapping[$oldAncillaryCostId];
            $newAncillaryCostItem->product_id = $productMapping[$oldProductId];
            $newAncillaryCostItem->save();
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

        // $unbalancedDocsCount = Document::where('company_id', $company->id)->has('transactions')
        //     ->withSum('transactions', 'value')->having('transactions_sum_value', '!=', 0);

        // if ($unbalancedDocsCount->count() > 0) {
        //     $errors[] = __('Cannot close fiscal year with unbalanced documents. Please ensure all documents are balanced before closing the year.');
        // }

        return $errors;
    }

    protected static function balanceCurrentProfitAndLoss(Company $company, Collection $transactions, User $user): Collection
    {
        $difference = (float) $transactions->sum('value');

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

            $transaction = [
                'subject_id' => $subject->id, // For balancing the opening account
                'value' => -1 * $difference,
                'user_id' => $user->id,
            ];

            $transactions->push($transaction);
        }

        return $transactions;
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

    protected static function createClosingDocument(Company $company, User $user): Document
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

        // Gather net balances of all permanent accounts across the entire fiscal year.
        // Temporary accounts are already zeroed out by Step 1 (currentProfitAndLoss),
        // so we only need permanent subjects here.
        $transactions = Transaction::query()
            ->whereHas('document', fn ($doc) => $doc->where('company_id', $company->id))
            ->whereHas('subject', fn ($sub) => $sub->where('company_id', $company->id)->where('is_permanent', true))
            ->selectRaw('subject_id, SUM(value) as value')
            ->groupBy('subject_id')
            ->havingRaw('SUM(value) != 0')
            ->get()
            ->map(fn ($row) => [
                'subject_id' => $row->subject_id,
                'value' => -1 * $row->value,
                'user_id' => $user->id,
                'desc' => __('Fiscal year closing Document').' '.$company->fiscal_year,
            ])->toArray();

        return DocumentService::createDocument($user, $documentData, $transactions);
    }

    protected static function newFiscalYear(Company $company, User $user): Company
    {
        $newFiscalYearData = collect($company->getAttributes())->except(['id', 'closed_at', 'fiscal_year'])
            ->merge(['fiscal_year' => $company->fiscal_year + 1])->toArray();

        $sectionsToCopy = ['subjects', 'configs', 'banks', 'customers', 'products', 'services', 'employees']; // Sections to copy to the new fiscal year
        $newFiscalYear = self::createWithCopiedData($newFiscalYearData, $company->id, $sectionsToCopy);
        $newFiscalYear->users()->attach($user->id);

        return $newFiscalYear;
    }

    /**
     * Step 1 – Close temporary (income/expense) accounts into the Income Summary account.
     * Saves the resulting document ID onto the company as `pl_document_id`.
     */
    public static function stepOneCloseTemporaryAccounts(Company $company, User $user): Document
    {
        $document = DB::transaction(function () use ($company, $user) {
            $doc = self::currentProfitAndLoss($company, $user);

            // Persist the link so the wizard can track progress
            $company->pl_document_id = $doc->id;
            $company->save();

            return $doc;
        });

        return $document;
    }

    /**
     * Step 3 – Close permanent accounts, create the new fiscal year, and generate the opening document.
     * Requires Step 1 to have been completed and the Income Summary balance to be exactly 0.
     *
     * @throws \Exception
     */
    public static function stepThreeCloseAndOpenNewYear(Company $company, User $user): Company
    {
        $newFiscalYear = null;

        DB::transaction(function () use ($company, $user, &$newFiscalYear) {
            $closeDocument = self::createClosingDocument($company, $user);

            $company->closing_document_id = $closeDocument->id;
            $company->closed_at = now();
            $company->closed_by = $user->id;
            $company->save();

            $newFiscalYear = self::newFiscalYear($company, $user);

            self::createOpeningDocument($newFiscalYear, $closeDocument, $user);
        });

        return $newFiscalYear;
    }

    /**
     * Returns the current balance of the Income Summary (خلاصه سود و زیان جاری) subject.
     * Used to gate Step 3: balance must be 0 before closing permanent accounts.
     */
    public static function getIncomeSummaryBalance(Company $company): float
    {
        $subject = Subject::where('company_id', $company->id)
            ->where('name', __('Current Profit and Loss Summary'))
            ->first();

        if (! $subject) {
            return 0.0;
        }

        return (float) Transaction::query()
            ->whereHas('document', fn ($q) => $q->where('company_id', $company->id))
            ->where('subject_id', $subject->id)
            ->sum('value');
    }

    /**
     * Pre-flight validations for initiating the year-end wizard.
     * Returns an array of validation checks with pass/fail status.
     *
     * @return array{draft_docs: array, negative_inventory: array, gaps_in_numbers: array}
     */
    public static function getWizardValidations(Company $company): array
    {
        // 1. Draft (unapproved) documents
        $draftCount = Document::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
            ->where('company_id', $company->id)
            ->whereNull('approved_at')
            ->count();

        // 2. Negative inventory
        $negativeInventoryCount = \App\Models\Product::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
            ->where('company_id', $company->id)
            ->where('quantity', '<', 0)
            ->count();

        // 3. Gaps in document numbers
        $numbers = Document::withoutGlobalScope('App\Models\Scopes\FiscalYearScope')
            ->where('company_id', $company->id)
            ->orderBy('number')
            ->pluck('number')
            ->map(fn ($n) => (int) $n)
            ->filter()
            ->values();

        $gapCount = 0;
        for ($i = 1; $i < $numbers->count(); $i++) {
            $gap = $numbers[$i] - $numbers[$i - 1] - 1;
            if ($gap > 0) {
                $gapCount += $gap;
            }
        }

        return [
            'draft_docs' => [
                'label' => __('No unapproved (draft) documents'),
                'pass' => $draftCount === 0,
                'detail' => $draftCount > 0 ? __(':count draft document(s) found.', ['count' => $draftCount]) : null,
            ],
            'negative_inventory' => [
                'label' => __('No negative inventory balances'),
                'pass' => $negativeInventoryCount === 0,
                'detail' => $negativeInventoryCount > 0 ? __(':count product(s) with negative quantity.', ['count' => $negativeInventoryCount]) : null,
            ],
            'gaps_in_numbers' => [
                'label' => __('Document numbers are sequential without gaps'),
                'pass' => $gapCount === 0,
                'detail' => $gapCount > 0 ? __(':count gap(s) in document numbering.', ['count' => $gapCount]) : null,
            ],
        ];
    }

    /**
     * @deprecated Use stepOneCloseTemporaryAccounts / stepThreeCloseAndOpenNewYear instead.
     */
    public static function closeFiscalYear(Company $company, User $user): array
    {
        $newFiscalYear = null;
        $validationErrors = [];

        DB::transaction(function () use ($company, &$newFiscalYear, &$validationErrors, $user) {
            $validationErrors = self::validateClosingFiscalYear($company);

            if (! empty($validationErrors)) {
                return;
            }

            $currentProfitAndLoss = self::currentProfitAndLoss($company, $user);
            $company->pl_document_id = $currentProfitAndLoss->id;

            $closeDocument = self::createClosingDocument($company, $user);
            $company->closing_document_id = $closeDocument->id;

            $newFiscalYear = self::newFiscalYear($company, $user);

            self::createOpeningDocument($newFiscalYear, $closeDocument, $user);

            $company->closed_at = now();
            $company->closed_by = $user->id;
            $company->save();
        });

        return [$newFiscalYear, $validationErrors];
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
            ]);

        // Move any remaining balance in current profit and loss to accumulated profit and loss to balance them
        $transactions = self::balanceCurrentProfitAndLoss($company, $transactions, $user);

        return DocumentService::createDocument($user, $documentData, $transactions->toArray());
    }
}
