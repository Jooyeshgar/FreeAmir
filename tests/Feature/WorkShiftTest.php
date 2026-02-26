<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkShiftTest extends TestCase
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
            Permission::firstOrCreate(['name' => 'work-shifts.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);
    }

    private function makeWorkShift(array $overrides = []): WorkShift
    {
        return WorkShift::factory()->create(array_merge([
            'company_id' => $this->companyId,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Morning Shift',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'crosses_midnight' => '0',
            'float_before' => '10',
            'float_after' => '10',
            'break' => '30',
            'is_active' => '1',
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_lists_work_shifts_for_active_company(): void
    {
        $this->makeWorkShift(['name' => 'Morning Shift']);
        $this->makeWorkShift(['name' => 'Night Shift']);

        $response = $this->get(route('work-shifts.index'));

        $response->assertStatus(200);
        $response->assertSee('Morning Shift');
        $response->assertSee('Night Shift');
    }

    public function test_index_does_not_show_other_company_work_shifts(): void
    {
        $otherCompany = Company::factory()->create();
        WorkShift::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Other Shift']);

        $response = $this->get(route('work-shifts.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Other Shift');
    }

    public function test_index_filters_by_name(): void
    {
        $this->makeWorkShift(['name' => 'Morning Shift']);
        $this->makeWorkShift(['name' => 'Night Shift']);

        $response = $this->get(route('work-shifts.index', ['search' => 'Morning']));

        $response->assertStatus(200);
        $response->assertSee('Morning Shift');
        $response->assertDontSee('Night Shift');
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('work-shifts.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_a_work_shift_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('work-shifts.store'), $payload);

        $response->assertRedirect(route('work-shifts.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('work_shifts', [
            'company_id' => $this->companyId,
            'name' => 'Morning Shift',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break' => 30,
            'float_before' => 10,
            'float_after' => 10,
            'is_active' => true,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('work-shifts.store'), []);

        $response->assertSessionHasErrors(['name', 'start_time', 'end_time']);
    }

    public function test_store_validates_time_format(): void
    {
        $response = $this->post(route('work-shifts.store'), $this->validPayload([
            'start_time' => 'not-a-time',
            'end_time' => '25:99',
        ]));

        $response->assertSessionHasErrors(['start_time', 'end_time']);
    }

    public function test_store_validates_float_and_break_ranges(): void
    {
        $response = $this->post(route('work-shifts.store'), $this->validPayload([
            'float_before' => 200,
            'float_after' => -5,
            'break' => 999,
        ]));

        $response->assertSessionHasErrors(['float_before', 'float_after', 'break']);
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200(): void
    {
        $workShift = $this->makeWorkShift();

        $response = $this->get(route('work-shifts.edit', $workShift));

        $response->assertStatus(200);
        $response->assertSee($workShift->name);
    }

    public function test_update_persists_changes_and_redirects(): void
    {
        $workShift = $this->makeWorkShift(['name' => 'Old Name', 'break' => 20]);

        $response = $this->put(route('work-shifts.update', $workShift), $this->validPayload([
            'name' => 'Updated Shift',
            'break' => 45,
        ]));

        $response->assertRedirect(route('work-shifts.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('work_shifts', [
            'id' => $workShift->id,
            'name' => 'Updated Shift',
            'break' => 45,
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $workShift = $this->makeWorkShift();

        $response = $this->put(route('work-shifts.update', $workShift), []);

        $response->assertSessionHasErrors(['name', 'start_time', 'end_time']);
    }

    public function test_cannot_edit_other_company_work_shift(): void
    {
        $otherCompany = Company::factory()->create();
        $otherShift = WorkShift::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->get(route('work-shifts.edit', $otherShift));

        $response->assertStatus(404);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_work_shift_and_redirects(): void
    {
        $workShift = $this->makeWorkShift(['name' => 'To Delete']);

        $response = $this->delete(route('work-shifts.destroy', $workShift));

        $response->assertRedirect(route('work-shifts.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('work_shifts', ['id' => $workShift->id]);
    }

    public function test_cannot_delete_other_company_work_shift(): void
    {
        $otherCompany = Company::factory()->create();
        $otherShift = WorkShift::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->delete(route('work-shifts.destroy', $otherShift));

        $response->assertStatus(404);
        $this->assertDatabaseHas('work_shifts', ['id' => $otherShift->id]);
    }

    // ----------------------------------------------------------------
    // crosses_midnight and is_active flags
    // ----------------------------------------------------------------

    public function test_store_handles_crosses_midnight_flag(): void
    {
        $this->post(route('work-shifts.store'), $this->validPayload([
            'start_time' => '22:00',
            'end_time' => '06:00',
            'crosses_midnight' => '1',
        ]));

        $this->assertDatabaseHas('work_shifts', [
            'company_id' => $this->companyId,
            'crosses_midnight' => true,
        ]);
    }

    public function test_store_defaults_is_active_to_true_when_omitted(): void
    {
        $payload = $this->validPayload();
        unset($payload['is_active']);

        $this->post(route('work-shifts.store'), $payload);

        $this->assertDatabaseHas('work_shifts', [
            'company_id' => $this->companyId,
            'name' => 'Morning Shift',
            'is_active' => true,
        ]);
    }

    public function test_update_can_deactivate_work_shift(): void
    {
        $workShift = $this->makeWorkShift(['is_active' => true]);

        $this->put(route('work-shifts.update', $workShift), $this->validPayload(['is_active' => '0']));

        $this->assertDatabaseHas('work_shifts', [
            'id' => $workShift->id,
            'is_active' => false,
        ]);
    }
}
