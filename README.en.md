# Amir: Free Laravel Accounting Software

**[نسخه فارسی](README.md)**

**Project status:** Amir is under active development and is not yet recommended for production use without independent review and testing. Before using it in Production, validate the workflows you need with test data.

## Introduction

**Amir** is a free and open-source accounting application built with Laravel for Iranian businesses. The project focuses on double-entry bookkeeping, sales and purchase invoices, inventory cost flows, fiscal-year operations, and a Persian-first user experience.

## Features

- Double-entry accounting with balanced debit and credit documents
- Invoice, customer, bank, product, and grouping management
- Inventory accounting with moving weighted average cost flow
- Fiscal-year operations, including creating fiscal years and importing/exporting fiscal-year data
- Financial reports and dashboards
- Persian localization, Jalali calendar support, and common Iranian business requirements
- Open source under the GPL-3 license

## Quick Installation

The full installation guide is available at [docs/INSTALLATION.en.md](docs/INSTALLATION.en.md) and covers three paths:

- Docker Compose for production-style deployment
- Single-command Docker for quick testing
- Standard installation with PHP, Composer, npm, and MariaDB

After installation, the app is usually available at `http://localhost:8000`. Default testing credentials:

- Email: `admin@example.com`
- Password: `password`

## Documentation

All project documentation files are Markdown. Persian README files have no prefix, and their English counterparts use the `README.en.md` pattern.

| Section | Persian | English |
|---|---|---|
| Full documentation index | [docs/README.md](docs/README.md) | [docs/README.en.md](docs/README.en.md) |
| Programmer guide | [docs/developer/README.md](docs/developer/README.md) | [docs/developer/README.en.md](docs/developer/README.en.md) |
| Ordinary user guide | [docs/user/README.md](docs/user/README.md) | [docs/user/README.en.md](docs/user/README.en.md) |
| Installation | [docs/INSTALLATION.md](docs/INSTALLATION.md) | [docs/INSTALLATION.en.md](docs/INSTALLATION.en.md) |
| What a fiscal year is and how to create one | [docs/fiscal-year.md](docs/fiscal-year.md) | [docs/fiscal-year.en.md](docs/fiscal-year.en.md) |
| Fiscal-year export/import | [docs/FiscalYearExportImport.md](docs/FiscalYearExportImport.md) | currently the same English document |

## Development

For local development, use Laravel Sail or direct commands:

```bash
sail up -d
sail artisan test
sail npm run dev
```

Without Sail:

```bash
php artisan test
npm run dev
```

Before changing accounting logic, read the [programmer guide](docs/developer/README.en.md). Changes that affect financial calculations must include tests.

## Contributing

Contributions are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for bug reports, feature requests, and pull requests.

## License

This project is released under the GPL-3 license. See [LICENSE](LICENSE) for details.
