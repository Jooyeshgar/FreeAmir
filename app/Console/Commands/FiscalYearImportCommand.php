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
                            {--name= : The name for the new fiscal year (Required)}
                            {--start-date= : The start date (YYYY-MM-DD) for the new fiscal year (Required)}
                            {--end-date= : The end date (YYYY-MM-DD) for the new fiscal year (Required)}
                            {--force : Skip confirmation prompt}';
    // Add more options here if your Company model requires other fields on creation

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
        $newName = $this->option('name');
        $newStartDate = $this->option('start-date');
        $newEndDate = $this->option('end-date');

        // --- Basic Input Validation ---
        if (empty($newName) || empty($newStartDate) || empty($newEndDate)) {
            $this->error('The --name, --start-date, and --end-date options are required.');
            return Command::FAILURE;
        }

        $validator = Validator::make([
            'start_date' => $newStartDate,
            'end_date' => $newEndDate,
        ], [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            $this->error('Invalid date format or range:');
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return Command::FAILURE;
        }

        // Check if file exists
        if (!Storage::disk('local')->exists($inputFile)) {
            $this->error("Import file not found at: storage/app/{$inputFile}");
            return Command::FAILURE;
        }
        // --- End Validation ---


        // --- Confirmation ---
        $fullPath = Storage::disk('local')->path($inputFile);
        $this->warn("You are about to import data from:");
        $this->line($fullPath);
        $this->warn("This will create a NEW fiscal year named '{$newName}' ({$newStartDate} to {$newEndDate})");
        $this->warn("And populate it with data from the file.");

        if (!$this->option('force') && !$this->confirm('Do you wish to continue?', false)) {
            $this->info('Import cancelled.');
            return Command::INVALID;
        }
        // --- End Confirmation ---


        $this->info("Starting import from: {$inputFile}");

        try {
            // Read and decode file
            $jsonContent = Storage::disk('local')->get($inputFile);
            $importData = json_decode($jsonContent, true); // Decode as associative array

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON file: " . json_last_error_msg());
            }

            // Prepare data for the new Company record
            $newFiscalYearData = [
                'name' => $newName,
                'start_date' => $newStartDate,
                'end_date' => $newEndDate,
                // Add other required fields for your Company model here
                // e.g., 'is_active' => false,
                // 'currency_id' => 1, // Or fetch a default
            ];

            // Perform the import using the service
            $newFiscalYear = FiscalYearService::importData($importData, $newFiscalYearData);

            $this->info("Fiscal year imported successfully!");
            $this->info("New Fiscal Year ID: {$newFiscalYear->id}");
            $this->info("Name: {$newFiscalYear->name}");

            return Command::SUCCESS;
        } catch (Exception $e) {
            // The service already logs the detailed error
            Log::error("Fiscal Year Import Command Failed: " . $e->getMessage(), [
                'input_file' => $inputFile,
                'new_name' => $newName,
                'exception' => $e // Log exception from command context too if needed
            ]);
            $this->error("Import failed: " . $e->getMessage());
            $this->error("Check the application logs for more details.");
            return Command::FAILURE;
        }
    }
}
