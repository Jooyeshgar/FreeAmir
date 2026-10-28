<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\AncillaryCostItem;
use App\Models\Config;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Scopes\FiscalYearScope;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FiscalYearTransferService
{
    public static function transferDocument(Document $document, int $targetFiscalYearId, User $user): array
    {
        $document->load(['documentable', 'transactions.subject']);

        $fiscalYearErrors = self::_validateTransferFiscalYears((int) $document->fiscal_year_id, $targetFiscalYearId);
        if ($fiscalYearErrors) {
            return ['success' => false, 'errors' => $fiscalYearErrors];
        }

        if ($document->documentable) {
            return [
                'success' => false,
                'errors' => [__('This document is linked to :type and cannot be transferred on its own. Transfer the :type instead.', [
                    'type' => __(class_basename($document->documentable_type)),
                ])],
            ];
        }

        return DB::transaction(function () use ($document, $targetFiscalYearId, $user) {
            $newDoc = self::_transferDocumentOnly($document, $targetFiscalYearId, $user);

            return ['success' => true, 'document' => $newDoc];
        });
    }

    public static function transferInvoice(Invoice $invoice, int $targetFiscalYearId, User $user, bool $transferDependencies = false): array
    {
        $invoice->load([
            'customer', 'items.itemable', 'document.transactions.subject',
            'payments.payer', 'payments.settlementSubjectRelation', 'payments.document.transactions.subject',
            'ancillaryCosts.customer', 'ancillaryCosts.items.product',
            'ancillaryCosts.document.transactions.subject',
        ]);

        $fiscalYearErrors = self::_validateTransferFiscalYears((int) $invoice->fiscal_year_id, $targetFiscalYearId);
        if ($fiscalYearErrors) {
            return ['success' => false, 'errors' => $fiscalYearErrors];
        }

        return self::_executeInvoiceChainTransfer($invoice, $targetFiscalYearId, $user, $transferDependencies);
    }

    /**
     * Validate both ends of a transfer before opening a transaction.
     * Fiscal year ownership is resolved from the explicit fiscal-year IDs rather than from the active company context.
     */
    private static function _validateTransferFiscalYears(int $sourceFiscalYearId, int $targetFiscalYearId): array
    {
        $fiscalYears = FiscalYear::withoutGlobalScopes()->whereKey([$sourceFiscalYearId, $targetFiscalYearId])->get()->keyBy('id');

        $source = $fiscalYears->get($sourceFiscalYearId);
        $target = $fiscalYears->get($targetFiscalYearId);
        $errors = [];

        if (! $source || ! $target) {
            return [__('The fiscal year could not be resolved.')];
        }

        if ($source->isClosed() || $target->isClosed()) {
            $errors[] = __('This fiscal year is already closed.');
        }

        return $errors;
    }

    private static function _executeInvoiceChainTransfer(Invoice $invoice, int $targetFiscalYearId, User $user, bool $transferDependencies): array
    {
        $invValidation = self::_validateInvoiceDependencies($invoice, $targetFiscalYearId, $transferDependencies);
        $errors = $invValidation['errors'];

        $returnedCheck = self::_checkReturnedInvoiceDeps($invoice, $targetFiscalYearId, $transferDependencies);
        $errors = array_merge($errors, $returnedCheck['errors']);

        $acValidations = [];
        foreach ($invoice->ancillaryCosts as $ac) {
            $acVal = self::_validateAncillaryCostItemsDependencies($ac, $targetFiscalYearId, $transferDependencies);
            $acValidations[$ac->id] = $acVal;
            $errors = array_merge($errors, $acVal['errors']);
        }

        $errors = array_values(array_unique($errors));
        if (! empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return DB::transaction(function () use ($invoice, $targetFiscalYearId, $user, $invValidation, $acValidations, $returnedCheck, $transferDependencies) {
            $warnings = [];
            $returnedInvoiceId = null;

            if ($transferDependencies) {
                $invValidation = self::_materializeInvoiceDependencies($invValidation, $targetFiscalYearId);
                foreach ($acValidations as $acId => $acValidation) {
                    $acValidations[$acId] = self::_materializeAncillaryCostDependencies($acValidation, $targetFiscalYearId);
                }
            }

            if ($returnedCheck['exists']) {
                $returnedInvoiceId = $returnedCheck['target_id'];
            } elseif ($returnedCheck['needed']) {
                $returnedValidation = $returnedCheck['validation'];
                if ($transferDependencies) {
                    $returnedValidation = self::_materializeInvoiceDependencies($returnedValidation, $targetFiscalYearId);
                }
                $newSource = self::_createInvoiceInTarget($returnedCheck['source'], $targetFiscalYearId, $user, $returnedValidation);
                $returnedInvoiceId = $newSource->id;
                $warnings[] = __('Source invoice #:number was created in the target fiscal year.', ['number' => $returnedCheck['source']->number]);
            }

            $newInvoice = self::_createInvoiceInTarget($invoice, $targetFiscalYearId, $user, $invValidation, $returnedInvoiceId);

            if ($invoice->document) {
                $newDoc = self::_transferDocumentOnly($invoice->document, $targetFiscalYearId, $user, $newInvoice->id, Invoice::class);
                $newInvoice->document_id = $newDoc->id;
                $newInvoice->save();
            }

            foreach ($invoice->ancillaryCosts as $ac) {
                $acVal = $acValidations[$ac->id];
                $newAc = self::_createAncillaryCostInTarget($ac, $targetFiscalYearId, $newInvoice->id, $acVal);

                if ($ac->document) {
                    $newAcDoc = self::_transferDocumentOnly($ac->document, $targetFiscalYearId, $user, $newAc->id, AncillaryCost::class);
                    $newAc->document_id = $newAcDoc->id;
                    $newAc->save();
                }
            }

            self::_transferInvoicePayments($invoice, $newInvoice, $targetFiscalYearId, $user, $invValidation['customer_id']);
            self::_transferBankServiceFeeConfig($invoice, $targetFiscalYearId);

            return ['success' => true, 'warnings' => $warnings];
        });
    }

    private static function _transferInvoicePayments(Invoice $sourceInvoice, Invoice $targetInvoice, int $targetFiscalYearId, User $user, ?int $targetCustomerId): void
    {
        foreach ($sourceInvoice->payments as $payment) {
            $targetSettlementSubjectId = null;
            $sourceSettlementSubject = $payment->settlementSubject();
            if ($sourceSettlementSubject) {
                $targetSettlementSubjectId = self::_findOrCreateSubjectInTarget($sourceSettlementSubject, $targetFiscalYearId)->id;
            }

            $newPayment = new Payment;
            $newPayment->fill(collect($payment->getAttributes())->except([
                'id', 'invoice_id', 'document_id', 'payer_id', 'settlement_subject_id', 'cheque_id', 'creator_id',
                'created_at', 'updated_at',
            ])->toArray());
            $newPayment->invoice_id = $targetInvoice->id;
            $newPayment->payer_id = $targetCustomerId;
            $newPayment->settlement_subject_id = $targetSettlementSubjectId;
            $newPayment->creator_id = $user->id;
            $newPayment->cheque_id = null;
            $newPayment->document_id = null;
            $newPayment->save();

            if ($payment->document) {
                $newDocument = self::_transferDocumentOnly($payment->document, $targetFiscalYearId, $user, $newPayment->id, Payment::class);
                $newPayment->document_id = $newDocument->id;
                $newPayment->save();
            }
        }
    }

    private static function _transferBankServiceFeeConfig(Invoice $sourceInvoice, int $targetFiscalYearId): void
    {
        $sourceConfig = Config::withoutGlobalScope(FiscalYearScope::class)
            ->where('fiscal_year_id', $sourceInvoice->fiscal_year_id)
            ->where('key', 'bank_service_fee')
            ->first();

        if (! $sourceConfig || ! $sourceConfig->value) {
            return;
        }

        $sourceSubject = Subject::withoutGlobalScopes()->find((int) $sourceConfig->value);
        if (! $sourceSubject) {
            return;
        }

        $targetSubject = self::_findOrCreateSubjectInTarget($sourceSubject, $targetFiscalYearId);
        Config::withoutGlobalScope(FiscalYearScope::class)->updateOrCreate(
            ['fiscal_year_id' => $targetFiscalYearId, 'key' => 'bank_service_fee'],
            collect($sourceConfig->getAttributes())->except(['id', 'fiscal_year_id'])->merge(['value' => (string) $targetSubject->id])->toArray(),
        );
    }

    private static function _withActiveFiscalYear(int $fiscalYearId, callable $callback): mixed
    {
        $previous = config('active-fiscal-year-id');
        config(['active-fiscal-year-id' => $fiscalYearId]);

        try {
            return $callback();
        } finally {
            config(['active-fiscal-year-id' => $previous]);
        }
    }

    private static function _createInvoiceInTarget(Invoice $invoice, int $targetFiscalYearId, User $user, array $validation, ?int $returnedInvoiceId = null): Invoice
    {
        $newInvoice = new Invoice;
        $newInvoice->number = self::_nextInvoiceNumber($targetFiscalYearId, $invoice->invoice_type);
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
        $newInvoice->fiscal_year_id = $targetFiscalYearId;
        $newInvoice->document_id = null;
        $newInvoice->returned_invoice_id = $returnedInvoiceId;

        self::_withActiveFiscalYear($targetFiscalYearId, fn () => $newInvoice->save());

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
            $newItem->discount = $item->discount;
            $newItem->vat = $item->vat;
            $newItem->amount = $item->amount;
            $newItem->description = $item->description;
            $newItem->quantity_at = 0;
            $newItem->cog_after = 0;
            $newItem->save();
        }

        return $newInvoice;
    }

    private static function _createAncillaryCostInTarget(AncillaryCost $ac, int $targetFiscalYearId, int $targetInvoiceId, array $acValidation): AncillaryCost
    {
        $newAc = new AncillaryCost;
        $newAc->number = $ac->number;
        $newAc->type = $ac->type;
        $newAc->amount = $ac->amount;
        $newAc->vat = $ac->vat;
        $newAc->date = $ac->date;
        $newAc->status = $ac->status;
        $newAc->fiscal_year_id = $targetFiscalYearId;
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

    private static function _transferDocumentOnly(Document $document, int $targetFiscalYearId, User $user, ?int $documentableId = null, ?string $documentableType = null): Document
    {
        $subjectMapping = [];
        foreach ($document->transactions as $transaction) {
            if (! $transaction->subject) {
                continue;
            }
            $sid = $transaction->subject->id;
            if (! isset($subjectMapping[$sid])) {
                $subjectMapping[$sid] = self::_findOrCreateSubjectInTarget($transaction->subject, $targetFiscalYearId)->id;
            }
        }

        $newDoc = new Document;
        $newDoc->title = $document->title;
        $newDoc->number = self::_nextDocumentNumber($targetFiscalYearId);
        $newDoc->date = $document->date;
        $newDoc->creator_id = $user->id;
        $newDoc->approver_id = $document->approver_id;
        $newDoc->approved_at = $document->approved_at;
        $newDoc->fiscal_year_id = $targetFiscalYearId;
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

    private static function _nextDocumentNumber(int $targetFiscalYearId): int
    {
        $maxNumber = Document::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->max('number');

        return ((int) floor((float) ($maxNumber ?? 0))) + 1;
    }

    private static function _nextInvoiceNumber(int $targetFiscalYearId, mixed $invoiceType): int
    {
        $maxNumber = Invoice::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('invoice_type', self::_invoiceTypeValue($invoiceType))->max('number');

        return ((int) floor((float) ($maxNumber ?? 0))) + 1;
    }

    private static function _invoiceTypeValue(mixed $invoiceType): int
    {
        return InvoiceType::fromName($invoiceType)->value;
    }

    private static function _checkReturnedInvoiceDeps(Invoice $invoice, int $targetFiscalYearId, bool $transferDependencies): array
    {
        if (! $invoice->returned_invoice_id) {
            return ['needed' => false, 'errors' => [], 'exists' => false, 'target_id' => null, 'source' => null, 'validation' => null];
        }

        $source = Invoice::withoutGlobalScope(FiscalYearScope::class)->with(['customer', 'items.itemable'])->find($invoice->returned_invoice_id);

        if (! $source) {
            return ['needed' => false, 'errors' => [], 'exists' => false, 'target_id' => null, 'source' => null, 'validation' => null];
        }

        $existing = Invoice::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('number', $source->number)->where('invoice_type', self::_invoiceTypeValue($source->invoice_type))->first();

        if ($existing) {
            return ['needed' => false, 'errors' => [], 'exists' => true, 'target_id' => $existing->id, 'source' => $source, 'validation' => null];
        }

        $validation = self::_validateInvoiceDependencies($source, $targetFiscalYearId, $transferDependencies);

        return [
            'needed' => true,
            'errors' => $validation['errors'],
            'exists' => false,
            'target_id' => null,
            'source' => $source,
            'validation' => $validation,
        ];
    }

    private static function _validateInvoiceDependencies(Invoice $invoice, int $targetFiscalYearId, bool $transferDependencies = false): array
    {
        $errors = [];
        $customerId = null;
        $productMapping = [];
        $serviceMapping = [];
        $missingCustomer = null;
        $missingProducts = [];
        $missingServices = [];

        if ($invoice->customer) {
            $targetCustomer = Customer::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('name', $invoice->customer->name)->first();

            if ($targetCustomer) {
                $customerId = $targetCustomer->id;
            } elseif ($transferDependencies) {
                $missingCustomer = $invoice->customer;
            } else {
                $errors[] = __('Customer ":name" does not exist in the target fiscal year.', ['name' => $invoice->customer->name]);
            }
        }

        foreach ($invoice->items as $item) {
            if ($item->itemable_type === Product::class && $item->itemable) {
                if (! isset($productMapping[$item->itemable_id])) {
                    $target = Product::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('name', $item->itemable->name)->first();

                    if ($target) {
                        $productMapping[$item->itemable_id] = $target->id;
                    } elseif ($transferDependencies) {
                        $missingProducts[$item->itemable_id] = $item->itemable;
                    } else {
                        $errors[] = __('Product ":name" does not exist in the target fiscal year.', ['name' => $item->itemable->name]);
                    }
                }
            } elseif ($item->itemable_type === Service::class && $item->itemable) {
                if (! isset($serviceMapping[$item->itemable_id])) {
                    $target = Service::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('name', $item->itemable->name)->first();

                    if ($target) {
                        $serviceMapping[$item->itemable_id] = $target->id;
                    } elseif ($transferDependencies) {
                        $missingServices[$item->itemable_id] = $item->itemable;
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
            'missing_customer' => $missingCustomer,
            'missing_products' => $missingProducts,
            'missing_services' => $missingServices,
        ];
    }

    private static function _validateAncillaryCostItemsDependencies(AncillaryCost $ac, int $targetFiscalYearId, bool $transferDependencies = false): array
    {
        $errors = [];
        $customerId = null;
        $productMapping = [];
        $missingCustomer = null;
        $missingProducts = [];

        if ($ac->customer) {
            $targetCustomer = Customer::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('name', $ac->customer->name)->first();

            if ($targetCustomer) {
                $customerId = $targetCustomer->id;
            } elseif ($transferDependencies) {
                $missingCustomer = $ac->customer;
            } else {
                $errors[] = __('Customer ":name" (ancillary cost) does not exist in the target fiscal year.', ['name' => $ac->customer->name]);
            }
        }

        foreach ($ac->items as $item) {
            if ($item->product && ! isset($productMapping[$item->product_id])) {
                $target = Product::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('name', $item->product->name)->first();

                if ($target) {
                    $productMapping[$item->product_id] = $target->id;
                } elseif ($transferDependencies) {
                    $missingProducts[$item->product_id] = $item->product;
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
            'missing_customer' => $missingCustomer,
            'missing_products' => $missingProducts,
        ];
    }

    private static function _materializeInvoiceDependencies(array $validation, int $targetFiscalYearId): array
    {
        if ($validation['customer_id'] === null && $validation['missing_customer']) {
            $validation['customer_id'] = self::_findOrCreateCustomerInTarget($validation['missing_customer'], $targetFiscalYearId)->id;
        }

        foreach ($validation['missing_products'] as $sourceId => $product) {
            $validation['product_mapping'][$sourceId] = self::_findOrCreateProductInTarget($product, $targetFiscalYearId)->id;
        }

        foreach ($validation['missing_services'] as $sourceId => $service) {
            $validation['service_mapping'][$sourceId] = self::_findOrCreateServiceInTarget($service, $targetFiscalYearId)->id;
        }

        return $validation;
    }

    private static function _materializeAncillaryCostDependencies(array $validation, int $targetFiscalYearId): array
    {
        if ($validation['customer_id'] === null && $validation['missing_customer']) {
            $validation['customer_id'] = self::_findOrCreateCustomerInTarget($validation['missing_customer'], $targetFiscalYearId)->id;
        }

        foreach ($validation['missing_products'] as $sourceId => $product) {
            $validation['product_mapping'][$sourceId] = self::_findOrCreateProductInTarget($product, $targetFiscalYearId)->id;
        }

        return $validation;
    }

    private static function _findOrCreateCustomerInTarget(Customer $source, int $targetFiscalYearId): Customer
    {
        $existing = Customer::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('name', $source->name)->first();

        if ($existing) {
            return $existing;
        }

        $source->loadMissing('subject');
        $sourceSubject = $source->subject;
        if (! $sourceSubject && $source->subject_id) {
            $sourceSubject = Subject::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $source->fiscal_year_id)->find($source->subject_id);
        }

        $customer = new Customer;
        $customer->fill(collect($source->getAttributes())->except(['id', 'fiscal_year_id', 'group_id', 'introducer_id', 'subject_id', 'created_at', 'updated_at'])->toArray());
        $customer->fiscal_year_id = $targetFiscalYearId;
        $customerGroup = self::_findOrCreateGroupInTarget(
            $source->group_id,
            $source->fiscal_year_id,
            CustomerGroup::class,
            ['subject_id'],
            $targetFiscalYearId,
        );
        $customer->group_id = $customerGroup?->id;
        $customer->introducer_id = null;
        $customer->subject_id = null;

        self::_withActiveFiscalYear($targetFiscalYearId, fn () => $customer->saveQuietly());

        if ($sourceSubject) {
            $subject = self::_findOrCreateSubjectInTarget($sourceSubject, $targetFiscalYearId);
            $subject->subjectable()->associate($customer);
            $subject->save();
            $customer->updateQuietly(['subject_id' => $subject->id]);
            $customer->setRelation('subject', $subject);
        }

        return $customer;
    }

    private static function _findOrCreateProductInTarget(Product $source, int $targetFiscalYearId): Product
    {
        $existing = Product::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('name', $source->name)->first()
            ?? Product::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('code', $source->code)->first();

        if ($existing) {
            return $existing;
        }

        $source->loadMissing('incomeSubject', 'salesReturnsSubject', 'cogsSubject', 'inventorySubject');
        $subjectColumns = ['income_subject_id', 'sales_returns_subject_id', 'cogs_subject_id', 'inventory_subject_id'];
        $product = new Product;
        $product->fill(collect($source->getAttributes())->except(['id', 'fiscal_year_id', 'group', 'subject_id', ...$subjectColumns, 'created_at', 'updated_at'])->toArray());
        $product->fiscal_year_id = $targetFiscalYearId;
        $productGroup = self::_findOrCreateGroupInTarget(
            $source->group,
            $source->fiscal_year_id,
            ProductGroup::class,
            ['sales_returns_subject_id', 'income_subject_id', 'cogs_subject_id', 'inventory_subject_id', 'buyId', 'sellId'],
            $targetFiscalYearId,
        );
        $product->group = $productGroup?->id;

        self::_withActiveFiscalYear($targetFiscalYearId, fn () => $product->save());

        $relations = [
            'income_subject_id' => 'incomeSubject',
            'sales_returns_subject_id' => 'salesReturnsSubject',
            'cogs_subject_id' => 'cogsSubject',
            'inventory_subject_id' => 'inventorySubject',
        ];
        $mapped = [];
        foreach ($relations as $column => $relation) {
            if (! $source->$relation) {
                continue;
            }

            $subject = self::_findOrCreateSubjectInTarget($source->$relation, $targetFiscalYearId);
            $subject->subjectable()->associate($product);
            $subject->save();
            $mapped[$column] = $subject->id;
        }
        if ($mapped) {
            $product->updateQuietly($mapped);
        }

        return $product;
    }

    private static function _findOrCreateServiceInTarget(Service $source, int $targetFiscalYearId): Service
    {
        $existing = Service::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('name', $source->name)->first()
            ?? Service::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('code', $source->code)->first();

        if ($existing) {
            return $existing;
        }

        $source->loadMissing('subject', 'cogsSubject', 'salesReturnsSubject');
        $subjectColumns = ['subject_id', 'cogs_subject_id', 'sales_returns_subject_id'];
        $service = new Service;
        $service->fill(collect($source->getAttributes())->except(['id', 'fiscal_year_id', 'group', ...$subjectColumns, 'created_at', 'updated_at'])->toArray());
        $service->fiscal_year_id = $targetFiscalYearId;
        $serviceGroup = self::_findOrCreateGroupInTarget(
            $source->group,
            $source->fiscal_year_id,
            ServiceGroup::class,
            ['subject_id', 'cogs_subject_id', 'sales_returns_subject_id'],
            $targetFiscalYearId,
        );
        $service->group = $serviceGroup?->id;

        self::_withActiveFiscalYear($targetFiscalYearId, fn () => $service->save());

        $relations = [
            'subject_id' => 'subject',
            'cogs_subject_id' => 'cogsSubject',
            'sales_returns_subject_id' => 'salesReturnsSubject',
        ];
        $mapped = [];
        foreach ($relations as $column => $relation) {
            if (! $source->$relation) {
                continue;
            }

            $subject = self::_findOrCreateSubjectInTarget($source->$relation, $targetFiscalYearId);
            $subject->subjectable()->associate($service);
            $subject->save();
            $mapped[$column] = $subject->id;
        }
        if ($mapped) {
            $service->updateQuietly($mapped);
        }

        return $service;
    }

    private static function _findOrCreateGroupInTarget(?int $sourceGroupId, int $sourceFiscalYearId, string $groupClass, array $subjectColumns, int $targetFiscalYearId): CustomerGroup|ProductGroup|ServiceGroup|null
    {
        if (! $sourceGroupId) {
            return null;
        }

        $sourceGroup = $groupClass::withoutGlobalScope(FiscalYearScope::class)
            ->where('fiscal_year_id', $sourceFiscalYearId)
            ->find($sourceGroupId);

        if (! $sourceGroup) {
            return null;
        }

        $targetGroup = $groupClass::withoutGlobalScope(FiscalYearScope::class)
            ->where('fiscal_year_id', $targetFiscalYearId)
            ->where('name', $sourceGroup->name)
            ->first();

        if ($targetGroup) {
            return $targetGroup;
        }

        $targetGroup = new $groupClass;
        $targetGroup->fill(collect($sourceGroup->getAttributes())->except(['id', 'fiscal_year_id', 'created_at', 'updated_at', ...$subjectColumns])->toArray());
        $targetGroup->fiscal_year_id = $targetFiscalYearId;

        $mappedSubjects = [];
        foreach ($subjectColumns as $column) {
            $sourceSubjectId = $sourceGroup->{$column};
            if (! $sourceSubjectId) {
                continue;
            }

            $sourceSubject = Subject::withoutGlobalScope(FiscalYearScope::class)
                ->where('fiscal_year_id', $sourceFiscalYearId)
                ->find($sourceSubjectId);

            if (! $sourceSubject) {
                continue;
            }

            $targetSubject = self::_findOrCreateSubjectInTarget($sourceSubject, $targetFiscalYearId);
            $targetGroup->{$column} = $targetSubject->id;
            $mappedSubjects[] = $targetSubject;
        }

        $targetGroup->save();

        foreach ($mappedSubjects as $subject) {
            $subject->subjectable()->associate($targetGroup);
            $subject->save();
        }

        return $targetGroup;
    }

    private static function _findOrCreateSubjectInTarget(Subject $sourceSubject, int $targetFiscalYearId): Subject
    {
        $chain = self::_buildAncestorChain($sourceSubject);
        $lastInTarget = null;

        foreach ($chain as $ancestor) {
            $inTarget = Subject::withoutGlobalScope(FiscalYearScope::class)->where('fiscal_year_id', $targetFiscalYearId)->where('code', $ancestor->code)->first();

            if (! $inTarget) {
                $inTarget = new Subject;
                $inTarget->code = $ancestor->code;
                $inTarget->name = $ancestor->name;
                $inTarget->type = $ancestor->type;
                $inTarget->is_permanent = $ancestor->is_permanent ?? null;
                $inTarget->fiscal_year_id = $targetFiscalYearId;
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
