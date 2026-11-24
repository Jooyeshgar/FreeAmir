<?php

namespace Tests\Feature;

use App\Enums\PersonnelRequestType;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\PersonnelRequest;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Database\Seeders\PersonnelRequestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelRequestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_requests_are_single_day_and_match_shift_length(): void
    {
        $fiscalYear = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $fiscalYear->id]);

        $workSite = WorkSite::factory()->create(['fiscal_year_id' => $fiscalYear->id]);
        $shift = WorkShift::factory()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        Employee::factory()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $shift->id,
        ]);

        $this->seed(PersonnelRequestSeeder::class);

        $daily = PersonnelRequest::withoutGlobalScopes()->whereIn('request_type', [
            PersonnelRequestType::LEAVE_DAILY->value,
            PersonnelRequestType::SICK_LEAVE->value,
            PersonnelRequestType::LEAVE_WITHOUT_PAY->value,
            PersonnelRequestType::MISSION_DAILY->value,
        ])->get();

        $this->assertCount(4, $daily);

        foreach ($daily as $request) {
            $this->assertTrue($request->start_date->isSameDay($request->end_date));
            $this->assertSame('09:00', $request->start_date->format('H:i'));
            $this->assertSame('17:00', $request->end_date->format('H:i'));
            $this->assertSame(480, $request->durationMinutes());
        }
    }
}
