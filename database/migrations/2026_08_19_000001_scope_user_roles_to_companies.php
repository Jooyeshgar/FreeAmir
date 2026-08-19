<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $roleAssignmentsHadCompany = Schema::hasColumn('model_has_roles', 'company_id');
        $permissionAssignmentsHadCompany = Schema::hasColumn('model_has_permissions', 'company_id');

        if (! $roleAssignmentsHadCompany) {
            Schema::table('model_has_roles', function (Blueprint $table) {
                $table->dropPrimary('model_has_roles_role_model_type_primary');
                $table->unsignedBigInteger('company_id')->default(0)->after('role_id')->index();
                $table->primary(
                    ['company_id', 'role_id', 'model_id', 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );
            });
        }

        if (! $permissionAssignmentsHadCompany) {
            Schema::table('model_has_permissions', function (Blueprint $table) {
                $table->dropPrimary('model_has_permissions_permission_model_type_primary');
                $table->unsignedBigInteger('company_id')->default(0)->after('permission_id')->index();
                $table->primary(
                    ['company_id', 'permission_id', 'model_id', 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
            });
        }

        if (! $roleAssignmentsHadCompany) {
            $this->expandAssignmentsAcrossCompanies('model_has_roles', 'role_id');
        }

        if (! $permissionAssignmentsHadCompany) {
            $this->expandAssignmentsAcrossCompanies('model_has_permissions', 'permission_id');
        }
    }

    private function expandAssignmentsAcrossCompanies(string $table, string $assignmentKey): void
    {
        $assignments = DB::table($table)->where('company_id', 0)
            ->when($table === 'model_has_roles', fn ($query) => $query->whereNotIn(
                $assignmentKey,
                DB::table('roles')->where('name', 'Super-Admin')->select('id')
            ))->when($table === 'model_has_permissions', fn ($query) => $query->whereNotIn(
                $assignmentKey,
                DB::table('permissions')->where('name', 'access-super-admin-panel')->select('id')
            ))->get();

        foreach ($assignments as $assignment) {
            $companyIds = DB::table('company_user')->where('user_id', $assignment->model_id)->pluck('company_id');

            foreach ($companyIds as $companyId) {
                DB::table($table)->insertOrIgnore([
                    'company_id' => $companyId,
                    $assignmentKey => $assignment->{$assignmentKey},
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                ]);
            }

            if ($companyIds->isNotEmpty()) {
                DB::table($table)
                    ->where('company_id', 0)
                    ->where($assignmentKey, $assignment->{$assignmentKey})
                    ->where('model_type', $assignment->model_type)
                    ->where('model_id', $assignment->model_id)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // Company-scoped assignments cannot be collapsed without losing fiscal-year history.
    }
};
