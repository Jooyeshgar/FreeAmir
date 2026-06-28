---
title: "FAQ"
description: "Frequently asked questions about Amir, an open-source Iranian accounting application"
---

# Frequently Asked Questions


## Is Amir really free?

Yes. Amir is released under the GPL-3 license. Using, modifying, and distributing it is completely free. There are no license fees or subscriptions.


## Can I host it on my own server?

Yes. You can set it up with Docker, Docker Compose, or a standard PHP and MariaDB installation on your own server. Your data stays fully under your control.


## Does it support Moadian?

Yes. Amir supports the Moadian system. Each company can configure its own certificate and private key. See the [Moadian setup guide](moadian.en.md) for instructions.


## Does it support multiple companies?

Yes. Amir supports multiple companies simultaneously, and each company can have multiple separate fiscal years.


## Does it support payroll?

Yes. Amir includes a payroll module that covers monthly salary calculation based on attendance, deduction and benefit management, payslip generation, and related reports.


## Is it production-ready?

Amir is under active development and is not yet recommended for production use without independent review and testing. Before using it in production, validate the workflows you need with test data.


## Who can contribute to Amir's development?

Anyone! Amir is an open-source project and contributions from everyone are welcome. See the [contribution guide](https://github.com/Jooyeshgar/FreeAmir/blob/main/CONTRIBUTING.md) for details.


## What technologies does it use?

- **Backend:** Laravel 12 (PHP)
- **Database:** MariaDB
- **Frontend:** Tailwind CSS + Alpine.js
- **Deployment:** Docker / Docker Compose
- **License:** GPL-3.0
