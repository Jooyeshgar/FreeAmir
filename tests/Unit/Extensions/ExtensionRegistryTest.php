<?php

namespace Tests\Unit\Extensions;

use App\Extensions\Contracts\InvoiceExtension;
use App\Extensions\InvoiceExtensionRegistry;
use App\Extensions\NavigationRegistry;
use App\Extensions\ViewSlotRegistry;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExtensionRegistryTest extends TestCase
{
    public function test_empty_registries_leave_the_request_and_query_unchanged(): void
    {
        self::assertNotContains('example', array_column(app(NavigationRegistry::class)->items(), 'id'));
        self::assertSame([], array_values(array_filter(
            app(ViewSlotRegistry::class)->items('invoice.show.panels'),
            fn (array $item): bool => $item['id'] !== 'invoice-categories',
        )));
        $registry = app(InvoiceExtensionRegistry::class);
        $query = Invoice::query();
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        self::assertSame([], $registry->validateRequest(Request::create('/', 'POST')));
        $registry->persist(new Invoice, []);
        $registry->extendQuery($query, Request::create('/'));
        self::assertSame($sql, $query->toSql());
        self::assertSame($bindings, $query->getBindings());
    }

    public function test_multiple_navigation_items_have_stable_ordering_and_area_isolation(): void
    {
        $registry = app(NavigationRegistry::class);
        $registry->register('later', ['area' => 'management', 'priority' => 20]);
        $registry->register('beta', ['area' => 'management', 'priority' => 10]);
        $registry->register('alpha', ['area' => 'management', 'priority' => 10]);
        $registry->register('workspace', ['area' => 'workspace', 'priority' => 1]);
        self::assertSame(['alpha', 'beta', 'later'], array_column($registry->items('management'), 'id'));
        self::assertSame(['workspace'], array_column($registry->items('workspace'), 'id'));
    }

    public function test_duplicate_navigation_ids_are_rejected_across_areas(): void
    {
        $registry = app(NavigationRegistry::class);
        $registry->register('same', ['area' => 'management']);
        $this->expectException(LogicException::class);
        $registry->register('same', ['area' => 'workspace']);
    }

    public function test_navigation_requires_an_area(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(NavigationRegistry::class)->register('example', []);
    }

    public function test_view_slots_support_multiple_items_with_stable_ordering(): void
    {
        $registry = app(ViewSlotRegistry::class);
        $registry->register('invoice.show.panels', 'later', 'example::later', priority: 20);
        $registry->register('invoice.show.panels', 'beta', 'example::beta', priority: 10);
        $registry->register('invoice.show.panels', 'alpha', 'example::alpha', priority: 10);
        $registry->register('invoice.edit.fields', 'alpha', 'example::alpha');
        self::assertSame(['alpha', 'beta', 'later'], array_column(
            array_filter($registry->items('invoice.show.panels'), fn (array $item): bool => $item['id'] !== 'invoice-categories'),
            'id',
        ));
        self::assertCount(2, $registry->items('invoice.edit.fields'));
        $this->expectException(LogicException::class);
        $registry->register('invoice.show.panels', 'alpha', 'example::duplicate');
    }

    public function test_view_slots_reject_missing_view_names(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ViewSlotRegistry::class)->register('invoice.show.panels', 'example', '');
    }

    public function test_view_data_is_resolved_from_each_context(): void
    {
        $registry = app(ViewSlotRegistry::class);
        $registry->register('invoice.show.panels', 'example', 'example::panel',
            fn (array $context): array => ['invoice_id' => $context['invoice']->getKey()]);
        $resolve = collect($registry->items('invoice.show.panels'))->firstWhere('id', 'example')['data'];
        self::assertSame(['invoice_id' => 11], $resolve(['invoice' => (new Invoice)->forceFill(['id' => 11])]));
        self::assertSame(['invoice_id' => 22], $resolve(['invoice' => (new Invoice)->forceFill(['id' => 22])]));
    }

    public function test_two_extensions_validate_and_persist_in_priority_order(): void
    {
        $registry = app(InvoiceExtensionRegistry::class);
        $registry->register('reference', ReferenceExtension::class, priority: 20);
        $registry->register('labels', LabelsExtension::class, priority: 10);
        $trace = new ExtensionTrace;
        $this->app->instance(ExtensionTrace::class, $trace);
        $payloads = $registry->validateRequest(Request::create('/', 'POST', ['extensions' => [
            'reference' => ['reference' => '  REF-1  '], 'labels' => ['ids' => ['1', '2']],
        ]]));
        self::assertSame(['labels' => ['ids' => [1, 2]], 'reference' => ['reference' => 'REF-1']], $payloads);
        $registry->persist((new Invoice)->forceFill(['id' => 42]), array_reverse($payloads, true));
        self::assertSame([
            ['labels', 42, ['ids' => [1, 2]]], ['reference', 42, ['reference' => 'REF-1']],
        ], $trace->persisted);
    }

    public function test_omitted_and_empty_payloads_have_different_meanings_across_requests(): void
    {
        $registry = app(InvoiceExtensionRegistry::class);
        $registry->register('labels', LabelsExtension::class);
        $trace = new ExtensionTrace;
        $this->app->instance(ExtensionTrace::class, $trace);
        $registry->persist(new Invoice, $registry->validateRequest(Request::create('/', 'POST')));
        self::assertSame([], $trace->persisted);
        $empty = $registry->validateRequest(Request::create('/', 'POST', ['extensions' => ['labels' => []]]));
        $registry->persist(new Invoice, $empty);
        self::assertSame([['labels', null, ['ids' => []]]], $trace->persisted);
        self::assertSame([], $registry->validateRequest(Request::create('/', 'POST')));
    }

    #[DataProvider('invalidPayloads')]
    public function test_malformed_or_unknown_payloads_are_rejected(mixed $payload): void
    {
        $registry = app(InvoiceExtensionRegistry::class);
        $registry->register('labels', LabelsExtension::class);
        $this->expectException(ValidationException::class);
        $registry->validateRequest(Request::create('/', 'POST', ['extensions' => $payload]));
    }

    public static function invalidPayloads(): array
    {
        return [
            'string root' => ['invalid'], 'null root' => [null],
            'string data' => [['labels' => 'invalid']], 'null data' => [['labels' => null]],
            'unknown handler' => [['unknown' => []]],
        ];
    }

    public function test_filters_from_two_packages_compose_on_the_same_builder(): void
    {
        $registry = app(InvoiceExtensionRegistry::class);
        $registry->register('reference', ReferenceExtension::class, priority: 20);
        $registry->register('labels', LabelsExtension::class, priority: 10);
        $query = Invoice::withoutGlobalScopes();
        $registry->extendQuery($query, Request::create('/', 'GET', ['minimum_number' => 5, 'creator_id' => 7]));
        self::assertSame([5, 7], $query->getBindings());
        $grammar = $query->getQuery()->getGrammar();
        self::assertStringContainsString($grammar->wrap('number').' >= ?', $query->toSql());
        self::assertStringContainsString($grammar->wrap('creator_id').' = ?', $query->toSql());
    }

    public function test_invoice_extension_keys_must_be_unique(): void
    {
        $registry = app(InvoiceExtensionRegistry::class);
        $registry->register('labels', LabelsExtension::class);
        $this->expectException(LogicException::class);
        $registry->register('labels', ReferenceExtension::class);
    }

    public function test_handler_must_implement_the_contract(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(InvoiceExtensionRegistry::class)->register('invalid', Invoice::class);
    }

    public function test_keys_cannot_contain_nested_input_paths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(InvoiceExtensionRegistry::class)->register('nested.key', LabelsExtension::class);
    }

    public function test_equal_priority_extensions_are_sorted_by_key(): void
    {
        $registry = app(InvoiceExtensionRegistry::class);
        $registry->register('reference', ReferenceExtension::class);
        $registry->register('labels', LabelsExtension::class);
        self::assertSame(['invoice_categories', 'labels', 'reference'], array_keys($registry->extensions()));
    }

    public function test_unregistered_persistence_payloads_are_programming_errors(): void
    {
        $this->expectException(LogicException::class);
        app(InvoiceExtensionRegistry::class)->persist(new Invoice, ['unknown' => []]);
    }

    public function test_handlers_are_resolved_per_operation(): void
    {
        $registry = app(InvoiceExtensionRegistry::class);
        $registry->register('labels', LabelsExtension::class);
        $instances = [];
        $this->app->bind(LabelsExtension::class, function () use (&$instances) {
            $handler = new LabelsExtension(new ExtensionTrace);
            $instances[] = $handler;

            return $handler;
        });
        $request = Request::create('/', 'POST', ['extensions' => ['labels' => []]]);
        $registry->validateRequest($request);
        $registry->validateRequest($request);
        self::assertCount(2, $instances);
        self::assertNotSame($instances[0], $instances[1]);
    }
}

class ExtensionTrace
{
    public array $persisted = [];
}

class LabelsExtension implements InvoiceExtension
{
    public function __construct(private readonly ExtensionTrace $trace) {}

    public function validate(array $input, ?Model $invoice = null): array
    {
        return ['ids' => array_map(intval(...), $input['ids'] ?? [])];
    }

    public function persist(Model $invoice, array $data): void
    {
        $this->trace->persisted[] = ['labels', $invoice->getKey(), $data];
    }

    public function extendQuery(Builder $query, Request $request): void
    {
        if ($request->filled('minimum_number')) {
            $query->where('number', '>=', (int) $request->input('minimum_number'));
        }
    }
}

class ReferenceExtension implements InvoiceExtension
{
    public function __construct(private readonly ExtensionTrace $trace) {}

    public function validate(array $input, ?Model $invoice = null): array
    {
        return ['reference' => trim($input['reference'] ?? '')];
    }

    public function persist(Model $invoice, array $data): void
    {
        $this->trace->persisted[] = ['reference', $invoice->getKey(), $data];
    }

    public function extendQuery(Builder $query, Request $request): void
    {
        if ($request->filled('creator_id')) {
            $query->where('creator_id', (int) $request->input('creator_id'));
        }
    }
}
