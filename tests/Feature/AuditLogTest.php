<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_super_admin_can_browse_the_system_audit_trail(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);

        AuditLogger::record('test.audit.created', null, ['reference' => 'AUDIT-001']);
        $filterDate = toEnglish(formatDate(now(), 'Y/m/d'));

        $this->get(route('admin.audit-logs.index', [
            'from' => $filterDate,
            'to' => $filterDate,
        ]))->assertOk()->assertSee(__('Audit Trail'))->assertSee('test.audit.created');

        $auditLog = AuditLog::query()->firstOrFail();

        $this->get(route('admin.audit-logs.show', $auditLog))->assertOk()->assertSee('AUDIT-001');
    }

    public function test_audit_routes_use_the_admin_access_ability(): void
    {
        $regularUser = User::factory()->create();

        $this->actingAs($regularUser)->get(route('admin.audit-logs.index'))->assertForbidden();

        $superAdmin = $this->superAdmin();

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($superAdmin->can('admin.access'));
    }

    public function test_audit_logger_redacts_sensitive_request_data(): void
    {
        $this->actingAs($this->superAdmin());

        AuditLogger::record('test.sensitive', null, [
            'reference' => 'visible',
            'password' => 'never-store-this',
            'token' => 'never-store-this-either',
            'nested' => [
                'api_token' => 'also-secret',
                'safe' => 'retained',
            ],
        ]);

        $data = AuditLog::query()->where('action', 'test.sensitive')->firstOrFail()->request_data;

        $this->assertSame('visible', $data['reference']);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('token', $data);
        $this->assertArrayNotHasKey('api_token', $data['nested']);
        $this->assertSame('retained', $data['nested']['safe']);
    }

    public function test_persisted_audit_events_are_immutable(): void
    {
        $this->actingAs($this->superAdmin());
        AuditLogger::record('test.immutable');

        $auditLog = AuditLog::query()->firstOrFail();

        $this->expectException(LogicException::class);

        $auditLog->update(['action' => 'test.tampered']);
    }

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'Super-Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
