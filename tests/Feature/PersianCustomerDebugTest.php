<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PersianCustomerDebugTest extends TestCase
{
    use RefreshDatabase;

    public static function names(): array
    {
        return [['مریم احمدی'], ['رضا محمدی'], ['علی‌رضا نیک‌نام']];
    }

    #[DataProvider('names')]
    public function test_persian_customer_can_be_created_found_renamed_and_deleted(string $name): void
    {
        Http::preventStrayRequests();
        $year = FiscalYear::factory()->create(['year' => 1405]);
        config(['active-fiscal-year-id' => $year->id]);
        $user = User::factory()->create(['name' => 'نرگس امینی']);
        $year->users()->attach($user);
        $user->givePermissionTo(Permission::findOrCreate('customers.*'));
        $this->actingAs($user)->withCookies(['active-fiscal-year-id' => (string) $year->id]);
        $group = CustomerGroup::factory()->withSubject()->create(['name' => 'اشخاص فرضی', 'fiscal_year_id' => $year->id]);
        $payload = ['name' => $name, 'type' => 'individual', 'group_id' => $group->id, 'acc_bank_1' => 'بانک تجارت'];
        $this->post(route('customers.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $customer = Customer::where('name', $name)->sole();
        $this->assertSame($name, $customer->subject->name);
        $this->get(route('customers.index', ['name' => $name]))->assertOk()->assertSee($name);
        $this->get(route('customers.show', $customer))->assertOk()->assertSee($name);
        $this->put(route('customers.update', $customer), array_replace($payload, ['name' => 'سارا کاظمی']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('سارا کاظمی', $customer->fresh()->subject->name);
        $this->delete(route('customers.destroy', $customer))->assertRedirect();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }
}
