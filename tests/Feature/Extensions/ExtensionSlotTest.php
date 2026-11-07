<?php

namespace Tests\Feature\Extensions;

use App\Extensions\ViewSlotRegistry;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\View;
use Illuminate\View\ViewException;
use RuntimeException;
use Tests\TestCase;
use TypeError;
use UnexpectedValueException;

class ExtensionSlotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addNamespace('extension-tests', __DIR__.'/fixtures');
        Exceptions::fake();
    }

    public function test_generic_context_reaches_callback_and_view_on_each_render(): void
    {
        app(ViewSlotRegistry::class)->register('customer.show.panels', 'details', 'extension-tests::context',
            fn (array $context): array => ['label' => 'customer-'.$context['customer']->getKey()]);

        foreach ([42, 43] as $id) {
            $html = Blade::render('<x-extension-slot slot="customer.show.panels" :context="$context" />', [
                'context' => ['customer' => (new Customer)->forceFill(['id' => $id])],
            ]);

            self::assertStringContainsString('customer-'.$id, $html);
            self::assertStringContainsString('data-customer="'.$id.'"', $html);
        }

        Exceptions::assertNothingReported();
    }

    public function test_existing_invoice_binding_is_preserved(): void
    {
        app(ViewSlotRegistry::class)->register('invoice.edit.fields.test', 'invoice', 'extension-tests::extension-stub',
            fn (array $context): array => ['label' => 'invoice-'.$context['invoice']->getKey()]);

        $html = Blade::render('<x-extension-slot slot="invoice.edit.fields.test" :invoice="$invoice" :context="$context" />', [
            'invoice' => (new Invoice)->forceFill(['id' => 11]),
            'context' => ['invoice' => (new Invoice)->forceFill(['id' => 22])],
        ]);

        self::assertStringContainsString('invoice-11', $html);
        self::assertStringContainsString('data-invoice="11"', $html);
        Exceptions::assertNothingReported();
    }

    public function test_context_is_available_to_views_without_a_callback(): void
    {
        app(ViewSlotRegistry::class)->register('customer.show.panels', 'details', 'extension-tests::context');

        $html = Blade::render('<x-extension-slot slot="customer.show.panels" :context="$context" />', [
            'context' => ['customer' => (new Customer)->forceFill(['id' => 42]), 'label' => 'customer-details'],
        ]);

        self::assertStringContainsString('data-customer="42"', $html);
        self::assertStringContainsString('customer-details', $html);
        Exceptions::assertNothingReported();
    }

    public function test_legacy_callback_still_receives_a_null_invoice_without_a_binding(): void
    {
        app(ViewSlotRegistry::class)->register('invoice.create.fields.test', 'invoice', 'extension-tests::extension-stub',
            fn (array $context): array => ['label' => $context['invoice'] === null ? 'new-invoice' : 'existing-invoice']);

        $html = Blade::render('<x-extension-slot slot="invoice.create.fields.test" />');

        self::assertStringContainsString('new-invoice', $html);
        Exceptions::assertNothingReported();
    }

    public function test_callback_exceptions_and_php_errors_do_not_break_other_items(): void
    {
        $registry = app(ViewSlotRegistry::class);
        $registry->register('example.panels', 'before', 'extension-tests::extension-stub', fn (): array => ['label' => 'before-item'], priority: 1);
        $registry->register('example.panels', 'exception', 'extension-tests::extension-stub', function (): array {
            throw new RuntimeException('callback failed');
        });
        $registry->register('example.panels', 'error', 'extension-tests::extension-stub', function (): array {
            throw new TypeError('callback type error');
        });
        $registry->register('example.panels', 'after', 'extension-tests::extension-stub', fn (): array => ['label' => 'after-item'], priority: 200);

        $html = Blade::render('<x-extension-slot slot="example.panels" />');

        self::assertStringContainsString('before-item', $html);
        self::assertStringContainsString('after-item', $html);
        self::assertStringNotContainsString('callback failed', $html);
        self::assertStringNotContainsString('callback type error', $html);
        self::assertLessThan(strpos($html, 'after-item'), strpos($html, 'before-item'));
        Exceptions::assertReported(RuntimeException::class);
        Exceptions::assertReported(TypeError::class);
    }

    public function test_blade_error_discards_partial_output_and_preserves_healthy_items(): void
    {
        Blade::component('extension-tests::wrapper', 'extension-test-wrapper');
        $registry = app(ViewSlotRegistry::class);
        $registry->register('example.panels', 'broken', 'extension-tests::broken');
        $registry->register('example.panels', 'healthy', 'extension-tests::extension-stub', fn (): array => ['label' => 'healthy-item']);

        $html = Blade::render(<<<'BLADE'
            @push('host-assets')
                host-asset
            @endpush
            <x-extension-test-wrapper>
                <x-extension-slot slot="example.panels" />
            </x-extension-test-wrapper>
            @stack('host-assets')
            BLADE);

        self::assertStringContainsString('healthy-item', $html);
        self::assertStringNotContainsString('partial-broken-output', $html);
        self::assertStringContainsString('<section>', $html);
        self::assertStringContainsString('host-asset', $html);
        Exceptions::assertReported(fn (ViewException $exception): bool => str_contains($exception->getMessage(), 'view failed'));
    }

    public function test_invalid_callback_return_is_reported_and_skipped(): void
    {
        app(ViewSlotRegistry::class)->register('example.panels', 'invalid', 'extension-tests::extension-stub', fn () => 'invalid data');

        self::assertSame('', trim(Blade::render('<x-extension-slot slot="example.panels" />')));
        Exceptions::assertReported(UnexpectedValueException::class);
    }

    public function test_missing_views_and_null_results_are_skipped_without_errors(): void
    {
        $registry = app(ViewSlotRegistry::class);
        $registry->register('example.panels', 'missing', 'extension-tests::missing', function (): array {
            throw new RuntimeException('must not execute for missing view');
        });
        $registry->register('example.panels', 'hidden', 'extension-tests::extension-stub', fn () => null);

        self::assertSame('', trim(Blade::render('<x-extension-slot slot="example.panels" />')));
        Exceptions::assertNothingReported();
    }

    public function test_view_data_overrides_context_and_is_escaped_by_blade(): void
    {
        app(ViewSlotRegistry::class)->register('example.panels', 'safe', 'extension-tests::extension-stub', fn (): array => ['label' => '<script>bad()</script>']);

        $html = Blade::render('<x-extension-slot slot="example.panels" :context="$context" />', ['context' => ['label' => 'context-label']]);

        self::assertStringContainsString('&lt;script&gt;bad()&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('context-label', $html);
        Exceptions::assertNothingReported();
    }
}
