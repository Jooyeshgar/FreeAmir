<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class BackupController extends Controller
{
    public function manualBackup(Request $request)
    {
        $company_id = session('active-company-id');
        Artisan::call('backup:company', [
            'company_id' => $company_id,
            '--public-only' => false,
        ]);

        return back()->with('success', 'Backup completed successfully.');
    }
}
