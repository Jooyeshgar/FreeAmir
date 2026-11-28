<?php

namespace App\Mcp\Tools;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Services\FiscalYearMutationGuard;
use App\Services\InvoiceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;

class UpdateInvoiceTool extends AccountingTool
{
    protected string $name = 'update_invoice';

    protected string $description = 'Set, change, or clear the external reference ID of an existing invoice (approved or draft) in the selected authorized open fiscal year. Only this reference field is changed (items, amounts, accounting documents and Moadian data are untouched), so it is also allowed for invoices already sent to Moadian.';

    protected array $permissionGroups = [['invoices.edit']];

    protected bool $readOnlyHint = false;

    public function schema(JsonSchema $schema): array
    {
        return [
            'fiscal_year_id' => $schema->integer()->min(1)->description('Authorized open fiscal year ID.')->required(),
            'invoice_id' => $schema->integer()->min(1)->description('ID of the invoice to update.')->required(),
            'external_reference_id' => $schema->string()->max(100)->description('New external reference ID (recommended format: "<source>:<order_code>"). Send an empty string to clear it.')->required(),
        ];
    }

    protected function rules(): array
    {
        return [
            'fiscal_year_id' => ['required', 'integer', 'min:1'],
            'invoice_id' => ['required', 'integer', 'min:1'],
            'external_reference_id' => ['present', 'nullable', 'string', 'max:100'],
        ];
    }

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        app(FiscalYearMutationGuard::class)->ensureOpen($fiscalYear);

        $reference = trim((string) ($arguments['external_reference_id'] ?? ''));
        $reference = $reference === '' ? null : $reference;

        return DB::transaction(function () use ($arguments, $fiscalYear, $reference): Response {
            // Same lock order as CreateInvoiceTool: fiscal-year scope first, then the invoice row.
            InvoiceService::lockExternalReferenceScope((int) $fiscalYear->id);

            $invoice = Invoice::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->lockForUpdate()
                ->find((int) $arguments['invoice_id']);

            if (! $invoice) {
                return $this->error(__('Invoice not found in the selected fiscal year.'));
            }

            if ($reference !== null
                && $invoice->invoice_type !== InvoiceType::VOID
                && $invoice->status !== InvoiceStatus::REJECTED) {
                $conflicting = InvoiceService::findActiveInvoiceByExternalReference(
                    (int) $fiscalYear->id,
                    $reference,
                    exceptInvoiceId: (int) $invoice->id,
                    lockForUpdate: true,
                );

                if ($conflicting !== null) {
                    throw ValidationException::withMessages([
                        'external_reference_id' => __('An invoice with this external reference ID already exists in the selected fiscal year.'),
                    ]);
                }
            }

            $invoice->external_reference_id = $reference;
            $invoice->save();

            return $this->success([
                'id' => (int) $invoice->id,
                'fiscal_year_id' => (int) $invoice->fiscal_year_id,
                'number' => $invoice->number,
                'external_reference_id' => $invoice->external_reference_id,
                'message' => __('Invoice updated successfully.'),
            ]);
        });
    }
}
