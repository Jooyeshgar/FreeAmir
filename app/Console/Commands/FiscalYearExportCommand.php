<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\FiscalYearService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception; // Added

class FiscalYearExportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiscal-year:export
                            {source_id : The ID of the fiscal year (Company) to export}
                            {--output= : The output file path relative to the storage/app directory (e.g., exports/fiscal_year_export.json)}
                            {--sections= : Comma-separated list of sections to export (e.g., banks,customers). Exports all if omitted.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export data from a specific fiscal year to a JSON file.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $sourceYearId = (int) $this->argument('source_id');
        $outputFile = $this->option('output');
        $sectionsInput = $this->option('sections');

        // Validate source year
        if (!Company::find($sourceYearId)) {
            $this->error("Source fiscal year with ID {$sourceYearId} not found.");
            return Command::FAILURE;
        }

        // Determine sections to export
        $sectionsToExport = null;
        if (!empty($sectionsInput)) {
            $sectionsToExport = explode(',', $sectionsInput);
            $sectionsToExport = array_map('trim', $sectionsToExport); // Trim whitespace
            // Optional: Validate sections against FiscalYearService::getAvailableSections()
            $validSections = array_keys(FiscalYearService::getAvailableSections());
            $invalidSections = array_diff($sectionsToExport, $validSections);
            if (!empty($invalidSections)) {
                $this->warn("Ignoring invalid sections: " . implode(', ', $invalidSections));
                $sectionsToExport = array_intersect($sectionsToExport, $validSections);
            }
            if (empty($sectionsToExport)) {
                $this->error("No valid sections provided for export.");
                return Command::FAILURE;
            }
        }

        // Determine output file path
        if (empty($outputFile)) {
            $outputFile = 'exports/fiscal_year_' . $sourceYearId . '_' . now()->format('YmdHis') . '.json';
            $this->info("Output file not specified, using default: {$outputFile}");
        }

        $this->info("Starting export for fiscal year ID: {$sourceYearId}");
        if ($sectionsToExport) {
            $this->info("Exporting sections: " . implode(', ', $sectionsToExport));
        } else {
            $this->info("Exporting all available sections.");
        }

        try {
            $exportData = FiscalYearService::exportData($sourceYearId, $sectionsToExport);

            $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON encoding failed: " . json_last_error_msg());
            }

            Storage::disk('local')->put($outputFile, $jsonContent); // Using 'local' disk

            $fullPath = Storage::disk('local')->path($outputFile);
            $this->info("Fiscal year data exported successfully!");
            $this->info("File saved to: {$fullPath}");

            return Command::SUCCESS;
        } catch (Exception $e) {
            Log::error("Fiscal Year Export Command Failed: " . $e->getMessage(), [
                'source_id' => $sourceYearId,
                'output_file' => $outputFile,
                'exception' => $e
            ]);
            $this->error("Export failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
