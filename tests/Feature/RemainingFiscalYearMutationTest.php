<?php

namespace Tests\Feature;

use App\Enums\ChequeType;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureFiscalYearOpen;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\Chequebook;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ChequeService;
use App\Services\CommentService;
use App\Services\CustomerImportService;
use App\Services\FiscalYearMutationGuard;
use App\Services\MonthlyBudgetService;
use App\Services\ProductImportService;
use App\Services\ProductService;
use App\Services\ServiceImportService;
use App\Services\SubjectService;
use App\Services\WarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemainingFiscalYearMutationTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $year;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->year = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $this->year->id]);
        $this->user = User::factory()->create();
        $this->user->fiscalYears()->attach($this->year);
        $this->actingAs($this->user)->withCookies(['active-fiscal-year-id' => (string) $this->year->id]);
        $this->withoutMiddleware(CheckPermission::class);
    }

    public static function roles(): array
    {
        return ['ordinary user' => [false], 'super admin' => [true]];
    }

    #[DataProvider('roles')]
    public function test_remaining_resources_reject_direct_edit_and_mutations_without_changes(bool $superAdmin): void
    {
        if ($superAdmin) {
            $this->user->assignRole(Role::findOrCreate('Super-Admin'));
        }
        $resources = $this->resources();
        $this->year->update(['closed_at' => now()]);
        foreach ($resources as $resource => $model) {
            $before = DB::table($model->getTable())->where('id', $model->id)->first();
            foreach ([['GET', 'create', []], ['GET', 'edit', [$model]], ['POST', 'store', []], ['PUT', 'update', [$model]], ['DELETE', 'destroy', [$model]]] as [$method, $action, $parameters]) {
                $this->json($method, route("$resource.$action", $parameters), ['name' => 'Changed'])
                    ->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
            }
            $this->assertEquals($before, DB::table($model->getTable())->where('id', $model->id)->first(), $resource);
        }
        foreach (['products.import', 'services.import', 'customers.import', 'warehouses.transfer'] as $route) {
            $this->getJson(route($route))->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
        }
        foreach (['products.import.store', 'services.import.store', 'customers.import.store', 'warehouses.transfer.store', 'subjects.transfer', 'budgets.rollover'] as $route) {
            $this->postJson(route($route))->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
        }
        $this->putJson(route('budgets.store'))->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
        $this->getJson(route('configs.edit', 'bank'))->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
        $this->assertDatabaseMissing('configs', ['key' => 'bank']);
        $this->get(route('banks.edit', $resources['banks']))->assertSessionHasErrors('fiscal_year');
    }

    public function test_nested_comments_check_the_actual_comment_and_posted_customer_owners(): void
    {
        $closedCustomer = Customer::factory()->create();
        $comment = Comment::create(['customer_id' => $closedCustomer->id, 'user_id' => $this->user->id, 'content' => 'History']);
        $this->year->update(['closed_at' => now()]);
        $openYear = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $openYear->id]);
        $this->user->fiscalYears()->attach($openYear);
        $openCustomer = Customer::factory()->create();
        $this->withCookies(['active-fiscal-year-id' => (string) $openYear->id]);
        $this->assertNull($comment->fresh()->customer);
        $this->assertSame($this->year->id, app(FiscalYearMutationGuard::class)->resolveFiscalYear($comment)->id);
        foreach ([['GET', 'edit'], ['PUT', 'update'], ['DELETE', 'destroy']] as [$method, $action]) {
            $this->json($method, route("comments.$action", [$openCustomer, $comment]), ['content' => 'Changed', 'customer_id' => $openCustomer->id, 'user_id' => $this->user->id])
                ->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
        }
        $this->postJson(route('comments.store', $openCustomer), ['content' => 'Changed', 'customer_id' => $closedCustomer->id, 'user_id' => $this->user->id])
            ->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
        $this->assertSame('History', $comment->fresh()->content);
        $this->assertDatabaseCount('comments', 1);
    }

    public function test_services_protect_closed_owners_even_with_an_open_active_year(): void
    {
        $resources = $this->resources();
        $customer = $resources['customers'];
        $comment = Comment::create(['customer_id' => $customer->id, 'user_id' => $this->user->id, 'content' => 'History']);
        $subject = $resources['subjects'];
        $warehouse = $resources['warehouses'];
        $this->year->update(['closed_at' => now()]);
        $openYear = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $openYear->id]);
        $openSubject = Subject::factory()->create();
        $mutations = [];
        foreach (['customers' => 'Customer', 'customer-groups' => 'CustomerGroup', 'products' => 'Product', 'product-groups' => 'ProductGroup', 'services' => 'Service', 'service-groups' => 'ServiceGroup', 'warehouses' => 'Warehouse'] as $resource => $service) {
            $service = app('App\\Services\\'.$service.'Service');
            $model = $resources[$resource];
            $mutations[] = fn () => $service->create(['name' => 'Blocked', 'fiscal_year_id' => $this->year->id]);
            $mutations[] = fn () => $service->update($model, ['name' => 'Changed']);
            if (method_exists($service, 'delete')) {
                $mutations[] = fn () => $service->delete($model);
            }
        }
        foreach ([CustomerImportService::class, ProductImportService::class, ServiceImportService::class] as $service) {
            $mutations[] = fn () => app($service)->import('/nonexistent.csv', $this->year->id);
        }
        $mutations = [...$mutations,
            fn () => app(CommentService::class)->update($comment, ['content' => 'Changed']),
            fn () => app(CommentService::class)->delete($comment),
            fn () => app(ChequeService::class)->update($resources['cheques'], $this->user, []),
            fn () => app(ChequeService::class)->delete($resources['cheques']),
            fn () => app(ChequeService::class)->transition($resources['cheques'], $this->user, 'deposit'),
            fn () => app(WarehouseService::class)->transfer($resources['products'], $warehouse, $warehouse, 1),
            fn () => ProductService::recalculateQuantity($resources['products']),
            fn () => app(SubjectService::class)->editSubject($subject, ['name' => 'Changed']),
            fn () => app(SubjectService::class)->transferSubject($subject, $openSubject),
            fn () => app(SubjectService::class)->transferSubject($openSubject, $subject),
        ];
        foreach ($mutations as $mutation) {
            try {
                $mutation();
                $this->fail('Mutation unexpectedly accepted a closed fiscal year.');
            } catch (ValidationException $exception) {
                $this->assertSame(__('This fiscal year is already closed.'), $exception->errors()['fiscal_year'][0]);
            }
        }
        $this->assertSame('History', $comment->fresh()->content);
        config(['active-fiscal-year-id' => $this->year->id]);
        foreach ([fn () => app(MonthlyBudgetService::class)->saveForecast(1, []), fn () => app(MonthlyBudgetService::class)->saveForecastForMonths([1, 2], []), fn () => app(MonthlyBudgetService::class)->rollover(2)] as $mutation) {
            try {
                $mutation();
                $this->fail('Budget unexpectedly accepted a closed fiscal year.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('fiscal_year', $exception->errors());
            }
        }
    }

    public function test_remaining_lists_are_readable_and_mutation_links_are_inert_after_closure(): void
    {
        $resources = $this->resources();
        Gate::before(fn () => true);
        $this->year->update(['closed_at' => now()]);
        foreach (array_keys($resources) as $resource) {
            $response = $this->get(route("$resource.index"))->assertOk();
            $response->assertSee(__('This fiscal year is already closed.'));
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $editUrl = route("$resource.edit", $resources[$resource]);
            $this->assertSame(0, $xpath->query('//a[@href="'.$editUrl.'"]')->length, $resource);
            $this->assertGreaterThan(0, $xpath->query('//a[@aria-disabled="true" and not(@href)]')->length, $resource);
        }
        $this->year->update(['closed_at' => null]);
        $this->get(route('banks.index'))->assertOk()->assertSee(route('banks.edit', $resources['banks']));
        $this->put(route('banks.update', $resources['banks']), ['name' => 'Open Bank'])->assertSessionHasNoErrors();
        $this->assertSame('Open Bank', $resources['banks']->fresh()->name);
    }

    public function test_audited_web_api_and_bulk_routes_enforce_the_closed_year_boundary(): void
    {
        $this->year->update(['closed_at' => now()]);
        $inventory = json_decode(file_get_contents(base_path('tests/Fixtures/closed-fiscal-year-routes.json')), true, flags: JSON_THROW_ON_ERROR);
        $audited = [];
        foreach ($inventory as [$method, $name, $uri, $rule]) {
            $route = clone Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('fiscal-year-open', $route->middleware(), $name);
            $request = Request::create('/'.ltrim(preg_replace('/\{[^}]+\}/', (string) $this->year->id, $uri), '/'), $method);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);
            $blocked = false;
            try {
                app(EnsureFiscalYearOpen::class)->handle($request, fn () => response('allowed'));
            } catch (ValidationException $exception) {
                $blocked = true;
                $this->assertArrayHasKey('fiscal_year', $exception->errors(), $name);
            }
            $this->assertSame($rule === 'blocked', $blocked, $name);
            $audited[] = $name;
        }
        $actual = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('fiscal-year-open', $route->middleware(), true))
            ->map(fn ($route) => $route->getName())->values()->all();
        sort($actual);
        sort($audited);
        $this->assertSame($actual, $audited, 'Update the reviewed inventory when fiscal-year routes change.');
    }

    private function resources(): array
    {
        $bank = Bank::create(['name' => 'Bank', 'fiscal_year_id' => $this->year->id]);
        $subject = Subject::factory()->create(['parent_id' => null]);
        $account = BankAccount::create(['bank_id' => $bank->id, 'name' => 'Account', 'number' => '123', 'type' => 1, 'subject_id' => $subject->id]);

        return [
            'customers' => Customer::factory()->create(),
            'customer-groups' => CustomerGroup::factory()->create(),
            'products' => Product::factory()->withGroup()->create(),
            'product-groups' => ProductGroup::factory()->create(),
            'services' => Service::factory()->withGroup()->create(),
            'service-groups' => ServiceGroup::factory()->create(),
            'subjects' => $subject,
            'banks' => $bank,
            'bank-accounts' => $account,
            'chequebooks' => Chequebook::create(['bank_account_id' => $account->id, 'first_leaf' => 1, 'last_leaf' => 10, 'next_leaf' => 1]),
            'cheques' => Cheque::create(['customer_id' => Customer::factory()->create()->id, 'title' => 'History cheque', 'amount' => 100, 'write_date' => now(), 'due_date' => now(), 'sayad_number' => '1234567890123456', 'direction' => ChequeType::RECEIVABLE, 'purpose' => ChequeType::GUARANTEE, 'status' => ChequeType::GUARANTEE_RECEIVED]),
            'warehouses' => Warehouse::create(['name' => 'Warehouse']),
        ];
    }
}
