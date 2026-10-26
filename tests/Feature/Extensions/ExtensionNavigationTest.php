<?php

namespace Tests\Feature\Extensions;

use App\Extensions\NavigationRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ExtensionNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_registry_renders_without_an_optional_package(): void
    {
        self::assertSame('', trim($this->navigation()));
        if (! class_exists('Amir\\InvoiceCategories\\InvoiceCategoriesServiceProvider')) {
            self::assertFalse(Route::has('invoice-categories.index'));
        }
    }

    public function test_navigation_requires_a_user_and_the_registered_permission(): void
    {
        Route::get('/extension-example', fn () => 'example')->name('extension-example');
        app(NavigationRegistry::class)->register('example', [
            'area' => 'navigation.management', 'label' => 'Extension Example',
            'route' => 'extension-example', 'permission' => 'access-super-admin-panel',
        ]);
        self::assertSame('', trim($this->navigation()));
        $user = User::factory()->create();
        $this->actingAs($user);
        self::assertSame('', trim($this->navigation()));
        $user->givePermissionTo(Permission::findOrCreate('access-super-admin-panel', 'web'));
        self::assertStringContainsString('Extension Example', $this->navigation());
    }

    public function test_multiple_packages_render_in_order_and_escape_their_labels(): void
    {
        $this->actingAs(User::factory()->create());
        Route::get('/extension-alpha', fn () => 'alpha')->name('extension-alpha');
        Route::get('/extension-beta', fn () => 'beta')->name('extension-beta');
        $registry = app(NavigationRegistry::class);
        $registry->register('beta', [
            'area' => 'navigation.management', 'label' => '<script>beta</script>',
            'route' => 'extension-beta', 'priority' => 20,
        ]);
        $registry->register('alpha', [
            'area' => 'navigation.management', 'label' => 'Alpha',
            'route' => 'extension-alpha', 'priority' => 10,
        ]);
        $registry->register('missing-route', [
            'area' => 'navigation.management', 'label' => 'Missing route', 'route' => 'not-installed',
        ]);
        $html = $this->navigation();
        self::assertLessThan(strpos($html, 'beta'), strpos($html, 'Alpha'));
        self::assertStringContainsString('&lt;script&gt;beta&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('Missing route', $html);
    }

    public function test_visibility_uses_the_current_context_on_each_render(): void
    {
        $this->actingAs(User::factory()->create());
        Route::get('/extension-context', fn () => 'context')->name('extension-context');
        app(NavigationRegistry::class)->register('context', [
            'area' => 'navigation.management', 'label' => 'Context entry', 'route' => 'extension-context',
            'visible' => static fn (array $context): bool => $context['fiscal_year_id'] === 12,
        ]);
        config(['active-fiscal-year-id' => null]);
        self::assertSame('', trim($this->navigation()));
        config(['active-fiscal-year-id' => 12]);
        self::assertStringContainsString('Context entry', $this->navigation());
        config(['active-fiscal-year-id' => 13]);
        self::assertSame('', trim($this->navigation()));
    }

    private function navigation(): string
    {
        Route::getRoutes()->refreshNameLookups();

        return Blade::render('<x-extension-navigation area="navigation.management" />');
    }
}
