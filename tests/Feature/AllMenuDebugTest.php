<?php

namespace Tests\Feature;

use App\Enums\ThursdayStatus;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Page-open coverage only. CRUD, calculations and browser interactions are tracked separately. */
class AllMenuDebugTest extends TestCase
{
    use RefreshDatabase;

    public static function menuLinks(): array
    {
        $rows = json_decode(file_get_contents(__DIR__.'/../Fixtures/debug-menu-links.json'), true, flags: JSON_THROW_ON_ERROR);
        $cases = [];
        foreach ($rows as $row) {
            $cases[$row['id']] = [$row['route'], $row['route_parameters']];
        }

        return $cases;
    }

    #[DataProvider('menuLinks')]
    public function test_authorized_persian_user_can_open_each_existing_menu_page(string $route, array $parameters): void
    {
        Http::preventStrayRequests();
        Notification::fake();
        $year = FiscalYear::factory()->create(['year' => 1405]);
        $year->company->update(['name' => 'شرکت فرضی سپهر']);
        config(['active-fiscal-year-id' => $year->id, 'active-company-fiscal-year' => 1405]);
        $this->seed([SubjectSeeder::class, ConfigSeeder::class]);
        $user = User::factory()->create(['name' => 'مریم احمدی']);
        $year->users()->attach($user);
        $permissions = collect(Route::getRoutes())->map(fn ($r) => $r->getName())->filter()->push('access-super-admin-panel')->unique();
        Permission::insert($permissions->map(fn ($name) => ['name' => $name, 'guard_name' => 'web'])->all());
        $user->givePermissionTo(Permission::all());
        $site = WorkSite::factory()->create(['fiscal_year_id' => $year->id]);
        $shift = WorkShift::factory()->create(['fiscal_year_id' => $year->id, 'thursday_status' => ThursdayStatus::HALF_DAY]);
        Employee::factory()->create(['fiscal_year_id' => $year->id, 'work_site_id' => $site->id, 'work_shift_id' => $shift->id, 'user_id' => $user->id, 'first_name' => 'مریم', 'last_name' => 'احمدی']);
        $this->actingAs($user)->withCookies(['active-fiscal-year-id' => (string) $year->id]);
        $response = $this->get(route($route, $parameters));
        $response->assertOk();
        $this->assertNotEmpty($response->getContent());
        Http::assertNothingSent();
        Notification::assertNothingSent();
    }
}
