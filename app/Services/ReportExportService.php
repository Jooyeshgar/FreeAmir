<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Subject;
use App\Models\Transaction;
use App\Services\DocumentImportExport\DocumentImportExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PDF;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public const EXPORTS = [
        'products_csv' => ['permission' => 'products.export'],
        'warehouse_pdf' => ['permission' => 'products.report'],
        'services_csv' => ['permission' => 'services.export'],
        'customers_csv' => ['permission' => 'customers.export'],
        'invoices_csv' => ['permission' => 'invoices.export'],
        'employees_csv' => ['permission' => 'hr.employees.export'],
        'documents_csv' => ['permission' => 'documents.export'],
        'trial_balance_csv' => ['permission' => 'reports.trial-balance.export-csv'],
        'accounting_report_csv' => ['permission' => 'reports.result'],
        'invoice_pdf' => ['permission' => 'invoices.print'],
    ];

    public function __construct(
        private readonly WarehouseDashboardService $warehouseDashboardService,
        private readonly DocumentImportExportService $documentExportService,
        private readonly TrialBalanceService $trialBalanceService,
    ) {}

    public function generate(string $export, array $filters): array
    {
        if (! isset(self::EXPORTS[$export])) {
            throw ValidationException::withMessages(['export' => __('The selected export is invalid.')]);
        }

        return match ($export) {
            'products_csv' => $this->productsCsv($filters),
            'warehouse_pdf' => $this->warehousePdf($filters),
            'services_csv' => $this->servicesCsv($filters),
            'customers_csv' => $this->customersCsv(),
            'invoices_csv' => $this->invoicesCsv($filters),
            'employees_csv' => $this->employeesCsv($filters),
            'documents_csv' => $this->captureResponse($this->documentExportService->export($this->validateDocumentFilters($filters))),
            'trial_balance_csv' => $this->captureResponse($this->trialBalanceService->exportCsv($this->filterRequest($filters))),
            'accounting_report_csv' => $this->accountingReportCsv($filters),
            'invoice_pdf' => $this->invoicePdf($filters),
        };
    }

    public function downloadResponse(string $export, array $filters): StreamedResponse
    {
        $file = $this->generate($export, $filters);

        return response()->streamDownload(
            fn () => print ($file['content']),
            $file['filename'],
            ['Content-Type' => $file['mime']],
        );
    }

    public function inlineResponse(string $export, array $filters): Response
    {
        $file = $this->generate($export, $filters);

        return response($file['content'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="'.$file['filename'].'"',
        ]);
    }

    private function productsCsv(array $filters): array
    {
        $available = $this->productColumns();
        $validated = Validator::make($filters, [
            'cols_submitted' => ['nullable', 'boolean'], 'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in(array_keys($available))],
        ])->validate();
        $columns = empty($validated['cols_submitted'])
            ? $available
            : array_intersect_key($available, array_flip(array_unique(['name', ...($validated['columns'] ?? [])])));
        $reportColumns = array_intersect(array_keys($this->productReportColumns()), array_keys($columns));
        $reportRows = $this->warehouseDashboardService->report(['cols_submitted' => true, 'columns' => array_values($reportColumns)])['rows']
            ->keyBy(fn (array $row) => (string) $row['code']);

        return $this->csv('products_'.now()->format('YmdHis').'.csv', array_values($columns), function ($file) use ($columns, $reportRows): void {
            Product::with('productGroup', 'incomeSubject', 'cogsSubject', 'inventorySubject', 'salesReturnsSubject')->orderBy('code')->chunk(200,
                function ($products) use ($file, $columns, $reportRows): void {
                    foreach ($products as $product) {
                        $row = array_merge($reportRows->get((string) $product->code, []), [
                            'name' => $product->name, 'category' => $product->productGroup?->name, 'code' => $product->code,
                            'stock' => $product->quantity, 'selling_price' => $product->selling_price, 'cost_of_goods' => $product->average_cost,
                            'income_subject_code' => $product->incomeSubject?->code, 'cogs_subject_code' => $product->cogsSubject?->code,
                            'inventory_subject_code' => $product->inventorySubject?->code, 'sales_returns_subject_code' => $product->salesReturnsSubject?->code,
                            'sstid' => $product->sstid, 'location' => $product->location, 'quantity_warning' => $product->quantity_warning,
                            'oversell' => $product->oversell, 'discount_formula' => $product->discount_formula,
                            'description' => $product->description, 'vat' => $product->vat,
                        ]);
                        fputcsv($file, array_map(fn (string $column) => $row[$column] ?? null, array_keys($columns)));
                    }
                });
        });
    }

    private function warehousePdf(array $filters): array
    {
        $validated = Validator::make($filters, [
            'name' => ['nullable', 'string'], 'group_name' => ['nullable', 'string'], 'min_quantity' => ['nullable', 'numeric'],
            'need_order' => ['nullable', 'boolean'], 'cols_submitted' => ['nullable'], 'columns' => ['nullable', 'array'], 'columns.*' => ['string'],
        ])->validate();
        $data = $this->warehouseDashboardService->report($validated);
        $config = ['format' => 'A4', 'orientation' => $data['portrait'] ? 'P' : 'L', 'directionality' => 'rtl',
            'margin_top' => 28, 'margin_bottom' => 18, 'margin_header' => 6, 'margin_footer' => 6, 'defaultPageNumStyle' => 'persian'];

        return ['content' => PDF::loadView('warehouse.report-pdf', $data, [], $config)->output(),
            'filename' => 'warehouse-report.pdf', 'mime' => 'application/pdf'];
    }

    private function servicesCsv(array $filters): array
    {
        $available = $this->serviceColumns();
        $validated = Validator::make($filters, ['cols_submitted' => ['nullable', 'boolean'], 'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in(array_keys($available))]])->validate();
        $columns = empty($validated['cols_submitted']) ? $available
            : array_intersect_key($available, array_flip(array_unique(['name', ...($validated['columns'] ?? [])])));
        $includeTotals = array_intersect(['revenue_account', 'cogs_account', 'sales_return_account'], array_keys($columns)) !== [];

        return $this->csv('services_'.now()->format('YmdHis').'.csv', array_values($columns), function ($file) use ($columns, $includeTotals): void {
            Service::with('serviceGroup', 'subject', 'cogsSubject', 'salesReturnsSubject')->orderBy('code')->chunk(200,
                function ($services) use ($file, $columns, $includeTotals): void {
                    $totals = $includeTotals ? $this->serviceSubjectTotals($services) : [];
                    foreach ($services as $service) {
                        $row = ['code' => $service->code, 'name' => $service->name, 'selling_price' => $service->selling_price,
                            'vat' => $service->vat, 'group_name' => $service->serviceGroup?->name,
                            'revenue_account' => abs($totals[$service->subject_id] ?? 0), 'cogs_account' => abs($totals[$service->cogs_subject_id] ?? 0),
                            'sales_return_account' => abs($totals[$service->sales_returns_subject_id] ?? 0), 'income_subject_code' => $service->subject?->code,
                            'cogs_subject_code' => $service->cogsSubject?->code, 'sales_returns_subject_code' => $service->salesReturnsSubject?->code,
                            'sstid' => $service->sstid, 'description' => $service->description];
                        fputcsv($file, array_map(fn (string $column) => $row[$column] ?? null, array_keys($columns)));
                    }
                });
        });
    }

    private function customersCsv(): array
    {
        return $this->csv('customers_'.now()->format('YmdHis').'.csv', CustomerImportService::COLUMNS, function ($file): void {
            Customer::with('group', 'subject')->orderBy('id')->chunk(200, function ($customers) use ($file): void {
                foreach ($customers as $customer) {
                    fputcsv($file, [$customer->name, $customer->group?->name, $customer->subject?->code, $customer->type?->valueName(),
                        $customer->phone, $customer->mobile, $customer->fax, $customer->address, $customer->postal_code, $customer->email,
                        $customer->ecnmcs_code, $customer->personal_code, $customer->web_page, $customer->responsible, $customer->connector,
                        $customer->desc, $customer->credit, $customer->disc_rate, $customer->acc_name_1, $customer->acc_no_1,
                        $customer->acc_bank_1, $customer->acc_name_2, $customer->acc_no_2, $customer->acc_bank_2]);
                }
            });
        });
    }

    private function invoicesCsv(array $filters): array
    {
        $request = $this->filterRequest($filters);
        $type = InvoiceType::tryFromName($request->invoice_type);
        $status = InvoiceStatus::tryFromName($request->status);
        $query = Invoice::with(['customer', 'document', 'items'])->orderByDesc('date')->orderByDesc('number');
        $this->applyInvoiceFilters($query, $request, $type);
        $query->when($status !== null, fn ($invoice) => $invoice->where('status', $status));
        $headers = [__('Invoice Number'), __('Customer Name'), __('Date'), __('Document Number'), __('Before discounts and tax'),
            __('Discounts'), __('Tax'), __('Amount'), __('Amount - Discounts')];

        return $this->csv('invoices_'.now()->format('YmdHis').'.csv', $headers, function ($file) use ($query): void {
            $query->chunk(200, function ($invoices) use ($file): void {
                foreach ($invoices as $invoice) {
                    $subtotal = $invoice->items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);
                    $discounts = (float) $invoice->items->sum('unit_discount');
                    fputcsv($file, [$invoice->number, $invoice->customer?->name, formatDate($invoice->date), $invoice->document?->number,
                        $subtotal, $discounts, (float) $invoice->items->sum('vat'), (float) $invoice->amount - (float) $invoice->subtraction, $subtotal - $discounts]);
                }
            });
        });
    }

    private function employeesCsv(array $filters): array
    {
        return $this->csv('employees_'.now()->format('YmdHis').'.csv', [__('Code'), __('First Name'), __('Last Name'), __('National Code'),
            __('Phone'), __('Work Site'), __('Position'), __('Contract'), __('Employment Type'), __('Status'), __('Contract Start Date'),
            __('Contract End Date'), __('Salary Decree Count')], function ($file) use ($filters): void {
                $this->employeeQuery($this->filterRequest($filters))->chunk(200, function ($employees) use ($file): void {
                    foreach ($employees as $employee) {
                        fputcsv($file, [$employee->code, $employee->first_name, $employee->last_name, $employee->national_code, $employee->phone,
                            $employee->workSite?->name, $employee->orgChart?->title, $employee->workSiteContract?->name,
                            $employee->employment_type?->label(), $employee->is_active ? __('Active') : __('Inactive'),
                            $employee->contract_start_date?->toDateString(), $employee->contract_end_date?->toDateString(), $employee->salary_decrees_count]);
                    }
                });
            });
    }

    private function accountingReportCsv(array $filters): array
    {
        $validated = Validator::make($filters, [
            'report_for' => ['required', Rule::in(['Journal', 'Ledger', 'subLedger', 'Document'])],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'start_document_number' => ['nullable', 'numeric'],
            'end_document_number' => ['nullable', 'numeric'],
            'start_date' => ['nullable', 'string'],
            'end_date' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'columns_selected' => ['nullable', 'boolean'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in(DocumentImportExportService::ALL_COLUMNS)],
        ])->after(function ($validator) use ($filters): void {
            if (in_array($filters['report_for'] ?? null, ['Ledger', 'subLedger'], true) && empty($filters['subject_id'])) {
                $validator->errors()->add('subject_id', __('The subject field is required.'));
            }

            if (isset($filters['start_document_number'], $filters['end_document_number'])
                && (float) $filters['start_document_number'] > (float) $filters['end_document_number']) {
                $validator->errors()->add('start_document_number', __('Start document number cannot be greater than end document number.'));
            }
        })->validate();

        $startDate = $this->reportDate($validated['start_date'] ?? null, 'start_date');
        $endDate = $this->reportDate($validated['end_date'] ?? null, 'end_date');
        if ($startDate && $endDate && Carbon::parse($startDate)->isAfter(Carbon::parse($endDate))) {
            throw ValidationException::withMessages(['start_date' => __('Start date cannot be greater than end date.')]);
        }

        if (in_array($validated['report_for'], ['Journal', 'Document'], true)) {
            $documentFilters = [
                'start_document_number' => $validated['start_document_number'] ?? null,
                'end_document_number' => $validated['end_document_number'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'text' => $validated['search'] ?? null,
                'columns_selected' => $validated['columns_selected'] ?? null,
                'columns' => $validated['columns'] ?? [],
            ];

            return $this->captureResponse($this->documentExportService->export($documentFilters));
        }

        $subject = Subject::findOrFail($validated['subject_id']);
        $subjectIds = $subject->getAllDescendantIds();
        $query = Transaction::with(['document', 'subject'])->whereIn('subject_id', $subjectIds);

        $query->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas(
            'document', fn (Builder $document) => $document->where('title', 'like', '%'.$search.'%')
        ));
        $query->when($validated['start_document_number'] ?? null, fn (Builder $query, $number) => $query->whereHas(
            'document', fn (Builder $document) => $document->where('number', '>=', $number)
        ));
        $query->when($validated['end_document_number'] ?? null, fn (Builder $query, $number) => $query->whereHas(
            'document', fn (Builder $document) => $document->where('number', '<=', $number)
        ));
        $query->when($startDate, fn (Builder $query, string $date) => $query->whereHas(
            'document', fn (Builder $document) => $document->where('date', '>=', $date)
        ));
        $query->when($endDate, fn (Builder $query, string $date) => $query->whereHas(
            'document', fn (Builder $document) => $document->where('date', '<=', $date)
        ));
        $query->orderBy(Document::whereColumn('id', 'transactions.document_id')->select('date'))
            ->orderBy(Document::whereColumn('id', 'transactions.document_id')->select('number'));

        $headers = [__('Date'), __('Document #'), __('Subject Code'), __('Subject Name'), __('Description'), __('Debit'), __('Credit')];

        return $this->csv(strtolower($validated['report_for']).'_report_'.now()->format('YmdHis').'.csv', $headers, function ($file) use ($query): void {
            $query->chunk(200, function ($transactions) use ($file): void {
                foreach ($transactions as $transaction) {
                    fputcsv($file, [
                        formatDate($transaction->document->date),
                        formatDocumentNumber($transaction->document->number),
                        formatCode($transaction->subject->code),
                        $transaction->subject->name,
                        $transaction->desc ?? '',
                        $transaction->debit ?? 0,
                        $transaction->credit ?? 0,
                    ]);
                }
            });
        });
    }

    private function reportDate(?string $date, string $attribute): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        return jalaliInputToGregorian(toEnglish(str_replace('-', '/', trim($date))), $attribute);
    }

    private function invoicePdf(array $filters): array
    {
        $invoice = Invoice::with('customer', 'items')->findOrFail($filters['invoice_id'] ?? null);
        if (! $invoice->status->isApproved()) {
            throw ValidationException::withMessages(['invoice_id' => __('Only approved invoices can be emailed as PDF.')]);
        }

        return ['content' => PDF::loadView('invoices.print', compact('invoice'))->output(),
            'filename' => 'invoice-'.formatDocumentNumber($invoice->number ?? $invoice->id).'.pdf', 'mime' => 'application/pdf'];
    }

    private function csv(string $filename, array $headers, callable $writeRows): array
    {
        $file = fopen('php://temp', 'w+');
        fwrite($file, "\xEF\xBB\xBF");
        fputcsv($file, $headers);
        $writeRows($file);
        rewind($file);
        $content = stream_get_contents($file);
        fclose($file);

        return ['content' => $content, 'filename' => $filename, 'mime' => 'text/csv; charset=UTF-8'];
    }

    private function captureResponse(StreamedResponse $response): array
    {
        ob_start();
        try {
            $response->sendContent();
            $content = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $disposition = $response->headers->get('Content-Disposition', '');
        preg_match('/filename=(?:"([^"]+)"|([^;]+))/', $disposition, $matches);

        return ['content' => $content, 'filename' => trim($matches[1] ?? $matches[2] ?? 'report.csv'),
            'mime' => $response->headers->get('Content-Type', 'text/csv; charset=UTF-8')];
    }

    private function validateDocumentFilters(array $filters): array
    {
        return $this->documentExportService->validateExportRequest($this->filterRequest($filters));
    }

    private function filterRequest(array $filters): Request
    {
        return new Request($filters);
    }

    private function productColumns(): array
    {
        return ['name' => __('Product name'), ...$this->productReportColumns(), 'income_subject_code' => __('Revenue subject code'),
            'cogs_subject_code' => __('COGS subject code'), 'inventory_subject_code' => __('Inventory subject code'),
            'sales_returns_subject_code' => __('Sales returns subject code'), 'sstid' => __('Product SSTID'), 'location' => __('Location in warehouse'),
            'quantity_warning' => __('Quantity warning'), 'oversell' => __('Oversell'), 'discount_formula' => __('Discount formula'),
            'description' => __('Description'), 'vat' => __('VAT')];
    }

    private function productReportColumns(): array
    {
        return ['inbound' => __('Inbound'), 'outbound' => __('Outbound'), 'stock' => __('Stock'), 'category' => __('Category'),
            'code' => __('Product code'), 'selling_price' => __('Sale price'), 'cost_of_goods' => __('Cost of goods'),
            'last_item_cost' => __('Last item cost'), 'sales_profit' => __('Sales profit'), 'revenue_account' => __('Revenue account amount'),
            'cogs_account' => __('COGS account amount'), 'inventory_account' => __('Inventory account amount'), 'sales_return_account' => __('Sales return account amount')];
    }

    private function serviceColumns(): array
    {
        return ['code' => __('Service Code'), 'name' => __('Name'), 'selling_price' => __('Sell price'), 'vat' => __('VAT'),
            'group_name' => __('Service group'), 'revenue_account' => __('Revenue account amount'), 'cogs_account' => __('COGS account amount'),
            'sales_return_account' => __('Sales return account amount'), 'income_subject_code' => __('Revenue subject code'),
            'cogs_subject_code' => __('COGS subject code'), 'sales_returns_subject_code' => __('Sales returns subject code'),
            'sstid' => __('Service SSTID'), 'description' => __('Description')];
    }

    private function serviceSubjectTotals(Collection $services): array
    {
        $ids = $services->flatMap(fn (Service $service) => [$service->subject_id, $service->cogs_subject_id, $service->sales_returns_subject_id])
            ->filter()->unique()->values();

        return $ids->isEmpty() ? [] : Transaction::whereIn('subject_id', $ids)->selectRaw('subject_id, SUM(value) as total')
            ->groupBy('subject_id')->pluck('total', 'subject_id')->map(fn ($value) => (float) $value)->all();
    }

    private function employeeQuery(Request $request): Builder
    {
        $query = Employee::with(['workSite', 'orgChart', 'workSiteContract', 'organizationUnit'])->withCount('salaryDecrees')->orderBy('code');
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn (Builder $q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")->orWhere('national_code', 'like', "%{$search}%"));
        }
        $query->when($request->filled('is_active'), fn ($q) => $q->where('is_active', (bool) $request->is_active));
        $query->when($request->filled('work_site_id'), fn ($q) => $q->where('work_site_id', $request->integer('work_site_id')));
        $query->when($request->filled('contract_framework_id'), fn ($q) => $q->where('contract_framework_id', $request->integer('contract_framework_id')));

        return $query;
    }

    private function applyInvoiceFilters(Builder $query, Request $request, ?InvoiceType $type): void
    {
        $serviceBuy = in_array($type, [InvoiceType::BUY, InvoiceType::RETURN_BUY], true) && $request->boolean('service_buy');
        $query->when($type, fn ($q) => $q->where('invoice_type', $type))->when($request->filled('number'), fn ($q) => $q->where('number', $request->number));
        $query->when($request->filled('start_date'), fn ($q) => $q->where('date', '>=', convertToGregorian($request->start_date)));
        $query->when($request->filled('end_date'), fn ($q) => $q->where('date', '<=', convertToGregorian($request->end_date)));
        $query->when($request->filled('text'), fn ($q) => $q->where(fn ($invoice) => $invoice
            ->whereHas('items', fn ($items) => $items->where('description', 'like', "%{$request->text}%"))
            ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$request->text}%"))));
        $query->when($serviceBuy, fn ($q) => $q->whereHas('items', fn ($item) => $item->where('itemable_type', Service::class)));
        $query->when(! $serviceBuy && in_array($type, [InvoiceType::BUY, InvoiceType::RETURN_BUY], true),
            fn ($q) => $q->whereHas('items', fn ($item) => $item->where('itemable_type', Product::class)));
        $query->when($type === InvoiceType::SELL && $request->boolean('voided'), fn ($q) => $q->whereHas('voidInvoice'));
    }
}
