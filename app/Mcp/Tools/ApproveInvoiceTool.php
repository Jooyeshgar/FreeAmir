<?php

namespace App\Mcp\Tools;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\FiscalYearMutationGuard;
use App\Services\InvoiceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Response;

class ApproveInvoiceTool extends AccountingTool
{
    protected string $name = 'approve_invoice';

    protected string $description = 'Approve an existing unapproved or draft invoice in the selected authorized open fiscal year, posting its accounting document and updating inventory/COGS. ONLY call this tool when the user explicitly instructs you to approve or post an invoice; never auto-approve on your own.';

    protected array $permissionGroups = [['invoices.approve']];

    protected bool $readOnlyHint = false;

    protected bool $destructiveHint = true;

    protected function unexpectedErrorMessage(): string
    {
        return __('Failed to approve invoice. Please try again or contact support.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->description('Authorized open fiscal year ID.')->required(),
            'invoice_id' => $schema->integer()->min(1)->description('ID of the invoice to approve.')->required(),
            'confirm' => $schema->boolean()->description('Set to true only after the user confirms approval despite a conflict warning.'),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['required', 'integer', 'min:1'],
            'invoice_id' => ['required', 'integer', 'min:1'],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($fiscalYear);

        // Keep the invoice locked from the eligibility checks through posting.
        return DB::transaction(fn (): Response => $this->approve($arguments, $fiscalYear));
    }

    private function approve(array $arguments, FiscalYear $fiscalYear): Response
    {
        $invoice = Invoice::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->lockForUpdate()
            ->find((int) $arguments['invoice_id']);

        if (! $invoice) {
            return $this->error(__('Invoice not found in the selected fiscal year.'));
        }

        if ($invoice->invoice_type === InvoiceType::VOID) {
            return $this->error(__('Invalid status action.'));
        }

        if ($invoice->status->isApprovedOrSettled()) {
            return $this->error(__('Invoice is already approved.'));
        }

        if (! in_array($invoice->status, [
            InvoiceStatus::PENDING,
            InvoiceStatus::PRE_INVOICE,
            InvoiceStatus::UNAPPROVED,
            InvoiceStatus::READY_TO_APPROVE,
        ], true)) {
            return $this->error(__('Invalid invoice status.'));
        }

        [$start, $end] = $fiscalYear->range();
        $invoiceDate = $invoice->date?->toDateString();
        if ($invoiceDate === null || $invoiceDate < $start->toDateString() || $invoiceDate > $end->toDateString()) {
            return $this->error(__('Provide an invoice date within the selected fiscal year.'));
        }

        $invoiceService = app(InvoiceService::class);
        $decision = $invoiceService->getChangeStatusDecision($invoice, 'approved');

        if ($decision->hasErrors()) {
            $error = $decision->messages->first(fn ($m) => $m->type === 'error');

            return $this->error($error?->text ?? __('Invalid invoice status.'));
        }

        if ($decision->needsConfirmation && ! ($arguments['confirm'] ?? false)) {
            $warning = $decision->messages->first(fn ($m) => $m->type === 'warning');

            return $this->error(json_encode([
                'message' => $warning?->text ?? __('Please confirm your action.'),
                'needs_confirmation' => true,
                'conflicts' => $decision->conflicts->map(function ($conflict): array {
                    if ($conflict instanceof Invoice) {
                        return [
                            'type' => 'invoice',
                            'id' => (int) $conflict->id,
                            'number' => $conflict->number !== null ? (string) $conflict->number : null,
                            'invoice_type' => $conflict->invoice_type?->valueName(),
                        ];
                    }

                    if ($conflict instanceof Product) {
                        return [
                            'type' => 'product',
                            'id' => (int) $conflict->id,
                            'code' => $conflict->code !== null ? (string) $conflict->code : null,
                            'name' => (string) $conflict->name,
                        ];
                    }

                    return [
                        'type' => strtolower(class_basename($conflict)),
                        'id' => $conflict->id ?? null,
                    ];
                })->values()->all(),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        $invoiceService->changeInvoiceStatus($invoice, 'approved');

        /** @var Invoice $approvedInvoice */
        $approvedInvoice = $invoice->fresh(['document']);

        return $this->success([
            'id' => $approvedInvoice->id,
            'fiscal_year_id' => $approvedInvoice->fiscal_year_id,
            'number' => $approvedInvoice->number,
            'invoice_type' => $approvedInvoice->invoice_type->valueName(),
            'status' => $approvedInvoice->status->valueName(),
            'document_id' => $approvedInvoice->document_id,
            'document_number' => $approvedInvoice->document?->number,
            'approved' => true,
            'message' => __('Invoice approved successfully.'),
        ]);
    }
}
