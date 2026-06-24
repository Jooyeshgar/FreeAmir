# Amir Documentation Index

**[نسخه فارسی](README.md)**

This directory is the Markdown documentation hub for Amir. To keep languages aligned, Persian README files have no prefix, and the English version of the same README is named `README.en.md`.

## Product Pages

| Page | Description |
|---|---|
| [Features](features.en.md) | Complete list of Amir's capabilities |
| [Comparison](comparison.en.md) | Comparison with commercial software |
| [FAQ](faq.en.md) | Frequently asked questions |
| [Roadmap](roadmap.en.md) | Future development roadmap |
| [Screenshots](screenshots.en.md) | User interface screenshots |
| [Moadian setup](moadian.en.md) | Moadian system setup guide |

## Main Paths

| Path | Audience | Description |
|---|---|---|
| [Ordinary user guide](user/README.en.md) | Non-developer users | Day-to-day operations, attendance, salary, inventory, and fiscal years |
| [Accounting concepts](accounting/README.en.md) | Users and developers | Accounting concepts, COGS, purchase/sales returns, and fiscal years |
| [Programmer guide](developer/README.en.md) | Developers | Architecture, database, testing, and scripts |
| [Installation guide](INSTALLATION.en.md) | System admins and developers | Installation with Docker Compose, single-command Docker, or standard setup |
| [Fiscal year](fiscal-year.en.md) | Everyone | What a fiscal year is and how to create one in Amir |
| [Fiscal-year export/import](FiscalYearExportImport.md) | System admins and developers | The `fiscal-year:export` and `fiscal-year:import` commands |

## All Documentation Files

| File | Category | Description |
|---|---|---|
| [INSTALLATION.md](INSTALLATION.md) / [INSTALLATION.en.md](INSTALLATION.en.md) | Installation | Persian and English installation guides |
| [user/README.md](user/README.md) / [user/README.en.md](user/README.en.md) | Ordinary user | Secondary index for practical and accounting documentation |
| [accounting/README.md](accounting/README.md) / [accounting/README.en.md](accounting/README.en.md) | Accounting | Accounting concepts index |
| [developer/README.md](developer/README.md) / [developer/README.en.md](developer/README.en.md) | Programmer | Secondary index for technical documentation |
| [user/attendance/README.md](user/attendance/README.md) / [user/attendance/README.en.md](user/attendance/README.en.md) | Attendance | Work shifts, logs, imports, and monthly attendance |
| [user/salary/README.md](user/salary/README.md) / [user/salary/README.en.md](user/salary/README.en.md) | Salary and payroll | Payroll elements, salary decrees, and payrolls |
| [user/inventory-costing.md](user/inventory-costing.md) / [user/inventory-costing.en.md](user/inventory-costing.en.md) | Inventory accounting | COGS, costing methods, and Amir's selected method |
| [fiscal-year.md](fiscal-year.md) / [fiscal-year.en.md](fiscal-year.en.md) | Fiscal year | Fiscal-year concept and creation guide |
| [FiscalYearExportImport.md](FiscalYearExportImport.md) | Fiscal year | Exporting and importing fiscal-year data |
| [accounting-basics.md](accounting-basics.md) | Accounting | Accounting basics, debit, credit, and documents |
| [return-sell-return-buy.md](return-sell-return-buy.md) | Inventory accounting | Sales returns and purchase returns |
| [project-structure.md](project-structure.md) | Technical | Laravel project structure |
| [database-guide.md](database-guide.md) | Technical | Database structure and relationships |
| [testing-guide.md](testing-guide.md) | Technical | Testing guide and test execution |
| [../script/README.md](../script/README.md) | Tools | Migration and data script documentation |

## Documentation Maintenance

- Every Persian README should stay structurally aligned with its English README counterpart.
- When a new file is added under `docs/`, reference it here or in one of the two secondary indexes.
- If a document is useful to ordinary users, include it in [user/README.en.md](user/README.en.md).
- If a document is about development or internal structure, include it in [developer/README.en.md](developer/README.en.md).
