<?php

namespace App\Http\Controllers;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use ZipArchive;

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

        if (! file_exists($fullPath)) {
            abort(404, __('File not found'));
        }

        $zipFileName = 'backup_'.now()->format('Y-m-d_H-i-s').'.zip';
        $zipPath = storage_path('app/temp/'.$zipFileName);

        if (! file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $zip->addFile($fullPath, basename($path));
            $zip->close();

            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
        }

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
