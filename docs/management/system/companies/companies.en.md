---
title: "Companies and company settings"
description: "Create, select, and edit a company and its Moadian credentials"
---

# Companies

Menu path: **Management → System → Companies**. [Open Companies in the app]({{ site.app_url }}/management/companies). Creating or editing a company requires the relevant permission. A company is the business identity, while each fiscal year is a separate record that belongs to one company; check the company name and active fiscal year together when working.

**Current limitation:** The listing and management forms still read parts of the old one-company-per-year structure. First-time setup, adding or copying a fiscal year, and selecting a year from this page are not reliable until the refactor is completed. In the data model, business identity belongs to `Company`, each period has its own `FiscalYear` record, and user access to each period is stored separately in `fiscal_year_user`.

Once the forms are aligned, the intended flow is to keep identity details on the company, create a separate fiscal-year record for each period, copy selected sections from a source year when needed, and assign users to the years they need.

## Moadian settings

Sending an invoice requires four company identity inputs: Moadian Username, Tax ID (`tax_id`), an X.509 certificate file with a `.crt` or `.cer` extension, and a PEM private-key file with a `.pem` extension. These settings belong to the company. **Upload both files**; do not paste their contents into a text box. On the edit form, existing files are shown and need uploading again only when replacing them.

[Step-by-step Moadian guide](../../../invoices/sells/moadian-histories/how-to-use-moadian.en.md) · [Moadian Histories](../../../invoices/sells/moadian-histories/moadian-histories.en.md) · [Fiscal year in Amir](fiscal-year.en.md)
