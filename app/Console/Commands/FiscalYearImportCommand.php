<?php

namespace App\Console\Commands;

use App\Services\FiscalYearService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator; // Added for validation
use Exception; // Added

class FiscalYearImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiscal-year:import
                            {file : The path to the JSON import file relative to storage/app}
                            {fiscal_year : The fiscal year identifier (positive integer)}
                            {--name= : The name for the new fiscal year (Required)}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import fiscal year data from a JSON file into a new fiscal year.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $inputFile = $this->argument('file');
        $fiscalYear = $this->argument('fiscal_year');
        $newName = $this->option('name');

        if (empty($newName)) {
            $this->error('The --name option is required.');
            return Command::FAILURE;
        }

        if (!ctype_digit($fiscalYear) || (int)$fiscalYear <= 0) {
            $this->error('The fiscal_year argument must be a positive integer.');
            return Command::FAILURE;
        }
        $fiscalYear = (int)$fiscalYear; // Cast to integer

        if (!Storage::disk('local')->exists($inputFile)) {
            $this->error("Import file not found at: storage/app/{$inputFile}");
            return Command::FAILURE;
        }

        // --- Confirmation ---
        $fullPath = Storage::path($inputFile); // Use Storage::path() for the absolute path
        $this->warn("You are about to import data from:");
        $this->line($fullPath);
        $this->warn("This will create a NEW fiscal year entry with:");
        $this->line("  Name: '{$newName}'");
        $this->line("  Fiscal Year: {$fiscalYear}");
        $this->warn("And populate it with data from the file.");

        if (!$this->option('force') && !$this->confirm('Do you wish to continue?', false)) {
            $this->info('Import cancelled.');
            return Command::INVALID;
        }


        $this->info("Starting import from: {$inputFile}");

        try {
            $jsonContent = Storage::disk('local')->get($inputFile);
            $importData = json_decode($jsonContent, true); // Decode as associative array

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON file: " . json_last_error_msg());
            }

            // Debug info about import data size
            $this->info("Import data analysis:");
            $this->line("  Raw file size: " . number_format(strlen($jsonContent)) . " bytes");
            $this->line("  Total records: " . count($importData));

            // Show breakdown by data type if structure is available
            foreach ($importData as $key => $value) {
                if (is_array($value)) {
                    $this->line("  {$key}: " . count($value) . " items");
                }
            }

            // Prepare data for the new Company record
            $newFiscalYearData = [
                'name' => $newName,
                'fiscal_year' => $fiscalYear,
            ];

            $newFiscalYear = FiscalYearService::importData($importData, $newFiscalYearData);

            $this->info("Fiscal year imported successfully!");
            $this->info("New Fiscal Year ID: {$newFiscalYear->id}");
            $this->info("Name: {$newFiscalYear->name}");

            return Command::SUCCESS;
        } catch (Exception $e) {
            Log::error("Fiscal Year Import Command Failed: " . $e->getMessage(), [
                'input_file' => $inputFile,
                'new_name' => $newName,
                'exception' => $e
            ]);
            $this->error("Import failed: " . $e->getMessage());
            $this->error("Check the application logs for more details.");
            return Command::FAILURE;
        }
    }
}
