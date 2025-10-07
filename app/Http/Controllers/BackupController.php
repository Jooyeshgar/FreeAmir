<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

class BackupController extends Controller
{
    public function run(): RedirectResponse
    {
        Artisan::call('backup:run --only-db');

        return back()->with('success', __('Backup completed successfully.'));
    }
}
