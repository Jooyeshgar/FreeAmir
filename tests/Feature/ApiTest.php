<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Subject;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);

        $permissions = [
            'api.access',
            'attendance.attendance-logs.index',
            'attendance.attendance-logs.store',
            'hr.employees.index',
            'hr.employees.store',
            'documents.store',
            'documents.show',
            'documents.files.store',
        ];

        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        $this->token = $this->user->createToken('device', $permissions)->plainTextToken;
    }

    protected function apiHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ];
    }

    public function test_api_requires_api_access_permission_for_token_requests(): void
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'hr.employees.index']));
        $token = $user->createToken('limited', ['hr.employees.index'])->plainTextToken;

        $response = $this->getJson('/api/employees', [
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ]);

        $response->assertForbidden();
    }

    public function test_token_must_have_route_permission_even_when_user_has_it(): void
    {
        $token = $this->user->createToken('read-only', ['hr.employees.index'])->plainTextToken;

        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);

        $response = $this->postJson('/api/employees', [
            'code' => 'EMP-API-1',
            'first_name' => 'Ali',
            'last_name' => 'Api',
            'nationality' => 'iranian',
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ]);

        $response->assertForbidden();
    }

    public function test_employee_api_lists_ids_and_creates_employee(): void
    {
        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ]);

        $this->getJson('/api/employees', $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', $employee->id);

        $this->postJson('/api/employees', [
            'code' => 'EMP-API-2',
            'first_name' => 'Sara',
            'last_name' => 'Device',
            'nationality' => 'iranian',
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ], $this->apiHeaders())
            ->assertCreated()
            ->assertJsonPath('data.code', 'EMP-API-2');
    }

    public function test_attendance_api_accepts_batch_and_filters_by_period(): void
    {
        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ]);

        $this->postJson('/api/attendance/logs', [
            'logs' => [
                [
                    'employee_id' => $employee->id,
                    'log_date' => '2026-05-01',
                    'entry_time' => '08:00',
                    'exit_time' => '17:00',
                ],
                [
                    'employee_id' => $employee->id,
                    'log_date' => '2026-05-02',
                    'entry_time' => '08:10',
                    'exit_time' => '17:05',
                ],
            ],
        ], $this->apiHeaders())
            ->assertCreated()
            ->assertJsonPath('meta.count', 2);

        AttendanceLog::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'log_date' => '2026-04-30',
        ]);

        $this->getJson('/api/attendance/logs?employee_id='.$employee->id.'&date_from=2026-05-01&date_to=2026-05-31', $this->apiHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_document_api_creates_document_and_attaches_file(): void
    {
        Storage::fake('public');

        $debitSubject = Subject::factory()->create(['company_id' => $this->company->id]);
        $creditSubject = Subject::factory()->create(['company_id' => $this->company->id]);

        $response = $this->postJson('/api/documents', [
            'title' => 'API document',
            'date' => '2026-05-16',
            'transactions' => [
                ['subject_id' => $debitSubject->id, 'value' => '-1000.00', 'desc' => 'debit'],
                ['subject_id' => $creditSubject->id, 'value' => '1000.00', 'desc' => 'credit'],
            ],
        ], $this->apiHeaders())
            ->assertCreated();

        $documentId = $response->json('data.id');

        $this->getJson('/api/documents/'.$documentId, $this->apiHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'data.transactions');

        $fileResponse = $this->post('/api/documents/'.$documentId.'/files', [
            'title' => 'receipt',
            'file' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ], $this->apiHeaders())
            ->assertCreated()
            ->assertJsonPath('data.title', 'receipt')
            ->assertJsonPath('data.document_id', $documentId)
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.name', 'receipt.pdf');

        $this->assertDatabaseHas('document_files', [
            'id' => $fileResponse->json('data.id'),
            'document_id' => $documentId,
            'title' => 'receipt',
            'name' => 'receipt.pdf',
        ]);
        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'company_id' => $this->company->id,
        ]);
        $this->assertSame(0.0, (float) Document::find($documentId)->transactions()->sum('value'));
    }
}
