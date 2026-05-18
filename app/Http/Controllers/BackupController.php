<?php

namespace App\Http\Controllers;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Scopes\FiscalYearScope;
use App\Services\FiscalYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class BackupController extends Controller
{
    public function create()
    {
        $previousYears = Company::all();
        $currentYear = toEnglish(jdate('Y'));

        return view('backups.create', compact('previousYears', 'currentYear'));
    }

    public function documentFilesSize(Request $request): JsonResponse
    {
        $validated = $request->validate(['source_id' => 'required|exists:companies,id']);

        $bytes = FiscalYearService::documentFilesSizeBytes($validated['source_id']);

        return response()->json(['size_mb' => round($bytes / (1024 * 1024), 2)]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'source_id' => 'required|exists:companies,id',
            'tables_to_backup' => 'required|array',
            'tables_to_backup.*' => 'string|in:'.implode(',', array_map(fn ($case) => $case->value, FiscalYearSection::cases())),
        ]);

        $includeDocumentFiles = in_array(FiscalYearSection::DOCUMENT_FILES->value, $validated['tables_to_backup']);
        $dbSections = array_values(array_filter(
            $validated['tables_to_backup'],
            fn ($s) => $s !== FiscalYearSection::DOCUMENT_FILES->value
        ));

        $company = Company::findOrFail($validated['source_id']);
        $exportData = FiscalYearService::exportData($validated['source_id'], $dbSections);
        $safeName = preg_replace('/\s+/', '-', trim($company->name));
        $fileBaseName = "Amir-{$safeName}-".now()->format('Y-m-d-H-i');

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

        if (! $zip->addFromString("{$fileBaseName}.json", $jsonContent)) {
            $zip->close();
            @unlink($zipFilePath);

            return redirect()->back()->with('error', __('Failed to add JSON to ZIP.'));
        }

        if ($includeDocumentFiles) {
            FiscalYearService::documentFilesToZip($zip, $validated['source_id']);
        }

        $zip->close();

        return response()->download($zipFilePath, "{$fileBaseName}.zip", ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
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
        $newCompany = FiscalYearService::importData($importData, $newFiscalYearData);

        // Restore actual document files if the backup includes document files.
        $disk = Storage::disk('public');
        $newDocumentIds = Document::withoutGlobalScope(FiscalYearScope::class)->where('company_id', $newCompany->id)->pluck('id');
        $newDocFiles = DocumentFile::whereIn('document_id', $newDocumentIds)->get()->keyBy('path');

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            if (! is_array($entry) || ! isset($entry['name'])) {
                continue;
            }
            $entryName = $entry['name'];
            if (str_ends_with($entryName, '/') || ! str_starts_with($entryName, 'files/')) {
                continue;
            }

            $oldRelPath = Str::after($entryName, 'files/'); // e.g. documents/123/uuid.pdf
            $docFile = $newDocFiles->get($oldRelPath);
            if (! $docFile) {
                continue;
            }

            $newRelPath = 'documents/'.$docFile->document_id.'/'.basename($oldRelPath);
            $stream = $zip->getStream($entryName);
            if ($stream) {
                $disk->writeStream($newRelPath, $stream);
                fclose($stream);
                $docFile->update(['path' => $newRelPath]);
            }
        }

        $zip->close();

        if ($zipPath && file_exists($zipPath)) {
            @unlink($zipPath);
        }

        return redirect()->route('home')->with('success', __('Company backup file imported successfully.'));
    }
}
