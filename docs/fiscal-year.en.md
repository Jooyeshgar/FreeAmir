# Fiscal Year in Amir

**[نسخه فارسی](fiscal-year.md)**  
**[Back to documentation index](README.en.md)**

## What Is a Fiscal Year?

A fiscal year is a defined time period used for bookkeeping, reporting, and closing accounts. Many Iranian businesses align the fiscal year with the Solar Hijri year. Amir currently supports fiscal years that start on Farvardin 1 and end at the end of Esfand.

In Amir, the fiscal year is a primary boundary for financial data. Documents, invoices, products, configurations, and reports should be created and reviewed in the correct company/fiscal-year context.

For each new company, you can create a new fiscal year and record that company's documents and invoices inside it.

## When to Create a New Fiscal Year

Create a new fiscal year when:

- The current financial period has ended. After closing accounts, a new fiscal year is created automatically.
- The company wants to record documents and invoices for a new period separately.
- Data for a new company needs to be recorded.

## Suggested Creation Flow

1. Back up the current data.
2. If the new fiscal year is related to another fiscal year, you can start by exporting and restoring the related fiscal-year data.
3. If this is a new company, create a new fiscal year.
4. Review base configurations, banks, customers, groups, products, and subjects.
5. Review opening balances.
6. Back up the new fiscal year after setup.

## Project Tools

The project includes two Artisan commands for fiscal-year data transfer or backup:

- `fiscal-year:export`: exports source fiscal-year data
- `fiscal-year:import`: imports exported data into a new fiscal year

Full details are available in [FiscalYearExportImport.md](FiscalYearExportImport.md).

## Cautions

- A fiscal-year backup contains one fiscal year only. If you have several fiscal years, create a separate backup for each one.
- After transfer, review inventory, customer balances, bank balances, and opening documents.
- Restoring a fiscal-year backup creates a new fiscal year. You can keep several copies of one company/fiscal year at the same time.
