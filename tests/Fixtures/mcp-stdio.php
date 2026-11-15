<?php

use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
Artisan::call('migrate', ['--force' => true]);
$user = User::factory()->create();
FiscalYear::factory()->create(['year' => 1405]);
foreach (['api.access', 'invoices.index'] as $permission) {
    $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
}
$token = $user->createToken('stdio test', ['invoices.index'])->plainTextToken;
exit(Artisan::call('mcp:serve', ['--token' => ($argv[1] ?? '') === 'invalid' ? 'invalid' : $token]));
