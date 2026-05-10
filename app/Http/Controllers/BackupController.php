<?php

namespace App\Http\Controllers;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use App\Services\FiscalYearService;
use Illuminate\Http\Request;
use ZipArchive;

class BackupController extends Controller
{
    public function create()
    {
        $previousYears = Company::all();

        return view('backups.create', compact('previousYears'));
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'source_id' => 'required|exists:companies,id',
            'tables_to_backup' => 'required|array',
            'tables_to_backup.*' => 'string|in:'.implode(',', array_map(fn ($case) => $case->value, FiscalYearSection::cases())),
        ]);

        $exportData = FiscalYearService::exportData($validated['source_id'], $validated['tables_to_backup']);
        $fileBaseName = 'company_backup_'.$validated['source_id'].'_'.now()->format('Ymd_His');

        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (! $jsonContent) {
            return redirect()->back()->with('error', __('Failed to encode backup data: :message', ['message' => json_last_error_msg()]));
        }

        $zipFilePath = tempnam(sys_get_temp_dir(), 'backup_zip_');
        if (! $zipFilePath || ! file_exists($zipFilePath)) {
            return redirect()->back()->with('error', __('Failed to create temporary file for ZIP.'));
        }

        $zip = new ZipArchive;
        if (! $zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            @unlink($zipFilePath);

            return redirect()->back()->with('error', __('Failed to open ZIP archive.'));
        }

        if (! $zip->addFromString($fileBaseName.'.json', $jsonContent)) {
            $zip->close();
            @unlink($zipFilePath);

            return redirect()->back()->with('error', __('Failed to add JSON to ZIP.'));
        }

        $zip->close();

        return response()->download($zipFilePath, $fileBaseName.'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    public function upload()
    {
        return view('backups.upload');
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:zip|max:102400',
            'fiscal_year' => 'required|integer|min:1', // positive integer
            'company_name' => 'required|max:50|string|regex:/^[\w\d\s]*$/u',
        ]);

        $uploadedFile = $request->file('file');
        $zipPath = $uploadedFile->getPathname();
        $zip = new ZipArchive;

        if (! $zip->open($zipPath)) {
            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }

            return redirect()->back()->with('error', __('Uploaded backup file is invalid.'));
        }

        $jsonContent = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            if (! is_array($entry) || ! isset($entry['name'])) {
                continue;
            }

            $entryName = $entry['name'];
            if (str_ends_with($entryName, '/')) {
                continue;
            }

            if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) === 'json') {
                $stream = $zip->getStream($entryName);

                if (! $stream) {
                    if ($zipPath && file_exists($zipPath)) {
                        @unlink($zipPath);
                    }

                    return redirect()->back()->with('error', __('Failed to read JSON file from ZIP archive.'));
                }

                $jsonContent = stream_get_contents($stream);
                fclose($stream);
                break;
            }
        }

        if (! $jsonContent) {
            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }

            return redirect()->back()->with('error', __('No JSON file found in ZIP archive.'));
        }

        $importData = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }

            return redirect()->back()->with('error', __('Invalid JSON: :msg', ['msg' => json_last_error_msg()]));
        }

        if (! is_array($importData)) {
            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }

            return redirect()->back()->with('error', __('Invalid JSON structure in backup file.'));
        }

        $newFiscalYearData = [
            'name' => $validated['company_name'],
            'fiscal_year' => (int) $validated['fiscal_year'],
        ];
        FiscalYearService::importData($importData, $newFiscalYearData);

        if ($zipPath && file_exists($zipPath)) {
            @unlink($zipPath);
        }

        return redirect()->route('home')->with('success', __('Company backup file imported successfully.'));
    }
}
