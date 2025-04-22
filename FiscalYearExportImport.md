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
   php artisan fiscal-year:export 1
   ```
   > Output: `storage/app/exports/fiscal_year_1_<timestamp>.json`

2. **Export only banks and customers from Fiscal Year ID 5 to a specific file:**
   ```bash
   php artisan fiscal-year:export 5 --output=data_exports/fy5_banks_customers.json --sections=banks,customers
   ```
   > Output: `storage/app/data_exports/fy5_banks_customers.json`

3. **Export all data from Fiscal Year ID 2 to a custom path:**
   ```bash
   php artisan fiscal-year:export 2 --output=archive/fiscal_year_2_full.json
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
