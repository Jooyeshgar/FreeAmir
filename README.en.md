# Amir: Free Open-Source Accounting & ERP for Iran

**[نسخه فارسی](README.md)**

**Project status:** Amir is under active development and is not yet recommended for production use without independent review and testing. Before using it in production, validate the workflows you need with test data.

## Introduction

**Amir** is an open-source accounting and ERP platform for Iranian businesses. The project focuses on double-entry bookkeeping, sales and purchase invoices, inventory cost flows, fiscal-year operations, payroll, and compliance with Iranian tax requirements including the Moadian system.

---

## Key Features

| Category | Capabilities |
|---|---|
| **Financial Accounting** | Double-entry accounting, journal entries, general ledger, balance sheet, profit & loss |
| **Purchasing & Sales** | Customers, suppliers, sales & purchase invoicing, returns |
| **Inventory** | Warehouse management, stock tracking, weighted average costing |
| **Human Resources** | Employees, attendance, payroll, payslips |
| **Tax** | Moadian integration, tax reports |
| **Administration** | Multi-company, multiple fiscal years, roles & permissions |

---

## Quick Installation

### 1. Windows exe (not recommended for production)

Download the Windows executable from the [Releases](https://github.com/Jooyeshgar/FreeAmir/releases/) page.

### 2. Single Docker command (quick testing, not recommended for production)

```bash
docker run -d --name freeamir -p 80:80 ghcr.io/jooyeshgar/freeamir-all-in-one:latest
```

The application is available at http://localhost once running.

### 3. Docker Compose (production)

```bash
mkdir freeamir && cd freeamir
curl -O https://raw.githubusercontent.com/Jooyeshgar/FreeAmir/main/docker/production/docker-compose.prebuilt.yml
curl -O https://raw.githubusercontent.com/Jooyeshgar/FreeAmir/main/docker/production/.env.example
cp docker-compose.prebuilt.yml docker-compose.yml
cp .env.example .env
# Edit .env as needed
docker compose up -d
```

> The full installation guide is at [docs/INSTALLATION.en.md](docs/INSTALLATION.en.md).

### Default login credentials

| Email | Roles |
|---|---|
| `admin@example.com` | Super Admin |
| `accountant@example.com` | Accountant |
| `seller@example.com` | Seller |
| `warehouse@example.com` | Warehousekeeper |

Password for all accounts: `password`

---

## Development

### Using Laravel Sail

```bash
sail up -d
sail artisan test
sail npm run dev
```

Before changing accounting logic, read the [programmer guide](docs/developer/README.en.md). Changes that affect financial calculations must include tests.

---

## Documentation

| Section | Persian | English |
|---|---|---|
| Features | [features.md](docs/features.md) | [features.en.md](docs/features.en.md) |
| Comparison | [comparison.md](docs/comparison.md) | [comparison.en.md](docs/comparison.en.md) |
| FAQ | [faq.md](docs/faq.md) | [faq.en.md](docs/faq.en.md) |
| Roadmap | [roadmap.md](docs/roadmap.md) | [roadmap.en.md](docs/roadmap.en.md) |
| Screenshots | [screenshots.md](docs/screenshots.md) | [screenshots.en.md](docs/screenshots.en.md) |
| Documentation index | [docs/README.md](docs/README.md) | [docs/README.en.md](docs/README.en.md) |
| Installation guide | [docs/INSTALLATION.md](docs/INSTALLATION.md) | [docs/INSTALLATION.en.md](docs/INSTALLATION.en.md) |
| User guide | [docs/user/README.md](docs/user/README.md) | [docs/user/README.en.md](docs/user/README.en.md) |
| Accounting concepts | [docs/accounting/README.md](docs/accounting/README.md) | [docs/accounting/README.en.md](docs/accounting/README.en.md) |
| Programmer guide | [docs/developer/README.md](docs/developer/README.md) | [docs/developer/README.en.md](docs/developer/README.en.md) |

---

## Contributing

Contributions are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for bug reports, feature requests, and pull requests.

### Ways to contribute

- **Bug reports:** Use GitHub Issues
- **Feature requests:** Create an Issue with the feature request label
- **Pull requests:** Fork, create a branch, add tests, and submit a PR

---

## Roadmap

- [x] Double-entry accounting
- [x] Sales & purchase invoicing
- [x] Inventory & cost of goods
- [x] Attendance tracking
- [x] Payroll
- [x] Multi-company support
- [x] Multiple fiscal years
- [x] Full Moadian integration
- [ ] Company-specific roles
- [ ] Workflow approvals
- [ ] Audit logs
- [ ] API expansion
- [ ] Mobile / PWA support

---

## FAQ

**Is Amir really free?**
Yes. Amir is released under the GPL-3 license with no license fees or subscriptions.

**Can I host it on my own server?**
Yes. Amir is designed for self-hosted deployment. Your data stays fully under your control.

**Does it support Moadian?**
Yes. Amir supports the Moadian system. Each company can configure its own certificate and private key. [Moadian setup guide](docs/moadian.en.md)

**Does it support multiple companies?**
Yes. Amir supports multiple companies with independent fiscal years.

**Does it support payroll?**
Yes. Salary calculation, attendance, payslips, and reports are all included.

**Is it production-ready?**
Amir is under active development. Evaluate with test data before production use.

---

## License

This project is released under the GPL-3 license. See [LICENSE](LICENSE) for details.
