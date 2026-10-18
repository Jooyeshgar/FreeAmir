<?php

namespace App\Services;

use App\Enums\InvoiceType;
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

    public static function transferInvoice(Invoice $invoice, int $targetCompanyId, User $user, bool $transferDependencies = false): array
    {
        $invoice->load([
            'customer', 'items.itemable', 'document.transactions.subject',
            'ancillaryCosts.customer', 'ancillaryCosts.items.product',
            'ancillaryCosts.document.transactions.subject',
        ]);

        return self::_executeInvoiceChainTransfer($invoice, $targetCompanyId, $user, $transferDependencies);
    }

    private static function _executeInvoiceChainTransfer(Invoice $invoice, int $targetCompanyId, User $user, bool $transferDependencies): array
    {
        $invValidation = self::_validateInvoiceDependencies($invoice, $targetCompanyId, $transferDependencies);
        $errors = $invValidation['errors'];

        $returnedCheck = self::_checkReturnedInvoiceDeps($invoice, $targetCompanyId, $transferDependencies);
        $errors = array_merge($errors, $returnedCheck['errors']);

        $acValidations = [];
        foreach ($invoice->ancillaryCosts as $ac) {
            $acVal = self::_validateAncillaryCostItemsDependencies($ac, $targetCompanyId, $transferDependencies);
            $acValidations[$ac->id] = $acVal;
            $errors = array_merge($errors, $acVal['errors']);
        }

        $errors = array_values(array_unique($errors));
        if (! empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return DB::transaction(function () use ($invoice, $targetCompanyId, $user, $invValidation, $acValidations, $returnedCheck, $transferDependencies) {
            $warnings = [];
            $returnedInvoiceId = null;

            if ($transferDependencies) {
                $invValidation = self::_materializeInvoiceDependencies($invValidation, $targetCompanyId);
                foreach ($acValidations as $acId => $acValidation) {
                    $acValidations[$acId] = self::_materializeAncillaryCostDependencies($acValidation, $targetCompanyId);
                }
            }

            if ($returnedCheck['exists']) {
                $returnedInvoiceId = $returnedCheck['target_id'];
            } elseif ($returnedCheck['needed']) {
                $returnedValidation = $returnedCheck['validation'];
                if ($transferDependencies) {
                    $returnedValidation = self::_materializeInvoiceDependencies($returnedValidation, $targetCompanyId);
                }
                $newSource = self::_createInvoiceInTarget($returnedCheck['source'], $targetCompanyId, $user, $returnedValidation);
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
        $newInvoice->number = self::_nextInvoiceNumber($targetCompanyId, $invoice->invoice_type);
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
        $newDoc->number = self::_nextDocumentNumber($targetCompanyId);
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

    private static function _nextDocumentNumber(int $targetCompanyId): int
    {
        $maxNumber = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->max('number');

        return ((int) floor((float) ($maxNumber ?? 0))) + 1;
    }

    private static function _nextInvoiceNumber(int $targetCompanyId, mixed $invoiceType): int
    {
        $maxNumber = Invoice::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('invoice_type', self::_invoiceTypeValue($invoiceType))->max('number');

        return ((int) floor((float) ($maxNumber ?? 0))) + 1;
    }

    private static function _invoiceTypeValue(mixed $invoiceType): int
    {
        return InvoiceType::fromName($invoiceType)->value;
    }

    private static function _checkReturnedInvoiceDeps(Invoice $invoice, int $targetCompanyId, bool $transferDependencies): array
    {
        if (! $invoice->returned_invoice_id) {
            return ['needed' => false, 'errors' => [], 'exists' => false, 'target_id' => null, 'source' => null, 'validation' => null];
        }

        $source = Invoice::withoutGlobalScope(FiscalYearScope::class)->with(['customer', 'items.itemable'])->find($invoice->returned_invoice_id);

        if (! $source) {
            return ['needed' => false, 'errors' => [], 'exists' => false, 'target_id' => null, 'source' => null, 'validation' => null];
        }

        $existing = Invoice::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('number', $source->number)->where('invoice_type', self::_invoiceTypeValue($source->invoice_type))->first();

        if ($existing) {
            return ['needed' => false, 'errors' => [], 'exists' => true, 'target_id' => $existing->id, 'source' => $source, 'validation' => null];
        }

        $validation = self::_validateInvoiceDependencies($source, $targetCompanyId, $transferDependencies);

        return [
            'needed' => true,
            'errors' => $validation['errors'],
            'exists' => false,
            'target_id' => null,
            'source' => $source,
            'validation' => $validation,
        ];
    }

    private static function _validateInvoiceDependencies(Invoice $invoice, int $targetCompanyId, bool $transferDependencies = false): array
    {
        $errors = [];
        $customerId = null;
        $productMapping = [];
        $serviceMapping = [];
        $missingCustomer = null;
        $missingProducts = [];
        $missingServices = [];

        if ($invoice->customer) {
            $targetCustomer = Customer::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $invoice->customer->name)->first();

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
                    $target = Product::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $item->itemable->name)->first();

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
                    $target = Service::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $item->itemable->name)->first();

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

    private static function _validateAncillaryCostItemsDependencies(AncillaryCost $ac, int $targetCompanyId, bool $transferDependencies = false): array
    {
        $errors = [];
        $customerId = null;
        $productMapping = [];
        $missingCustomer = null;
        $missingProducts = [];

        if ($ac->customer) {
            $targetCustomer = Customer::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $ac->customer->name)->first();

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
                $target = Product::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $item->product->name)->first();

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

    private static function _materializeInvoiceDependencies(array $validation, int $targetCompanyId): array
    {
        if ($validation['customer_id'] === null && $validation['missing_customer']) {
            $validation['customer_id'] = self::_findOrCreateCustomerInTarget($validation['missing_customer'], $targetCompanyId)->id;
        }

        foreach ($validation['missing_products'] as $sourceId => $product) {
            $validation['product_mapping'][$sourceId] = self::_findOrCreateProductInTarget($product, $targetCompanyId)->id;
        }

        foreach ($validation['missing_services'] as $sourceId => $service) {
            $validation['service_mapping'][$sourceId] = self::_findOrCreateServiceInTarget($service, $targetCompanyId)->id;
        }

        return $validation;
    }

    private static function _materializeAncillaryCostDependencies(array $validation, int $targetCompanyId): array
    {
        if ($validation['customer_id'] === null && $validation['missing_customer']) {
            $validation['customer_id'] = self::_findOrCreateCustomerInTarget($validation['missing_customer'], $targetCompanyId)->id;
        }

        foreach ($validation['missing_products'] as $sourceId => $product) {
            $validation['product_mapping'][$sourceId] = self::_findOrCreateProductInTarget($product, $targetCompanyId)->id;
        }

        return $validation;
    }

    private static function _findOrCreateCustomerInTarget(Customer $source, int $targetCompanyId): Customer
    {
        $existing = Customer::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $source->name)->first();

        if ($existing) {
            return $existing;
        }

        $source->loadMissing('subject');
        $sourceSubject = $source->subject;
        if (! $sourceSubject && $source->subject_id) {
            $sourceSubject = Subject::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $source->company_id)->find($source->subject_id);
        }

        $customer = new Customer;
        $customer->fill(collect($source->getAttributes())->except(['id', 'company_id', 'group_id', 'introducer_id', 'subject_id', 'created_at', 'updated_at'])->toArray());
        $customer->company_id = $targetCompanyId;
        $customer->group_id = null;
        $customer->introducer_id = null;
        $customer->subject_id = null;

        self::_withActiveCompany($targetCompanyId, fn () => $customer->saveQuietly());

        if ($sourceSubject) {
            $subject = self::_findOrCreateSubjectInTarget($sourceSubject, $targetCompanyId);
            $subject->subjectable()->associate($customer);
            $subject->save();
            $customer->updateQuietly(['subject_id' => $subject->id]);
            $customer->setRelation('subject', $subject);
        }

        return $customer;
    }

    private static function _findOrCreateProductInTarget(Product $source, int $targetCompanyId): Product
    {
        $existing = Product::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $source->name)->first()
            ?? Product::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('code', $source->code)->first();

        if ($existing) {
            return $existing;
        }

        $source->loadMissing('incomeSubject', 'salesReturnsSubject', 'cogsSubject', 'inventorySubject');
        $subjectColumns = ['income_subject_id', 'sales_returns_subject_id', 'cogs_subject_id', 'inventory_subject_id'];
        $product = new Product;
        $product->fill(collect($source->getAttributes())->except(['id', 'company_id', 'group', 'subject_id', ...$subjectColumns, 'created_at', 'updated_at'])->toArray());
        $product->company_id = $targetCompanyId;
        $product->group = null;

        self::_withActiveCompany($targetCompanyId, fn () => $product->save());

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

            $subject = self::_findOrCreateSubjectInTarget($source->$relation, $targetCompanyId);
            $subject->subjectable()->associate($product);
            $subject->save();
            $mapped[$column] = $subject->id;
        }
        if ($mapped) {
            $product->updateQuietly($mapped);
        }

        return $product;
    }

    private static function _findOrCreateServiceInTarget(Service $source, int $targetCompanyId): Service
    {
        $existing = Service::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('name', $source->name)->first()
            ?? Service::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $targetCompanyId)->where('code', $source->code)->first();

        if ($existing) {
            return $existing;
        }

        $source->loadMissing('subject', 'cogsSubject', 'salesReturnsSubject');
        $subjectColumns = ['subject_id', 'cogs_subject_id', 'sales_returns_subject_id'];
        $service = new Service;
        $service->fill(collect($source->getAttributes())->except(['id', 'company_id', 'group', ...$subjectColumns, 'created_at', 'updated_at'])->toArray());
        $service->company_id = $targetCompanyId;
        $service->group = null;

        self::_withActiveCompany($targetCompanyId, fn () => $service->save());

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

            $subject = self::_findOrCreateSubjectInTarget($source->$relation, $targetCompanyId);
            $subject->subjectable()->associate($service);
            $subject->save();
            $mapped[$column] = $subject->id;
        }
        if ($mapped) {
            $service->updateQuietly($mapped);
        }

        return $service;
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
