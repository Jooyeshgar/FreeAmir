<?php

namespace Tests\Feature\Extensions;

use App\Extensions\Contracts\HostContext;
use App\Extensions\ExtensionApi;
use App\Extensions\InvoiceExtensionRegistry;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HostContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_and_context_boot_without_an_enterprise_package(): void
    {
        config(['active-fiscal-year-id' => null]);
        self::assertSame(1, ExtensionApi::VERSION);
        self::assertNull(app(HostContext::class)->user());
        self::assertNull(app(HostContext::class)->activeFiscalYearId());
        self::assertSame([], app(InvoiceExtensionRegistry::class)->extensions());
    }

    public function test_context_follows_current_user_and_year_without_caching_them(): void
    {
        $context = app(HostContext::class);
        $user = User::factory()->create();
        $first = FiscalYear::factory()->create();
        $second = FiscalYear::factory()->create();
        $user->fiscalYears()->attach([$first->id, $second->id]);
        $this->actingAs($user);
        config(['active-fiscal-year-id' => $first->id]);

        self::assertSame($user->id, $context->user()->getAuthIdentifier());
        self::assertSame($first->id, $context->activeFiscalYearId());
        $context->authorizeFiscalYear($first->id);

        config(['active-fiscal-year-id' => $second->id]);
        self::assertSame($second->id, $context->activeFiscalYearId());
        $context->authorizeFiscalYear($second->id);
        $this->app['auth']->guard()->logout();
        self::assertNull($context->user());
    }

    public function test_guest_cannot_authorize_a_year(): void
    {
        $this->expectException(AuthenticationException::class);
        app(HostContext::class)->authorizeFiscalYear(1);
    }

    public function test_an_unassigned_selected_year_is_rejected(): void
    {
        $year = FiscalYear::factory()->create();
        $this->actingAs(User::factory()->create());
        config(['active-fiscal-year-id' => $year->id]);
        $this->expectException(AuthorizationException::class);
        app(HostContext::class)->authorizeFiscalYear($year->id);
    }

    public function test_an_assigned_but_non_active_year_is_rejected(): void
    {
        $user = User::factory()->create();
        $first = FiscalYear::factory()->create();
        $second = FiscalYear::factory()->create();
        $user->fiscalYears()->attach([$first->id, $second->id]);
        $this->actingAs($user);
        config(['active-fiscal-year-id' => $first->id]);
        $this->expectException(AuthorizationException::class);
        app(HostContext::class)->authorizeFiscalYear($second->id);
    }

    public function test_context_does_not_fall_back_to_an_existing_database_year(): void
    {
        $year = FiscalYear::factory()->create();
        $user = User::factory()->create();
        $user->fiscalYears()->attach($year);
        $this->actingAs($user);
        config(['active-fiscal-year-id' => null]);
        self::assertNull(app(HostContext::class)->activeFiscalYearId());
        $this->expectException(AuthorizationException::class);
        app(HostContext::class)->authorizeFiscalYear($year->id);
    }

    public function test_context_reuses_the_closed_year_mutation_guard(): void
    {
        $year = FiscalYear::factory()->create(['closed_at' => now()]);
        $user = User::factory()->create();
        $user->fiscalYears()->attach($year);
        $this->actingAs($user);
        config(['active-fiscal-year-id' => $year->id]);
        app(HostContext::class)->authorizeFiscalYear($year->id);
        $this->expectException(ValidationException::class);
        app(HostContext::class)->ensureFiscalYearOpen($year->id);
    }

    public function test_an_accessible_open_year_is_accepted(): void
    {
        $year = FiscalYear::factory()->create();
        $user = User::factory()->create();
        $user->fiscalYears()->attach($year);
        $this->actingAs($user);
        config(['active-fiscal-year-id' => $year->id]);
        app(HostContext::class)->ensureFiscalYearOpen($year->id);
        self::assertFalse($year->fresh()->isClosed());
    }
}
