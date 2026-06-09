<?php

namespace App\Services;

use App\Models\AncillaryCost;
use App\Models\AncillaryCostItem;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Scopes\FiscalYearScope;
use App\Models\Service;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FiscalYearTransferService
{
    public static function transferDocument(Document $document, int $targetCompanyId, User $user): array
    {
        $document->load(['documentable', 'transactions.subject']);

        if ($document->documentable) {
            return [
                'success' => false,
                'errors' => [__('This document is linked to :type and cannot be transferred on its own. Transfer the :type instead.', [
                    'type' => __(class_basename($document->documentable_type)),
                ])],
            ];
        }

        return DB::transaction(function () use ($document, $targetCompanyId, $user) {
            $newDoc = self::_transferDocumentOnly($document, $targetCompanyId, $user);

            return ['success' => true, 'document' => $newDoc];
        });
    }

    public static function transferInvoice(Invoice $invoice, int $targetCompanyId, User $user): array
    {
        $invoice->load([
            'customer', 'items.itemable', 'document.transactions.subject',
            'ancillaryCosts.customer', 'ancillaryCosts.items.product',
            'ancillaryCosts.document.transactions.subject',
        ]);

        return self::_executeInvoiceChainTransfer($invoice, $targetCompanyId, $user);
    }

    private static function _executeInvoiceChainTransfer(Invoice $invoice, int $targetCompanyId, User $user): array
    {
        $invValidation = self::_validateInvoiceDependencies($invoice, $targetCompanyId);
        $errors = $invValidation['errors'];

        $returnedCheck = self::_checkReturnedInvoiceDeps($invoice, $targetCompanyId);
        $errors = array_merge($errors, $returnedCheck['errors']);

        $acValidations = [];
        foreach ($invoice->ancillaryCosts as $ac) {
            $acVal = self::_validateAncillaryCostItemsDependencies($ac, $targetCompanyId);
            $acValidations[$ac->id] = $acVal;
            $errors = array_merge($errors, $acVal['errors']);
        }

        $errors = array_values(array_unique($errors));
        if (! empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return DB::transaction(function () use ($invoice, $targetCompanyId, $user, $invValidation, $acValidations, $returnedCheck) {
            $warnings = [];
            $returnedInvoiceId = null;

            if ($returnedCheck['exists']) {
                $returnedInvoiceId = $returnedCheck['target_id'];
            } elseif ($returnedCheck['needed']) {
                $newSource = self::_createInvoiceInTarget($returnedCheck['source'], $targetCompanyId, $user, $returnedCheck['validation']);
                $returnedInvoiceId = $newSource->id;
                $warnings[] = __('Source invoice #:number was created in the target fiscal year.', ['number' => $returnedCheck['source']->number]);
            }

            $newInvoice = self::_createInvoiceInTarget($invoice, $targetCompanyId, $user, $invValidation, $returnedInvoiceId);

            if ($invoice->document) {
                $newDoc = self::_transferDocumentOnly($invoice->document, $targetCompanyId, $user, $newInvoice->id, Invoice::class);
                $newInvoice->document_id = $newDoc->id;
                $newInvoice->save();
            }

            foreach ($invoice->ancillaryCosts as $ac) {
                $acVal = $acValidations[$ac->id];
                $newAc = self::_createAncillaryCostInTarget($ac, $targetCompanyId, $newInvoice->id, $acVal);

                if ($ac->document) {
                    $newAcDoc = self::_transferDocumentOnly($ac->document, $targetCompanyId, $user, $newAc->id, AncillaryCost::class);
                    $newAc->document_id = $newAcDoc->id;
                    $newAc->save();
                }
            }

            return ['success' => true, 'warnings' => $warnings];
        });
    }

    private static function _withActiveCompany(int $companyId, callable $callback): mixed
    {
        $previous = config('active-company-id');
        config(['active-company-id' => $companyId]);

        try {
            return $callback();
        } finally {
            config(['active-company-id' => $previous]);
        }
    }

    private static function _createInvoiceInTarget(Invoice $invoice, int $targetCompanyId, User $user, array $validation, ?int $returnedInvoiceId = null): Invoice
    {
        $newInvoice = new Invoice;
        $newInvoice->number = $invoice->number;
        $newInvoice->date = $invoice->date;
        $newInvoice->ship_date = $invoice->ship_date;
        $newInvoice->ship_via = $invoice->ship_via;
        $newInvoice->description = $invoice->description;
        $newInvoice->invoice_type = $invoice->invoice_type;
        $newInvoice->status = $invoice->status;
        $newInvoice->vat = $invoice->vat;
        $newInvoice->amount = $invoice->amount;
        $newInvoice->subtraction = $invoice->subtraction;
        $newInvoice->title = $invoice->title;
        $newInvoice->customer_id = $validation['customer_id'];
        $newInvoice->creator_id = $user->id;
        $newInvoice->company_id = $targetCompanyId;
        $newInvoice->document_id = null;
        $newInvoice->returned_invoice_id = $returnedInvoiceId;

        self::_withActiveCompany($targetCompanyId, fn () => $newInvoice->save());

        foreach ($invoice->items as $item) {
            $targetItemableId = null;
            if ($item->itemable_type === Product::class) {
                $targetItemableId = $validation['product_mapping'][$item->itemable_id] ?? null;
            } elseif ($item->itemable_type === Service::class) {
                $targetItemableId = $validation['service_mapping'][$item->itemable_id] ?? null;
            }

            if ($targetItemableId === null) {
                continue;
            }

            $newItem = new InvoiceItem;
            $newItem->invoice_id = $newInvoice->id;
            $newItem->itemable_type = $item->itemable_type;
            $newItem->itemable_id = $targetItemableId;
            $newItem->quantity = $item->quantity;
            $newItem->unit_price = $item->unit_price;
            $newItem->unit_discount = $item->unit_discount;
            $newItem->vat = $item->vat;
            $newItem->amount = $item->amount;
            $newItem->description = $item->description;
            $newItem->quantity_at = 0;
            $newItem->cog_after = 0;
            $newItem->save();
        }

        return $newInvoice;
    }

    private static function _createAncillaryCostInTarget(AncillaryCost $ac, int $targetCompanyId, int $targetInvoiceId, array $acValidation): AncillaryCost
    {
        $newAc = new AncillaryCost;
        $newAc->number = $ac->number;
        $newAc->type = $ac->type;
        $newAc->amount = $ac->amount;
        $newAc->vat = $ac->vat;
        $newAc->date = $ac->date;
        $newAc->status = $ac->status;
        $newAc->company_id = $targetCompanyId;
        $newAc->invoice_id = $targetInvoiceId;
        $newAc->customer_id = $acValidation['customer_id'];
        $newAc->document_id = null;
        $newAc->save();

        foreach ($ac->items as $item) {
            $targetProductId = $acValidation['product_mapping'][$item->product_id] ?? null;
            if ($targetProductId === null) {
                continue;
            }

            $newItem = new AncillaryCostItem;
            $newItem->ancillary_cost_id = $newAc->id;
            $newItem->product_id = $targetProductId;
            $newItem->type = $item->type;
            $newItem->amount = $item->amount;
            $newItem->save();
        }

        return $newAc;
    }

    private static function _transferDocumentOnly(Document $document, int $targetCompanyId, User $user, ?int $documentableId = null, ?string $documentableType = null): Document
    {
        $subjectMapping = [];
        foreach ($document->transactions as $transaction) {
            if (! $transaction->subject) {
                continue;
            }
            $sid = $transaction->subject->id;
            if (! isset($subjectMapping[$sid])) {
                $subjectMapping[$sid] = self::_findOrCreateSubjectInTarget($transaction->subject, $targetCompanyId)->id;
            }
        }

        $newDoc = new Document;
        $newDoc->title = $document->title;
        $newDoc->number = $document->number;
        $newDoc->date = $document->date;
        $newDoc->creator_id = $user->id;
        $newDoc->approver_id = $document->approver_id;
        $newDoc->approved_at = $document->approved_at;
        $newDoc->company_id = $targetCompanyId;
        $newDoc->documentable_id = $documentableId;
        $newDoc->documentable_type = $documentableType;
        $newDoc->save();

        foreach ($document->transactions as $transaction) {
            if (! $transaction->subject || ! isset($subjectMapping[$transaction->subject->id])) {
                continue;
            }

            $newTx = new Transaction;
            $newTx->document_id = $newDoc->id;
            $newTx->subject_id = $subjectMapping[$transaction->subject->id];
            $newTx->user_id = $transaction->user_id;
            $newTx->value = $transaction->value;
            $newTx->desc = $transaction->desc;
            $newTx->save();
        }

        return $newDoc;
    }

    private static function _checkReturnedInvoiceDeps(Invoice $invoice, int $targetCompanyId): array
    {
        if (! $invoice->returned_invoice_id) {
            return ['needed' => false, 'errors' => [], 'exists' => false, 'target_id' => null, 'source' => null, 'validation' => null];
        }

        $source = Invoice::withoutGlobalScope(FiscalYearScope::class)->with(['customer', 'items.itemable'])->find($invoice->returned_invoice_id);

        if (! $source) {
            return ['needed' => false, 'errors' => [], 'exists' => false, 'target_id' => null, 'source' => null, 'validation' => null];
        }

        $existing = Invoice::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('number', $source->number)
            ->where('invoice_type', $source->invoice_type)->first();

        if ($existing) {
            return ['needed' => false, 'errors' => [], 'exists' => true, 'target_id' => $existing->id, 'source' => $source, 'validation' => null];
        }

        $validation = self::_validateInvoiceDependencies($source, $targetCompanyId);

        return [
            'needed' => true,
            'errors' => $validation['errors'],
            'exists' => false,
            'target_id' => null,
            'source' => $source,
            'validation' => $validation,
        ];
    }

    private static function _validateInvoiceDependencies(Invoice $invoice, int $targetCompanyId): array
    {
        $errors = [];
        $customerId = null;
        $productMapping = [];
        $serviceMapping = [];

        if ($invoice->customer) {
            $targetCustomer = Customer::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $invoice->customer->name)->first();

            if ($targetCustomer) {
                $customerId = $targetCustomer->id;
            } else {
                $errors[] = __('Customer ":name" does not exist in the target fiscal year.', ['name' => $invoice->customer->name]);
            }
        }

        foreach ($invoice->items as $item) {
            if ($item->itemable_type === Product::class && $item->itemable) {
                if (! isset($productMapping[$item->itemable_id])) {
                    $target = Product::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $item->itemable->name)->first();

                    if ($target) {
                        $productMapping[$item->itemable_id] = $target->id;
                    } else {
                        $errors[] = __('Product ":name" does not exist in the target fiscal year.', ['name' => $item->itemable->name]);
                    }
                }
            } elseif ($item->itemable_type === Service::class && $item->itemable) {
                if (! isset($serviceMapping[$item->itemable_id])) {
                    $target = Service::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $item->itemable->name)->first();

                    if ($target) {
                        $serviceMapping[$item->itemable_id] = $target->id;
                    } else {
                        $errors[] = __('Service ":name" does not exist in the target fiscal year.', ['name' => $item->itemable->name]);
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'customer_id' => $customerId,
            'product_mapping' => $productMapping,
            'service_mapping' => $serviceMapping,
        ];
    }

    private static function _validateAncillaryCostItemsDependencies(AncillaryCost $ac, int $targetCompanyId): array
    {
        $errors = [];
        $customerId = null;
        $productMapping = [];

        if ($ac->customer) {
            $targetCustomer = Customer::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $ac->customer->name)->first();

            if ($targetCustomer) {
                $customerId = $targetCustomer->id;
            } else {
                $errors[] = __('Customer ":name" (ancillary cost) does not exist in the target fiscal year.', ['name' => $ac->customer->name]);
            }
        }

        foreach ($ac->items as $item) {
            if ($item->product && ! isset($productMapping[$item->product_id])) {
                $target = Product::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $item->product->name)->first();

                if ($target) {
                    $productMapping[$item->product_id] = $target->id;
                } else {
                    $errors[] = __('Product ":name" does not exist in the target fiscal year.', ['name' => $item->product->name]);
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'customer_id' => $customerId,
            'product_mapping' => $productMapping,
        ];
    }

    private static function _findOrCreateSubjectInTarget(Subject $sourceSubject, int $targetCompanyId): Subject
    {
        $chain = self::_buildAncestorChain($sourceSubject);
        $lastInTarget = null;

        foreach ($chain as $ancestor) {
            $inTarget = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('code', $ancestor->code)->first();

            if (! $inTarget) {
                $inTarget = new Subject;
                $inTarget->code = $ancestor->code;
                $inTarget->name = $ancestor->name;
                $inTarget->type = $ancestor->type;
                $inTarget->is_permanent = $ancestor->is_permanent ?? null;
                $inTarget->company_id = $targetCompanyId;
                $inTarget->parent_id = $lastInTarget?->id ?? null;
                $inTarget->save();
            }

            $lastInTarget = $inTarget;
        }

        return $lastInTarget;
    }

    private static function _buildAncestorChain(Subject $subject): array
    {
        $chain = [];
        $current = $subject;
        $visited = [];

        while ($current) {
            if (isset($visited[$current->id])) {
                break;
            }

            $visited[$current->id] = true;
            array_unshift($chain, $current);

            $current = $current->parent_id ? Subject::withoutGlobalScope(FiscalYearScope::class)->find($current->parent_id) : null;
        }

        return $chain;
    }
}
