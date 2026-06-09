<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\AncillaryCostItem;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FiscalYearTransferService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FiscalYearTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $source;

    private Company $target;

    private int $sequence = 1000;

    private function nextNumber(): int
    {
        return ++$this->sequence;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->source = Company::factory()->create(['fiscal_year' => 1402]);
        $this->target = Company::factory()->create(['fiscal_year' => 1403]);
        $this->source->users()->attach($this->user);
        $this->target->users()->attach($this->user);

        $this->actingAs($this->user);
        $this->activate($this->source);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'documents.transfer']),
            Permission::firstOrCreate(['name' => 'invoices.transfer']),
            Permission::firstOrCreate(['name' => 'invoices.ancillary-costs.transfer'])
        );
    }

    private function activate(Company $company): void
    {
        config(['active-company-id' => $company->id]);
    }

    private function makeSubject(Company $company, string $code, string $name, ?Subject $parent = null): Subject
    {
        return Subject::create([
            'code' => $code,
            'name' => $name,
            'type' => 'both',
            'company_id' => $company->id,
            'parent_id' => $parent?->id,
        ]);
    }

    private function makeCustomer(Company $company, string $name): Customer
    {
        return Customer::create(['name' => $name, 'company_id' => $company->id]);
    }

    private function makeProduct(Company $company, string $name, string $code): Product
    {
        return Product::create([
            'code' => $code,
            'name' => $name,
            'quantity' => 0,
            'selling_price' => 0,
            'vat' => 0,
            'average_cost' => 0,
            'company_id' => $company->id,
        ]);
    }

    private function makeService(Company $company, string $name, string $code): Service
    {
        return Service::create([
            'code' => $code,
            'name' => $name,
            'selling_price' => 0,
            'vat' => 0,
            'company_id' => $company->id,
        ]);
    }

    private function makeInvoice(Company $company, array $attributes = []): Invoice
    {
        $invoice = new Invoice;
        $invoice->forceFill(array_merge([
            'number' => $this->nextNumber(),
            'date' => '2023-06-01',
            'invoice_type' => InvoiceType::BUY,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 0,
            'status' => InvoiceStatus::APPROVED,
            'title' => 'Invoice',
            'creator_id' => $this->user->id,
            'company_id' => $company->id,
        ], $attributes));

        $previous = config('active-company-id');
        config(['active-company-id' => $company->id]);
        try {
            $invoice->save();
        } finally {
            config(['active-company-id' => $previous]);
        }

        return $invoice;
    }

    private function addProductItem(Invoice $invoice, Product $product, float $qty = 2, float $unit = 50): InvoiceItem
    {
        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => $unit,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => $qty * $unit,
        ]);
    }

    private function addServiceItem(Invoice $invoice, Service $service, float $unit = 100): InvoiceItem
    {
        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Service::class,
            'itemable_id' => $service->id,
            'quantity' => 1,
            'unit_price' => $unit,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => $unit,
        ]);
    }

    private function makeDocument(Company $company, array $lines, ?int $number = null, $documentable = null): Document
    {
        $document = Document::create([
            'number' => $number ?? (Document::withoutGlobalScopes()->max('number') ?? 0) + 1,
            'date' => '2023-06-01',
            'title' => 'Document',
            'creator_id' => $this->user->id,
            'company_id' => $company->id,
            'documentable_id' => $documentable?->id,
            'documentable_type' => $documentable ? $documentable::class : null,
        ]);

        foreach ($lines as [$subject, $value]) {
            Transaction::create([
                'document_id' => $document->id,
                'subject_id' => $subject->id,
                'user_id' => $this->user->id,
                'value' => $value,
                'desc' => 'line',
            ]);
        }

        return $document;
    }

    private function makeAncillaryCost(Company $company, Invoice $invoice, Product $product, array $attributes = []): AncillaryCost
    {
        $ac = AncillaryCost::create(array_merge([
            'number' => $this->nextNumber(),
            'type' => 'Shipping',
            'amount' => 500,
            'vat' => 0,
            'date' => '2023-06-01',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'status' => InvoiceStatus::APPROVED,
            'company_id' => $company->id,
        ], $attributes));

        AncillaryCostItem::create([
            'ancillary_cost_id' => $ac->id,
            'product_id' => $product->id,
            'type' => 'Shipping',
            'amount' => 500,
        ]);

        return $ac;
    }

    private function targetInvoices(): Collection
    {
        return Invoice::withoutGlobalScopes()->where('company_id', $this->target->id)->get();
    }

    private function targetAncillaryCosts(): Collection
    {
        return AncillaryCost::withoutGlobalScopes()->where('company_id', $this->target->id)->get();
    }

    public function test_transfers_a_plain_document_with_its_transactions_and_creates_subjects_in_target(): void
    {
        $cash = $this->makeSubject($this->source, '101', 'Cash');
        $bank = $this->makeSubject($this->source, '102', 'Bank');
        $document = $this->makeDocument($this->source, [[$cash, 1000], [$bank, -1000]]);

        $result = FiscalYearTransferService::transferDocument($document, $this->target->id, $this->user);

        $this->assertTrue($result['success']);

        $newDoc = Document::withoutGlobalScopes()->where('company_id', $this->target->id)->where('number', $document->number)->first();

        $this->assertNotNull($newDoc);
        $this->assertNull($newDoc->documentable_type);
        $this->assertSame('Document', $newDoc->title);
        $this->assertSame($this->user->id, $newDoc->creator_id);

        $transactions = Transaction::where('document_id', $newDoc->id)->get();
        $this->assertCount(2, $transactions);
        $this->assertEqualsCanonicalizing([1000.0, -1000.0], $transactions->pluck('value')->map(fn ($v) => (float) $v)->all());

        foreach (['101', '102'] as $code) {
            $this->assertDatabaseHas('subjects', ['company_id' => $this->target->id, 'code' => $code]);
        }

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'company_id' => $this->source->id]);
    }

    public function test_document_transfer_recreates_the_full_subject_ancestor_chain(): void
    {
        $root = $this->makeSubject($this->source, '1', 'Assets');
        $mid = $this->makeSubject($this->source, '101', 'Current assets', $root);
        $leaf = $this->makeSubject($this->source, '101001', 'Cash', $mid);
        $document = $this->makeDocument($this->source, [[$leaf, 250]]);

        $result = FiscalYearTransferService::transferDocument($document, $this->target->id, $this->user);
        $this->assertTrue($result['success']);

        $targetRoot = Subject::withoutGlobalScopes()->where('company_id', $this->target->id)->where('code', '1')->first();
        $targetMid = Subject::withoutGlobalScopes()->where('company_id', $this->target->id)->where('code', '101')->first();
        $targetLeaf = Subject::withoutGlobalScopes()->where('company_id', $this->target->id)->where('code', '101001')->first();

        $this->assertNotNull($targetRoot);
        $this->assertNotNull($targetMid);
        $this->assertNotNull($targetLeaf);
        $this->assertNull($targetRoot->parent_id);
        $this->assertSame($targetRoot->id, $targetMid->parent_id);
        $this->assertSame($targetMid->id, $targetLeaf->parent_id);
    }

    public function test_document_transfer_reuses_existing_subjects_in_target_by_code(): void
    {
        $cash = $this->makeSubject($this->source, '101', 'Cash');
        $document = $this->makeDocument($this->source, [[$cash, 500]]);

        $existing = $this->makeSubject($this->target, '101', 'Existing cash');

        $result = FiscalYearTransferService::transferDocument($document, $this->target->id, $this->user);
        $this->assertTrue($result['success']);

        $matches = Subject::withoutGlobalScopes()->where('company_id', $this->target->id)->where('code', '101')->get();
        $this->assertCount(1, $matches, 'existing target subject should be reused, not duplicated');

        $newDoc = Document::withoutGlobalScopes()->where('company_id', $this->target->id)->first();
        $this->assertSame($existing->id, Transaction::where('document_id', $newDoc->id)->first()->subject_id);
    }

    public function test_transfers_an_invoice_with_items_and_document_into_target_year(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');
        $subject = $this->makeSubject($this->source, '201', 'Purchases');

        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'amount' => 100]);
        $this->addProductItem($invoice, $product);
        $document = $this->makeDocument($this->source, [[$subject, 100]], documentable: $invoice);
        $invoice->document_id = $document->id;
        $invoice->save();

        $this->makeCustomer($this->target, 'ACME');
        $targetProduct = $this->makeProduct($this->target, 'Widget', 'P1');

        $result = FiscalYearTransferService::transferInvoice($invoice, $this->target->id, $this->user);

        $this->assertTrue($result['success'], json_encode($result));

        $targetInvoices = $this->targetInvoices();
        $this->assertCount(1, $targetInvoices);

        $newInvoice = $targetInvoices->first();
        $this->assertSame($this->target->id, (int) $newInvoice->company_id);
        $this->assertEquals($invoice->number, $newInvoice->number);
        $this->assertSame(InvoiceType::BUY, $newInvoice->invoice_type);

        $newItem = InvoiceItem::where('invoice_id', $newInvoice->id)->first();
        $this->assertNotNull($newItem);
        $this->assertSame($targetProduct->id, $newItem->itemable_id);
        $this->assertSame(Product::class, $newItem->itemable_type);

        $newDoc = Document::withoutGlobalScopes()->find($newInvoice->document_id);
        $this->assertNotNull($newDoc);
        $this->assertSame($this->target->id, (int) $newDoc->company_id);
        $this->assertSame(Invoice::class, $newDoc->documentable_type);
        $this->assertSame($newInvoice->id, $newDoc->documentable_id);

        $this->assertSame($this->source->id, (int) $invoice->fresh()->company_id);
    }

    public function test_invoice_transfer_remaps_service_items(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $service = $this->makeService($this->source, 'Consulting', 'S1');
        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'invoice_type' => InvoiceType::SELL, 'amount' => 100]);
        $this->addServiceItem($invoice, $service);

        $this->makeCustomer($this->target, 'ACME');
        $targetService = $this->makeService($this->target, 'Consulting', 'S9');

        $result = FiscalYearTransferService::transferInvoice($invoice, $this->target->id, $this->user);
        $this->assertTrue($result['success'], json_encode($result));

        $newInvoice = $this->targetInvoices()->first();
        $newItem = InvoiceItem::where('invoice_id', $newInvoice->id)->first();
        $this->assertSame(Service::class, $newItem->itemable_type);
        $this->assertSame($targetService->id, $newItem->itemable_id);
    }

    public function test_invoice_transfer_fails_when_customer_missing_in_target_and_rolls_back(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');
        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id]);
        $this->addProductItem($invoice, $product);

        $this->makeProduct($this->target, 'Widget', 'P1');

        $result = FiscalYearTransferService::transferInvoice($invoice, $this->target->id, $this->user);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('ACME', implode(' ', $result['errors']));

        $this->assertCount(0, $this->targetInvoices(), 'nothing should be written when validation fails');
    }

    public function test_invoice_transfer_fails_when_product_missing_in_target(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');
        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id]);
        $this->addProductItem($invoice, $product);

        $this->makeCustomer($this->target, 'ACME');

        $result = FiscalYearTransferService::transferInvoice($invoice, $this->target->id, $this->user);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Widget', implode(' ', $result['errors']));
        $this->assertCount(0, $this->targetInvoices());
    }

    public function test_invoice_transfer_carries_its_ancillary_costs(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');
        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'amount' => 100]);
        $this->addProductItem($invoice, $product);
        $ac = $this->makeAncillaryCost($this->source, $invoice, $product);

        $this->makeCustomer($this->target, 'ACME');
        $targetProduct = $this->makeProduct($this->target, 'Widget', 'P1');

        $result = FiscalYearTransferService::transferInvoice($invoice, $this->target->id, $this->user);
        $this->assertTrue($result['success'], json_encode($result));

        $targetAcs = $this->targetAncillaryCosts();
        $this->assertCount(1, $targetAcs);

        $newAc = $targetAcs->first();
        $this->assertSame($this->target->id, (int) $newAc->company_id);
        $this->assertEquals($ac->number, $newAc->number);
        $this->assertSame($this->targetInvoices()->first()->id, $newAc->invoice_id);

        $newAcItem = AncillaryCostItem::where('ancillary_cost_id', $newAc->id)->first();
        $this->assertSame($targetProduct->id, $newAcItem->product_id);
    }

    public function test_return_invoice_transfer_creates_the_source_invoice_in_target_with_a_warning(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');

        $original = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'invoice_type' => InvoiceType::SELL, 'amount' => 100]);
        $this->addProductItem($original, $product);

        $returnInvoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'invoice_type' => InvoiceType::RETURN_SELL, 'amount' => 100, 'returned_invoice_id' => $original->id]);
        $this->addProductItem($returnInvoice, $product);

        $this->makeCustomer($this->target, 'ACME');
        $this->makeProduct($this->target, 'Widget', 'P1');

        $result = FiscalYearTransferService::transferInvoice($returnInvoice, $this->target->id, $this->user);
        $this->assertTrue($result['success'], json_encode($result));

        $targetInvoices = $this->targetInvoices();
        $this->assertCount(2, $targetInvoices);

        $newReturn = $targetInvoices->firstWhere('invoice_type', InvoiceType::RETURN_SELL);
        $newOriginal = $targetInvoices->firstWhere('invoice_type', InvoiceType::SELL);
        $this->assertNotNull($newOriginal);
        $this->assertSame($newOriginal->id, $newReturn->returned_invoice_id);

        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString((string) $original->number, implode(' ', $result['warnings']));
    }

    public function test_return_invoice_transfer_reuses_existing_source_invoice_in_target(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');

        $original = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'invoice_type' => InvoiceType::SELL, 'number' => 7000, 'amount' => 100]);
        $this->addProductItem($original, $product);

        $returnInvoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'invoice_type' => InvoiceType::RETURN_SELL, 'amount' => 100, 'returned_invoice_id' => $original->id]);
        $this->addProductItem($returnInvoice, $product);

        $this->makeCustomer($this->target, 'ACME');
        $this->makeProduct($this->target, 'Widget', 'P1');

        $existingOriginal = $this->makeInvoice($this->target, [
            'customer_id' => Customer::withoutGlobalScopes()->where('company_id', $this->target->id)->first()->id,
            'invoice_type' => InvoiceType::SELL,
            'number' => 7000,
            'amount' => 100,
        ]);

        $result = FiscalYearTransferService::transferInvoice($returnInvoice, $this->target->id, $this->user);
        $this->assertTrue($result['success'], json_encode($result));

        $sellInvoices = Invoice::withoutGlobalScopes()->where('company_id', $this->target->id)->where('invoice_type', InvoiceType::SELL)->get();
        $this->assertCount(1, $sellInvoices, 'existing original must be reused, not duplicated');

        $newReturn = Invoice::withoutGlobalScopes()->where('company_id', $this->target->id)->where('invoice_type', InvoiceType::RETURN_SELL)->first();
        $this->assertSame($existingOriginal->id, $newReturn->returned_invoice_id);
        $this->assertEmpty($result['warnings'] ?? []);
    }

    public function test_transfers_an_ancillary_cost_and_creates_its_invoice_in_target(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');
        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'amount' => 100]);
        $this->addProductItem($invoice, $product);
        $ac = $this->makeAncillaryCost($this->source, $invoice, $product);

        $this->makeCustomer($this->target, 'ACME');
        $this->makeProduct($this->target, 'Widget', 'P1');

        $result = FiscalYearTransferService::transferAncillaryCost($ac, $this->target->id, $this->user);
        $this->assertTrue($result['success'], json_encode($result));

        $this->assertCount(1, $this->targetInvoices());
        $this->assertCount(1, $this->targetAncillaryCosts());

        $newAc = $this->targetAncillaryCosts()->first();
        $this->assertSame($this->targetInvoices()->first()->id, $newAc->invoice_id);
    }

    public function test_ancillary_cost_transfer_reuses_existing_target_invoice(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');
        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'number' => 8000, 'invoice_type' => InvoiceType::BUY, 'amount' => 100]);
        $this->addProductItem($invoice, $product);
        $ac = $this->makeAncillaryCost($this->source, $invoice, $product);

        $targetCustomer = $this->makeCustomer($this->target, 'ACME');
        $this->makeProduct($this->target, 'Widget', 'P1');

        $existingInvoice = $this->makeInvoice($this->target, ['customer_id' => $targetCustomer->id, 'number' => 8000, 'invoice_type' => InvoiceType::BUY, 'amount' => 100]);

        $result = FiscalYearTransferService::transferAncillaryCost($ac, $this->target->id, $this->user);
        $this->assertTrue($result['success'], json_encode($result));

        $this->assertCount(1, $this->targetInvoices(), 'existing target invoice must be reused');
        $newAc = $this->targetAncillaryCosts()->first();
        $this->assertSame($existingInvoice->id, $newAc->invoice_id);
    }

    public function test_ancillary_cost_transfer_fails_when_product_missing_in_target(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');
        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'amount' => 100]);
        $this->addProductItem($invoice, $product);
        $ac = $this->makeAncillaryCost($this->source, $invoice, $product);

        $this->makeCustomer($this->target, 'ACME');

        $result = FiscalYearTransferService::transferAncillaryCost($ac, $this->target->id, $this->user);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Widget', implode(' ', $result['errors']));
        $this->assertCount(0, $this->targetAncillaryCosts());
        $this->assertCount(0, $this->targetInvoices());
    }

    public function test_transfer_document_routes_invoice_documentable_through_invoice_chain(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');
        $subject = $this->makeSubject($this->source, '201', 'Purchases');

        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'amount' => 100]);
        $this->addProductItem($invoice, $product);
        $document = $this->makeDocument($this->source, [[$subject, 100]], documentable: $invoice);
        $invoice->document_id = $document->id;
        $invoice->save();

        $this->makeCustomer($this->target, 'ACME');
        $this->makeProduct($this->target, 'Widget', 'P1');

        $result = FiscalYearTransferService::transferDocument($document, $this->target->id, $this->user);

        $this->assertTrue($result['success'], json_encode($result));
        $this->assertCount(1, $this->targetInvoices());

        $newInvoice = $this->targetInvoices()->first();
        $newDoc = Document::withoutGlobalScopes()->find($newInvoice->document_id);
        $this->assertNotNull($newDoc);
        $this->assertSame(Invoice::class, $newDoc->documentable_type);
        $this->assertSame($newInvoice->id, $newDoc->documentable_id);
    }

    public function test_transfer_document_routes_ancillary_documentable_through_ancillary_chain(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');
        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'amount' => 100]);
        $this->addProductItem($invoice, $product);
        $ac = $this->makeAncillaryCost($this->source, $invoice, $product);
        $acDocument = $this->makeDocument($this->source, [], documentable: $ac);
        $ac->document_id = $acDocument->id;
        $ac->save();

        $this->makeCustomer($this->target, 'ACME');
        $this->makeProduct($this->target, 'Widget', 'P1');

        $result = FiscalYearTransferService::transferDocument($acDocument, $this->target->id, $this->user);

        $this->assertTrue($result['success'], json_encode($result));
        $this->assertCount(1, $this->targetAncillaryCosts());
        $this->assertCount(1, $this->targetInvoices());
    }

    public function test_document_transfer_endpoint_rejects_same_fiscal_year(): void
    {
        $cash = $this->makeSubject($this->source, '101', 'Cash');
        $document = $this->makeDocument($this->source, [[$cash, 100]]);

        $response = $this->post(route('documents.transfer', $document), ['target_company_id' => $this->source->id]);

        $response->assertSessionHas('error');
        $this->assertSame(0, Document::withoutGlobalScopes()->where('company_id', $this->target->id)->count());
    }

    public function test_document_transfer_endpoint_succeeds_and_flashes_success(): void
    {
        $cash = $this->makeSubject($this->source, '101', 'Cash');
        $document = $this->makeDocument($this->source, [[$cash, 100]]);

        $response = $this->post(route('documents.transfer', $document), ['target_company_id' => $this->target->id]);

        $response->assertSessionHas('success');
        $this->assertSame(1, Document::withoutGlobalScopes()->where('company_id', $this->target->id)->count());
    }

    public function test_invoice_transfer_endpoint_flashes_errors_on_missing_dependency(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');
        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'amount' => 100]);
        $this->addProductItem($invoice, $product);

        $response = $this->post(route('invoices.transfer', $invoice), ['target_company_id' => $this->target->id]);

        $response->assertSessionHasErrors();
        $this->assertCount(0, $this->targetInvoices());
    }

    public function test_invoice_transfer_endpoint_succeeds(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');
        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'amount' => 100]);
        $this->addProductItem($invoice, $product);

        $this->makeCustomer($this->target, 'ACME');
        $this->makeProduct($this->target, 'Widget', 'P1');

        $response = $this->post(route('invoices.transfer', $invoice), ['target_company_id' => $this->target->id]);

        $response->assertSessionHas('success');
        $this->assertCount(1, $this->targetInvoices());
    }

    public function test_ancillary_cost_transfer_endpoint_succeeds(): void
    {
        $customer = $this->makeCustomer($this->source, 'ACME');
        $product = $this->makeProduct($this->source, 'Widget', 'P1');
        $invoice = $this->makeInvoice($this->source, ['customer_id' => $customer->id, 'amount' => 100]);
        $this->addProductItem($invoice, $product);
        $ac = $this->makeAncillaryCost($this->source, $invoice, $product);

        $this->makeCustomer($this->target, 'ACME');
        $this->makeProduct($this->target, 'Widget', 'P1');

        $response = $this->post(route('invoices.ancillary-costs.transfer', [$invoice, $ac]), ['target_company_id' => $this->target->id]);

        $response->assertSessionHas('success');
        $this->assertCount(1, $this->targetAncillaryCosts());
    }
}
