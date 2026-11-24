<?php

use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
Artisan::call('migrate', ['--force' => true]);
$user = User::factory()->create();
FiscalYear::factory()->create(['year' => 1405]);
$permissions = ['api.access', 'invoices.index'];
$abilities = ['invoices.index'];
if (($argv[1] ?? '') === 'writer') {
    config(['active-fiscal-year-id' => 1]);
    $seeder = new class
    {
        use SeederHelper;
    };
    $seeder->importSubjects(1);
    $seeder->importConfigs(1);
    CustomerGroup::factory()->withSubject()->create(['fiscal_year_id' => 1]);
    ProductGroup::factory()->withSubjects()->create(['fiscal_year_id' => 1]);
    Warehouse::create(['name' => 'Main', 'code' => 'MAIN']);
    $abilities = ['customers.store', 'products.store', 'invoices.store'];
    $permissions = ['api.access', ...$abilities];
    config(['active-fiscal-year-id' => null]);
}
foreach ($permissions as $permission) {
    $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
}
$token = $user->createToken('stdio test', $abilities)->plainTextToken;
exit(Artisan::call('mcp:serve', ['--token' => ($argv[1] ?? '') === 'invalid' ? 'invalid' : $token]));
