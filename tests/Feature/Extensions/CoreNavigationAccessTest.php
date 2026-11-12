<?php

namespace Tests\Feature\Extensions;

use App\Extensions\Contracts\HostContext;
use App\Extensions\MenuItem;
use App\Extensions\NavigationRegistry;
use App\Models\FiscalYear;
use App\Models\User;
use App\View\Components\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CoreNavigationAccessTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('restrictedRoles')]
    public function test_restricted_roles_only_see_their_authorized_menu_links(array $permissions, array $shown, array $hidden): void
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate('Navigation reviewer', 'web');
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        $menu = new Menu(app(NavigationRegistry::class), app(HostContext::class));
        $ids = $this->ids($menu->items);

        foreach ($shown as $id) {
            self::assertContains($id, $ids);
        }
        foreach ($hidden as $id) {
            self::assertNotContains($id, $ids);
        }
    }

    public static function restrictedRoles(): array
    {
        return [
            'no module permission' => [[], ['core.management.about'], ['core.home', 'core.invoices', 'core.accounting', 'core.reports', 'core.warehouse', 'core.hr', 'core.crm']],
            'ancillary costs only' => [
                ['ancillary-costs.index'],
                ['core.invoices', 'core.invoices.purchases', 'core.invoices.purchases.ancillary-costs'],
                ['core.invoices.sales', 'core.invoices.purchases.buy', 'core.invoices.purchases.buy-service', 'core.invoices.purchases.return-buy', 'core.invoices.purchases.service-buy-return', 'core.invoices.beginning-inventory'],
            ],
            'cheques only' => [['cheques.index'], ['core.accounting', 'core.accounting.cheques'], ['core.accounting.documents', 'core.accounting.create-document']],
            'moadian history only' => [
                ['invoices.moadian-histories.index'], ['core.invoices', 'core.invoices.sales', 'core.invoices.sales.moadian-histories'],
                ['core.invoices.sales.sell', 'core.invoices.sales.return-sell', 'core.invoices.sales.voided-sell', 'core.invoices.purchases'],
            ],
            'invoice list only' => [
                ['invoices.index'], ['core.invoices.sales.sell', 'core.invoices.purchases.buy', 'core.invoices.beginning-inventory'],
                ['core.invoices.sales.moadian-histories', 'core.invoices.purchases.ancillary-costs'],
            ],
            'documents only' => [['documents.index'], ['core.accounting.documents'], ['core.accounting.cheques', 'core.accounting.create-document']],
        ];
    }

    #[DataProvider('locales')]
    public function test_desktop_and_mobile_render_the_same_links_in_each_locale(string $locale, string $direction): void
    {
        $this->withoutVite();
        $this->app->setLocale($locale);
        $user = User::factory()->create();
        $year = FiscalYear::factory()->create();
        $user->fiscalYears()->sync([$year->id]);
        $user->givePermissionTo(Permission::findOrCreate('invoices.index', 'web'));
        $this->actingAs($user)->withCookies(['active-fiscal-year-id' => (string) $year->id]);
        Route::get('/navigation-review', fn () => Blade::render('<x-app-layout>Navigation review</x-app-layout>'))->middleware(['web', 'auth']);

        $response = $this->get('/navigation-review')->assertOk();
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        self::assertSame($direction, $document->documentElement->getAttribute('dir'));
        $desktop = $xpath->query('(//ul[@data-main-menu and contains(concat(" ", normalize-space(@class), " "), " app-main-menu ")])[1]//a');
        $mobile = $xpath->query('//ul[contains(concat(" ", normalize-space(@class), " "), " app-mobile-menu ")]//a');
        $hrefs = static fn ($nodes): array => array_map(static fn ($node): string => $node->getAttribute('href'), iterator_to_array($nodes));
        self::assertNotEmpty($hrefs($desktop));
        self::assertSame($hrefs($desktop), $hrefs($mobile));
    }

    public static function locales(): array
    {
        return [['fa', 'rtl'], ['en', 'ltr']];
    }

    public function test_active_state_matches_path_parameters_before_query_parameters(): void
    {
        $route = Route::get('/navigation-review/{section}', fn () => 'review')->name('navigation-review.section');
        $request = Request::create('/navigation-review/7?section=9');
        $route->bind($request);
        $request->setRouteResolver(static fn () => $route);
        $this->app->instance('request', $request);
        $menu = new Menu(app(NavigationRegistry::class), app(HostContext::class));

        self::assertTrue($menu->isActive(new MenuItem('matching', 'Matching', 'navigation-review.section', routeParameters: ['section' => 7])));
        self::assertFalse($menu->isActive(new MenuItem('other', 'Other', 'navigation-review.section', routeParameters: ['section' => 9])));
    }

    public function test_active_state_uses_the_route_key_of_a_bound_model(): void
    {
        $user = User::factory()->create();
        $route = Route::get('/navigation-review/users/{user}', fn () => 'review')->name('navigation-review.user');
        $request = Request::create('/navigation-review/users/'.$user->id);
        $route->bind($request);
        $route->setParameter('user', $user);
        $request->setRouteResolver(static fn () => $route);
        $this->app->instance('request', $request);
        $menu = new Menu(app(NavigationRegistry::class), app(HostContext::class));

        self::assertTrue($menu->isActive(new MenuItem('matching', 'Matching', 'navigation-review.user', routeParameters: ['user' => $user->id])));
        self::assertFalse($menu->isActive(new MenuItem('other', 'Other', 'navigation-review.user', routeParameters: ['user' => $user->id + 1])));
    }

    private function ids(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            $ids[] = $node['item']->id;
            array_push($ids, ...$this->ids($node['children']));
        }

        return $ids;
    }
}
