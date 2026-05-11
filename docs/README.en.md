# Amir Documentation Index

**[نسخه فارسی](README.md)**

This directory is the Markdown documentation hub for Amir. To keep languages aligned, Persian README files have no prefix, and the English version of the same README is named `README.en.md`.

## Main Paths

| Path | Audience | Description |
|---|---|---|
| [Programmer guide](developer/README.en.md) | Developers | Architecture, database, testing, accounting basics for development, and scripts |
| [Ordinary user guide](user/README.en.md) | Non-developer users | Accounting concepts, inventory, moving weighted average, purchase/sales returns, and fiscal years |
| [Installation guide](INSTALLATION.en.md) | System admins and developers | Installation with Docker Compose, single-command Docker, or standard setup |
| [Fiscal year](fiscal-year.en.md) | Everyone | What a fiscal year is and how to create one in Amir |
| [Fiscal-year export/import](FiscalYearExportImport.md) | System admins and developers | The `fiscal-year:export` and `fiscal-year:import` commands |

## All Documentation Files

| File | Category | Description |
|---|---|---|
| [INSTALLATION.md](INSTALLATION.md) / [INSTALLATION.en.md](INSTALLATION.en.md) | Installation | Persian and English installation guides |
| [developer/README.md](developer/README.md) / [developer/README.en.md](developer/README.en.md) | Programmer | Secondary index for technical documentation |
| [user/README.md](user/README.md) / [user/README.en.md](user/README.en.md) | Ordinary user | Secondary index for practical and accounting documentation |
| [fiscal-year.md](fiscal-year.md) / [fiscal-year.en.md](fiscal-year.en.md) | Fiscal year | Fiscal-year concept and creation guide |
| [FiscalYearExportImport.md](FiscalYearExportImport.md) | Fiscal year | Exporting and importing fiscal-year data |
| [accounting-basics.md](accounting-basics.md) | Accounting | Accounting basics for developers |
| [inventory-accounting-guide.md](inventory-accounting-guide.md) | Inventory accounting | Inventory and cost of goods |
| [moving-weighted-average.md](moving-weighted-average.md) | Inventory accounting | Moving weighted average design and implementation |
| [Registering-Sales-of-Goods-in-Permanent-System.md](Registering-Sales-of-Goods-in-Permanent-System.md) | Inventory accounting | Recording sales of goods in a perpetual system |
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
