<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PublicHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PublicHolidayTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'public-holidays.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);
    }

    private function makePublicHoliday(array $overrides = []): PublicHoliday
    {
        return PublicHoliday::factory()->create(array_merge([
            'company_id' => $this->companyId,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-03-21',
            'name' => 'Nowruz',
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_lists_public_holidays_for_active_company(): void
    {
        $this->makePublicHoliday(['date' => '2026-03-21', 'name' => 'Nowruz']);
        $this->makePublicHoliday(['date' => '2026-04-01', 'name' => 'Islamic Republic Day']);

        $response = $this->get(route('public-holidays.index'));

        $response->assertStatus(200);
        $response->assertSee('Nowruz');
        $response->assertSee('Islamic Republic Day');
    }

    public function test_index_filters_by_name(): void
    {
        $this->makePublicHoliday(['date' => '2026-03-21', 'name' => 'Nowruz']);
        $this->makePublicHoliday(['date' => '2026-04-01', 'name' => 'Islamic Republic Day']);

        $response = $this->get(route('public-holidays.index', ['name' => 'Nowruz']));

        $response->assertStatus(200);
        $response->assertSee('Nowruz');
        $response->assertDontSee('Islamic Republic Day');
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('public-holidays.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_a_public_holiday_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('public-holidays.store'), $payload);

        $response->assertRedirect(route('public-holidays.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('public_holidays', [
            'company_id' => $this->companyId,
            'date' => '2026-03-21',
            'name' => 'Nowruz',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('public-holidays.store'), []);

        $response->assertSessionHasErrors(['date', 'name']);
    }

    public function test_store_rejects_duplicate_date(): void
    {
        $this->makePublicHoliday(['date' => '2026-03-21', 'name' => 'Nowruz']);

        $response = $this->post(route('public-holidays.store'), $this->validPayload([
            'date' => '2026-03-21',
            'name' => 'Another Holiday',
        ]));

        $response->assertSessionHasErrors(['date']);
    }

    public function test_store_rejects_invalid_date_format(): void
    {
        $response = $this->post(route('public-holidays.store'), $this->validPayload([
            'date' => 'not-a-date',
        ]));

        $response->assertSessionHasErrors(['date']);
    }

    public function test_store_rejects_name_exceeding_max_length(): void
    {
        $response = $this->post(route('public-holidays.store'), $this->validPayload([
            'name' => str_repeat('a', 201),
        ]));

        $response->assertSessionHasErrors(['name']);
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200(): void
    {
        $publicHoliday = $this->makePublicHoliday();

        $response = $this->get(route('public-holidays.edit', $publicHoliday));

        $response->assertStatus(200);
        $response->assertSee($publicHoliday->name);
    }

    public function test_update_modifies_public_holiday_and_redirects(): void
    {
        $publicHoliday = $this->makePublicHoliday(['date' => '2026-03-21', 'name' => 'Nowruz']);

        $response = $this->put(route('public-holidays.update', $publicHoliday), $this->validPayload([
            'name' => 'Nowruz - New Year',
        ]));

        $response->assertRedirect(route('public-holidays.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('public_holidays', [
            'id' => $publicHoliday->id,
            'name' => 'Nowruz - New Year',
        ]);
    }

    public function test_update_rejects_duplicate_date_for_another_record(): void
    {
        $first = $this->makePublicHoliday(['date' => '2026-03-21', 'name' => 'Nowruz']);
        $second = $this->makePublicHoliday(['date' => '2026-04-01', 'name' => 'Islamic Republic Day']);

        $response = $this->put(route('public-holidays.update', $second), $this->validPayload([
            'date' => '2026-03-21', // conflicts with $first
            'name' => 'Islamic Republic Day',
        ]));

        $response->assertSessionHasErrors(['date']);
    }

    public function test_update_allows_same_date_for_same_record(): void
    {
        $publicHoliday = $this->makePublicHoliday(['date' => '2026-03-21', 'name' => 'Nowruz']);

        $response = $this->put(route('public-holidays.update', $publicHoliday), $this->validPayload([
            'date' => '2026-03-21',
            'name' => 'Nowruz Updated',
        ]));

        $response->assertRedirect(route('public-holidays.index'));
        $this->assertDatabaseHas('public_holidays', [
            'id' => $publicHoliday->id,
            'name' => 'Nowruz Updated',
        ]);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_public_holiday_and_redirects(): void
    {
        $publicHoliday = $this->makePublicHoliday();

        $response = $this->delete(route('public-holidays.destroy', $publicHoliday));

        $response->assertRedirect(route('public-holidays.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('public_holidays', ['id' => $publicHoliday->id]);
    }

    // ----------------------------------------------------------------
    // guest access
    // ----------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        auth()->logout();

        $response = $this->get(route('public-holidays.index'));

        $response->assertRedirect(route('login'));
    }
}
