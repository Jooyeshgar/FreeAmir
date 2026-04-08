<?php

namespace App\Http\Controllers;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Storage;
use ZipArchive;

class BackupController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:backups.create', ['only' => ['create', 'download', 'export', 'import']]);
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
        $zipPath = storage_path('app/exports/'.$zipFileName);

        if (! file_exists(storage_path('app/exports'))) {
            mkdir(storage_path('app/exports'), 0755, true);
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
            'tables_to_backup' => 'required|array',
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

    public function upload()
    {
        return view('backups.upload');
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:json',
            'fiscal_year' => 'required|integer|min:1', // positive integer
            'company_name' => 'required|max:50|string|regex:/^[\w\d\s]*$/u',
        ]);

        $path = $validated['file']->store('import-tmp');

        $params = [
            'file' => $path, // related temp json file
            'fiscal_year' => $validated['fiscal_year'],
            '--name' => $validated['company_name'],
            '--force' => true,
        ];

        Artisan::call('fiscal-year:import', $params);
        Storage::delete($path);

        return redirect()->route('home')->with('success', __('Company backup file imported successfully.'));
    }
}
