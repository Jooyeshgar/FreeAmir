<?php

namespace App\Http\Controllers;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BackupController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:backups.create', ['only' => ['create', 'download', 'export']]);
    }

    public function create()
    {
        $previousYears = Company::all();

        return view('backups.create', compact('previousYears'));
    }

    public function download(string $path)
    {
        $fullPath = storage_path("app/$path");

        return response()->download($fullPath, 'backup_'.now()->format('Y-m-d_H-i-s').'.json');
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'source_id' => 'required|exists:companies,id',
            'tables_to_backup' => 'array',
            'tables_to_backup.*' => 'string|in:'.implode(',', array_map(fn ($case) => $case->value, FiscalYearSection::cases())),
        ]);

        $path = 'exports/fiscal_year_'.$validated['source_id'].'_'.now()->format('YmdHis').'_user_id_'.Auth::id().'.json';

        $params = [
            'source_id' => $validated['source_id'],
            '--output' => $path,
            '--sections' => implode(',', $validated['tables_to_backup']),
        ];

        Artisan::call('fiscal-year:export', $params);

        return view('backups.download', [
            'downloadUrl' => route('backups.download', ['path' => $path]),
            'redirectUrl' => route('home'),
        ]);
    }
}
