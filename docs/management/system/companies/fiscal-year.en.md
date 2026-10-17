# Fiscal Year in Amir

**[نسخه فارسی](fiscal-year.md)**

**[Back to documentation index](../../../menu/documentation.en.md)**

**[Practical guide to getting started and closing a fiscal year](getting-started-fiscal-year.en.md)**

## What Is a Fiscal Year?

A fiscal year is a defined time period used for bookkeeping, reporting, and closing accounts. Many Iranian businesses align the fiscal year with the Solar Hijri year, although a company may need a different period for legal or management reasons. Amir currently supports only fiscal years that start on Farvardin 1 and end at the end of Esfand.

In Amir, a company is the business identity and can have multiple fiscal years. Each fiscal year is a separate record and the primary boundary for that period's data; documents, invoices, products, configurations, and reports are linked to the relevant fiscal year. User access is assigned separately for each fiscal year.

The fiscal-year management page creates a new fiscal year for an existing company using separate company and fiscal-year records. Use **Create Fiscal Year** from the management page for this task.

The `fiscal-year:import` command creates a new company and fiscal year. After import, assign user access through user management. See the [export and import command guide](../../../developer/FiscalYearExportImport.en.md).

An existing company can have another fiscal year, with the new period's documents and invoices recorded in that year.

## When to Create a New Fiscal Year

Create a new fiscal year when:

- The current financial period has ended. Closing accounts creates the next fiscal year for the same company.
- The company wants to record documents and invoices for a new period separately.
- Data for a new company needs to be recorded.

## Intended Creation Flow

1. Back up the source fiscal-year data.
2. Select the destination company or create it for a new business.
3. Attach a separate fiscal-year record to that company; copy selected sections from a source year if needed.
4. Assign users access to the destination year.
5. Review base configurations, banks, customers, groups, products, and accounts.
6. Review opening balances and back up the new fiscal year after setup.

## Project Tools

The project includes two Artisan commands for fiscal-year data transfer or backup:

- `fiscal-year:export`: exports source fiscal-year data
- `fiscal-year:import`: imports exported data into a new fiscal year

Full details are available in [FiscalYearExportImport.en.md](../../../developer/FiscalYearExportImport.en.md).

## Cautions

- A fiscal-year backup contains one fiscal year only. If you have several fiscal years, create a separate backup for each one.
- After transfer, review inventory, customer balances, bank balances, and opening documents.
- Restoring a backup loads the data for a fiscal year; check the destination before proceeding. One company can have multiple separate fiscal years.
