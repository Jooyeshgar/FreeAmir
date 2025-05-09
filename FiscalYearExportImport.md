# Fiscal Year Export Command (`fiscal-year:export`)

This Artisan command exports data associated with a specific fiscal year (represented by a `Company` record) into a JSON file. This file can be used for backups, migrations, or as input for the `fiscal-year:import` command.

## Purpose

Extract specific data sections (like banks, customers, products, configurations, etc.) linked to a single fiscal year from the database and save them in a structured JSON format.

## Usage

```bash
php artisan fiscal-year:export <source_id> [options]
```

## Arguments

| Argument   | Description                                      | Required |
|------------|--------------------------------------------------|----------|
| `source_id`| The numeric ID of the Company record to export. | Yes      |

---

## Options

| Option       | Description                                                                                                                                                    | Required | Default                                               |
|--------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------|----------|-------------------------------------------------------|
| `--output`   | Relative path within `storage/app` for the output JSON file (e.g., `my_exports/fy_export.json`). If omitted, a default name is generated in the `exports/` dir. | No       | `exports/fiscal_year_<source_id>_<timestamp>.json`   |
| `--sections` | Comma-separated list of data sections to export (e.g., `banks,customers,products`). Valid sections are defined in `FiscalYearService::getAvailableSections()`. | No       | All available sections                                |

**Available Sections (from `FiscalYearService`):**
- `configs`
- `banks`
- `bank_accounts`
- `customer_groups`
- `customers`
- `product_groups`
- `products`
- `subjects`

## Examples

1. **Export all data from Fiscal Year ID 1 to a default file:**
   ```bash
   sail artisan fiscal-year:export 1
   ```
   > Output: `storage/app/exports/fiscal_year_1_<timestamp>.json`

2. **Export only banks and customers from Fiscal Year ID 5 to a specific file:**
   ```bash
   sail artisan fiscal-year:export 5 --output=data_exports/fy5_banks_customers.json --sections=banks,customers
   ```
   > Output: `storage/app/data_exports/fy5_banks_customers.json`

3. **Export all data from Fiscal Year ID 2 to a custom path:**
   ```bash
   sail artisan fiscal-year:export 2 --output=archive/fiscal_year_2_full.json
   ```
   > Output: `storage/app/archive/fiscal_year_2_full.json`


## Output

- The command prints the full path to the generated JSON file.
- The JSON contains keys for each exported section (e.g., `"banks"`, `"customers"`), each holding an array of model data.
- A `"meta"` key is included with:
  - `source_id`
  - `company_name`
  - `timestamp`
  - `sections_exported`

---

## Error Handling

- If the provided `source_id` doesn't match an existing `Company`, an error message is displayed.
- Invalid section names in `--sections` trigger a warning. If no valid sections remain, the command fails.
- Other issues (e.g., file write permissions) result in an error message and are logged to `storage/logs/laravel.log`.

---

# Fiscal Year Import Command (`fiscal-year:import`)

This Artisan command imports data from a JSON file (typically generated by `fiscal-year:export`) into a **new** fiscal year (Company record).

## Purpose

Create a new fiscal year and populate it with data (banks, customers, products, etc.) from a previously exported JSON file. This is useful for restoring backups or migrating fiscal year data between environments.

## Usage

```bash
php artisan fiscal-year:import <file> <fiscal_year> --name=<new_name> [options]
```

## Arguments

| Argument      | Description                                                              | Required |
|---------------|--------------------------------------------------------------------------|----------|
| `file`        | The path to the JSON import file, relative to the `storage/app` directory. | Yes      |
| `fiscal_year` | The fiscal year identifier (positive integer).                           | Yes      |

---

## Options

| Option    | Description                                                                                                                            | Required | Default |
|-----------|----------------------------------------------------------------------------------------------------------------------------------------|----------|---------|
| `--name`  | The name for the **new** fiscal year being created.                                                                                    | Yes      |         |
| `--force` | Skip the confirmation prompt before starting the import. Use with caution, especially in production environments.                        | No       | `false` |
| *Note:*   | *Depending on your `Company` model setup, additional required fields might need to be handled within the `FiscalYearService::importData` method.* |          |         |

## Examples

1.  **Import data from `exports/fy1_backup.json` into a new fiscal year named "Fiscal Year 2024" with fiscal year identifier 2024:**
    ```bash
    sail artisan fiscal-year:import exports/fy1_backup.json 2024 --name="Fiscal Year 2024"
    ```
    >   This will prompt for confirmation before proceeding.

2.  **Import data from `archive/old_data.json` forcefully (no confirmation):**
    ```bash
    sail artisan fiscal-year:import archive/old_data.json 2023 --name="Restored FY" --force
    ```

## Process

1.  **Validation:**
    *   Checks if `--name` is provided.
    *   Validates that the `fiscal_year` argument is a positive integer.
    *   Verifies that the specified import `file` exists within `storage/app`.
2.  **Confirmation:**
    *   Displays the full path of the file being imported and the details of the new fiscal year to be created.
    *   Prompts the user to confirm unless `--force` is used.
3.  **Import Execution:**
    *   Reads and decodes the JSON file.
    *   Prepares the data for creating a new `Company` record using the provided `--name` and `fiscal_year`.
    *   Calls the `FiscalYearService::importData` method, passing the decoded JSON data and the new fiscal year details. This service handles the actual creation of the new `Company` and the insertion of related data (banks, customers, etc.) associated with the new company ID.
4.  **Output:**
    *   On success, prints a confirmation message including the ID and name of the newly created fiscal year.
    *   On failure, displays an error message.

---

## Error Handling

- If the required `--name` option is missing, an error is shown.
- If the `fiscal_year` argument is not a positive integer, an error is shown.
- If the import file is not found at the specified path, an error is displayed.
- If the JSON file is invalid or cannot be decoded, an error message is shown.
- Any exceptions during the `FiscalYearService::importData` process (e.g., database errors, validation issues within the service) will result in an error message being displayed, and details are logged to `storage/logs/laravel.log`.
- If the user cancels the confirmation prompt, an "Import cancelled" message is shown.
