<?php

namespace Tests\Feature\Extensions;

use App\Enums\InvoiceType;
use App\Extensions\Contracts\InvoiceExtension;
use App\Extensions\Events\VoidInvoiceCreated;
use App\Extensions\InvoiceExtensionRegistry;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class InvoiceExtensionPersistenceTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    private Customer $customer;

    private Warehouse $warehouse;

    private Service $service;

    private Product $product;

    private PersistenceTestExtension $extension;

    private int $transactionLevel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $year = FiscalYear::factory()->create(['year' => 1405]);
        $this->actingAs($this->user);
        config(['active-fiscal-year-id' => $year->id, 'amir.beginning_inventory' => '29']);
        $this->importSubjects($year->id);
        $this->importConfigs($year->id);
        $group = CustomerGroup::factory()->withSubject()->create();
        $this->customer = Customer::factory()->withGroup($group)->withSubject()->create();
        $this->service = Service::factory()->withGroup()->withSubject()->create();
        $productGroup = ProductGroup::factory()->withSubjects()->create();
        $this->product = Product::factory()->withGroup($productGroup)->withSubjects()->create(['quantity' => 0]);
        $this->warehouse = Warehouse::create(['name' => 'Main', 'code' => 'MAIN', 'fiscal_year_id' => $year->id]);

        $this->extension = new PersistenceTestExtension;
        $this->app->instance(PersistenceTestExtension::class, $this->extension);
        app(InvoiceExtensionRegistry::class)->register('persistence_test', PersistenceTestExtension::class);
        $this->transactionLevel = DB::transactionLevel();
    }

    public static function creationPaths(): array
    {
        return [
            'draft' => [InvoiceType::SELL, false],
            'purchase with approval' => [InvoiceType::BUY, true],
            'opening draft' => [InvoiceType::BEGINNING_INVENTORY, false],
            'opening with approval' => [InvoiceType::BEGINNING_INVENTORY, true],
        ];
    }

    #[DataProvider('creationPaths')]
    public function test_creation_persists_extension_inside_the_invoice_transaction(InvoiceType $type, bool $approved): void
    {
        $invoice = InvoiceService::createInvoice($this->user, $this->data($type), $this->items($type), $approved, $this->persister('created'))['invoice'];

        self::assertSame(['created'], $this->extension->persisted[$invoice->id]);
        self::assertSame([$this->transactionLevel + 1], $this->extension->levels);
    }

    #[DataProvider('creationPaths')]
    public function test_extension_failure_rolls_back_invoice_items_and_posting(InvoiceType $type, bool $approved): void
    {
        $before = $this->snapshot();
        $this->extension->fail = true;

        $this->assertExtensionFailure(fn () => InvoiceService::createInvoice(
            $this->user, $this->data($type), $this->items($type), $approved, $this->persister('failed'),
        ));

        self::assertSame($before, $this->snapshot());
        self::assertSame([], $this->extension->persisted);
    }

    public static function updatePaths(): array
    {
        return [
            'draft' => [InvoiceType::BUY, false],
            'approved' => [InvoiceType::BUY, true],
            'opening draft' => [InvoiceType::BEGINNING_INVENTORY, false],
            'opening approved' => [InvoiceType::BEGINNING_INVENTORY, true],
        ];
    }

    #[DataProvider('updatePaths')]
    public function test_update_persists_extension_and_failure_restores_previous_financial_data(InvoiceType $type, bool $approved): void
    {
        $data = $this->data($type);
        $items = $this->items($type);
        $invoice = InvoiceService::createInvoice($this->user, $data, $items, $approved, $this->persister('original'))['invoice'];
        $data['title'] = 'Updated invoice';
        InvoiceService::updateInvoice($invoice->id, $data, $items, $approved, $this->persister('updated'));
        self::assertSame(['original', 'updated'], $this->extension->persisted[$invoice->id]);
        self::assertSame([$this->transactionLevel + 1, $this->transactionLevel + 1], $this->extension->levels);

        $before = $this->snapshot();
        $this->extension->fail = true;
        $data['title'] = 'Must roll back';
        $items[0]['unit'] = 200;
        $this->assertExtensionFailure(fn () => InvoiceService::updateInvoice($invoice->id, $data, $items, $approved, $this->persister('failed')));

        self::assertSame($before, $this->snapshot());
        self::assertSame(['original', 'updated'], $this->extension->persisted[$invoice->id]);
    }

    public function test_void_event_persists_extension_and_listener_failure_rolls_back_the_void_invoice(): void
    {
        $invoice = InvoiceService::createInvoice($this->user, $this->data(InvoiceType::SELL), $this->items(InvoiceType::SELL))['invoice'];
        app(InvoiceService::class)->changeInvoiceStatus($invoice, 'approved');
        self::assertTrue($invoice->fresh()->status->isApproved());
        Event::listen(VoidInvoiceCreated::class, function (VoidInvoiceCreated $event) use ($invoice): void {
            self::assertSame($invoice->id, $event->source->id);
            ($this->persister('derived'))($event->target);
        });
        $before = $this->snapshot();
        $this->extension->fail = true;
        $this->assertExtensionFailure(fn () => app(InvoiceService::class)->voidInvoice($invoice->fresh(), $this->user, '2026-08-11', 2));
        self::assertSame($before, $this->snapshot());
        self::assertSame([], $this->extension->persisted);

        $this->extension->fail = false;
        $void = app(InvoiceService::class)->voidInvoice($invoice->fresh(), $this->user, '2026-08-11', 2)['invoice'];
        self::assertSame(['derived'], $this->extension->persisted[$void->id]);
        self::assertSame([$this->transactionLevel + 1, $this->transactionLevel + 1], $this->extension->levels);
    }

    private function data(InvoiceType $type): array
    {
        return [
            'title' => 'Extension test', 'date' => '2026-08-10', 'number' => 1, 'document_number' => 1,
            'invoice_type' => $type, 'customer_id' => $type->isBeginningInventory() ? null : $this->customer->id,
            'warehouse_id' => $type->isBeginningInventory() ? $this->warehouse->id : null,
        ];
    }

    private function items(InvoiceType $type): array
    {
        return [[
            'itemable_type' => $type->isBeginningInventory() ? 'product' : 'service',
            'itemable_id' => $type->isBeginningInventory() ? $this->product->id : $this->service->id,
            'quantity' => 2, 'unit' => 100, 'discount' => 0, 'vat' => 0,
        ]];
    }

    private function persister(string $value): \Closure
    {
        $registry = app(InvoiceExtensionRegistry::class);
        $payloads = $registry->validateRequest(Request::create('/', 'POST', ['extensions' => ['persistence_test' => ['value' => $value]]]));

        return static fn (Invoice $invoice) => $registry->persist($invoice, $payloads);
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['invoices', 'invoice_items', 'documents', 'transactions', 'products', 'warehouse_product_stocks'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }

    private function assertExtensionFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('The extension must run and propagate its failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('extension persistence failed', $exception->getMessage());
        }
    }
}

class PersistenceTestExtension implements InvoiceExtension
{
    public bool $fail = false;

    public array $levels = [];

    public array $persisted = [];

    public function validate(array $input, ?Model $invoice = null): array
    {
        return $input;
    }

    public function persist(Model $invoice, array $data): void
    {
        $this->levels[] = DB::transactionLevel();
        if ($this->fail) {
            throw new RuntimeException('extension persistence failed');
        }
        $this->persisted[$invoice->getKey()][] = $data['value'];
    }

    public function extendQuery(Builder $query, Request $request): void {}
}
