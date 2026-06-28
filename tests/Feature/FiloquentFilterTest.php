<?php

namespace Tests\Feature;

use App\Filters\AttendanceLogFilter;
use App\Filters\EmployeeFilter;
use App\Filters\PayrollFilter;
use App\Filters\ProductFilter;
use App\Filters\TransactionFilter;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Tests\TestCase;

class FiloquentFilterTest extends TestCase
{
    public function test_employee_filter_uses_searchable_fields_for_search(): void
    {
        $query = Employee::withoutGlobalScopes()
            ->filter(new EmployeeFilter($this->request(['search' => 'ali'])));

        $sql = $query->toSql();

        $this->assertStringContainsString('first_name', $sql);
        $this->assertStringContainsString('last_name', $sql);
        $this->assertStringContainsString('code', $sql);
        $this->assertStringContainsString('national_code', $sql);
        $this->assertSame(['%ali%', '%ali%', '%ali%', '%ali%'], $query->getBindings());
    }

    public function test_falsey_boolean_filter_values_are_preserved(): void
    {
        $query = Employee::withoutGlobalScopes()
            ->filter(new EmployeeFilter($this->request(['is_active' => '0'])));

        $this->assertStringContainsString('is_active', $query->toSql());
        $this->assertSame([false], $query->getBindings());
    }

    public function test_attendance_log_filter_preserves_manual_false_value(): void
    {
        $query = AttendanceLog::withoutGlobalScopes()
            ->filter(new AttendanceLogFilter($this->request([
                'employee_id' => '12',
                'is_manual' => '0',
            ])));

        $this->assertStringContainsString('employee_id', $query->toSql());
        $this->assertStringContainsString('is_manual', $query->toSql());
        $this->assertSame([12, false], $query->getBindings());
    }

    public function test_payroll_filter_uses_shared_employee_month_and_status_filters(): void
    {
        $query = Payroll::withoutGlobalScopes()
            ->filter(new PayrollFilter($this->request([
                'employee_id' => '7',
                'month' => '3',
                'status' => 'paid',
            ])));

        $sql = $query->toSql();

        $this->assertStringContainsString('employee_id', $sql);
        $this->assertStringContainsString('month', $sql);
        $this->assertStringContainsString('status', $sql);
        $this->assertSame([7, 3, 'paid'], $query->getBindings());
    }

    public function test_product_filter_uses_shared_name_and_group_name_filters(): void
    {
        $query = Product::withoutGlobalScopes()
            ->filter(new ProductFilter($this->request([
                'name' => 'laptop',
                'group_name' => 'hardware',
            ])));

        $sql = $query->toSql();

        $this->assertStringContainsString('name', $sql);
        $this->assertStringContainsString('exists', strtolower($sql));
        $this->assertContains('%laptop%', $query->getBindings());
        $this->assertContains('%hardware%', $query->getBindings());
    }

    public function test_transaction_filter_uses_searchable_fields_and_relations(): void
    {
        $query = Transaction::query()
            ->filter(new TransactionFilter($this->request(['search' => 'cash'])));

        $sql = strtolower($query->toSql());

        $this->assertStringContainsString('desc', $sql);
        $this->assertStringContainsString('subjects', $sql);
        $this->assertStringContainsString('documents', $sql);
        $this->assertSame(5, collect($query->getBindings())->filter(fn ($binding) => $binding === '%cash%')->count());
    }

    private function request(array $query): Request
    {
        return Request::create('/', 'GET', $query);
    }
}
