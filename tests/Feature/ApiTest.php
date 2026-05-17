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
use RuntimeException;
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
            'companies.index',
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
        ];
    }

    protected function companyApiUrl(string $path): string
    {
        return '/api/companies/'.$this->company->id.$path;
    }

    public function test_api_requires_api_access_permission_for_token_requests(): void
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'hr.employees.index']));
        $token = $user->createToken('limited', ['hr.employees.index'])->plainTextToken;

        $response = $this->getJson('/api/companies/'.$this->company->id.'/employees', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertForbidden();
    }

    public function test_token_must_have_route_permission_even_when_user_has_it(): void
    {
        $token = $this->user->createToken('read-only', ['hr.employees.index'])->plainTextToken;

        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);

        $response = $this->postJson($this->companyApiUrl('/employees'), [
            'code' => 'EMP-API-1',
            'first_name' => 'Ali',
            'last_name' => 'Api',
            'nationality' => 'iranian',
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ], [
            'Authorization' => 'Bearer '.$token,
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

        $this->getJson($this->companyApiUrl('/employees'), $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', $employee->id);

        $this->postJson($this->companyApiUrl('/employees'), [
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

        $this->postJson($this->companyApiUrl('/attendance/logs'), [
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

        $this->getJson($this->companyApiUrl('/attendance/logs').'?employee_id='.$employee->id.'&date_from=2026-05-01&date_to=2026-05-31', $this->apiHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_company_scoped_api_requires_company_path_parameter(): void
    {
        $this->getJson('/api/employees', $this->apiHeaders())
            ->assertNotFound();
    }

    public function test_company_scoped_api_rejects_invalid_company_path_parameter(): void
    {
        $this->getJson('/api/companies/0/employees', $this->apiHeaders())
            ->assertStatus(422)
            ->assertJsonPath('message', __('The company path parameter must be a valid company ID.'));
    }

    public function test_company_scoped_api_rejects_unattached_company_id(): void
    {
        $otherCompany = Company::factory()->create();

        $this->getJson('/api/companies/'.$otherCompany->id.'/employees', $this->apiHeaders())
            ->assertForbidden()
            ->assertJsonPath('message', __('You do not have access to this company.'));
    }

    public function test_api_lists_available_companies(): void
    {
        $secondCompany = Company::factory()->create(['name' => 'Second API Company']);
        $unattachedCompany = Company::factory()->create(['name' => 'Hidden API Company']);
        $this->company->update(['name' => 'First API Company']);
        $this->user->companies()->attach($secondCompany);

        $this->getJson('/api/companies', $this->apiHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'First API Company')
            ->assertJsonPath('data.1.name', 'Second API Company')
            ->assertJsonMissing(['id' => $unattachedCompany->id]);
    }

    public function test_api_companies_requires_user_permission_and_token_ability(): void
    {
        $tokenWithoutAbility = $this->user->createToken('without-companies', ['api.access'])->plainTextToken;

        $this->getJson('/api/companies', [
            'Authorization' => 'Bearer '.$tokenWithoutAbility,
        ])->assertForbidden();

        $userWithoutPermission = User::factory()->create();
        $this->company->users()->attach($userWithoutPermission);
        $userWithoutPermission->givePermissionTo(Permission::firstOrCreate(['name' => 'api.access']));
        $token = $userWithoutPermission->createToken('companies', ['companies.index'])->plainTextToken;

        $this->getJson('/api/companies', [
            'Authorization' => 'Bearer '.$token,
        ])->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_attendance_batch_rolls_back_when_one_insert_fails(): void
    {
        $workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ]);

        $creates = 0;
        AttendanceLog::creating(function () use (&$creates): void {
            $creates++;

            if ($creates === 2) {
                throw new RuntimeException('Simulated attendance insert failure.');
            }
        });

        try {
            $this->postJson($this->companyApiUrl('/attendance/logs'), [
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
            ], $this->apiHeaders())->assertStatus(500);
        } finally {
            AttendanceLog::flushEventListeners();
            AttendanceLog::clearBootedModels();
        }

        $this->assertDatabaseMissing('attendance_logs', [
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'log_date' => '2026-05-01',
        ]);
    }

    public function test_document_api_creates_document_and_attaches_file(): void
    {
        Storage::fake('public');

        $debitSubject = Subject::factory()->create(['company_id' => $this->company->id]);
        $creditSubject = Subject::factory()->create(['company_id' => $this->company->id]);

        $response = $this->postJson($this->companyApiUrl('/documents'), [
            'title' => 'API document',
            'date' => '2026-05-16',
            'transactions' => [
                ['subject_id' => $debitSubject->id, 'value' => '-1000.00', 'desc' => 'debit'],
                ['subject_id' => $creditSubject->id, 'value' => '1000.00', 'desc' => 'credit'],
            ],
        ], $this->apiHeaders())
            ->assertCreated();

        $documentId = $response->json('data.id');
        $this->assertNotNull($documentId);
        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'company_id' => $this->company->id,
        ]);

        $this->getJson($this->companyApiUrl('/documents/'.$documentId), $this->apiHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'data.transactions');

        $fileResponse = $this->post($this->companyApiUrl('/documents/'.$documentId.'/files'), [
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
