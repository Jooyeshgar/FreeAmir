---
title: "How to use Moadian"
description: "Configure company credentials, send invoices, and check Moadian results in Amir"
---

# Using the Moadian tax system

Menu path: **Invoices → Sales → Moadian Histories**. Before sending, select the correct company and fiscal year and complete that company's settings under [Management → System → Companies]({{ site.app_url }}/management/companies). You can [open Moadian Histories in the app]({{ site.app_url }}/invoices/moadian-histories).

## Four pieces of information required to send

In the create or edit company form, enter the **Moadian Username** (the unique tax memory ID). In addition to that username, provide these three items:

1. Enter the company's **Tax ID** (`tax_id`) in the Tax ID field.
2. Upload the **signature certificate file** with a `.crt` or `.cer` extension. It must contain a valid X.509 certificate.
3. Upload the **private-key file** with a `.pem` extension and PEM-formatted contents.

These are four separate inputs; the three items above do not replace the username. The company form permits them to be left blank, but the app checks all four **before an invoice is sent**. When editing a company, existing filenames are shown; do not upload them again unless they need replacing. Upload the files themselves—do not paste their contents into text fields.

## Obtain the files and identifier

To request an organizational seal certificate, generate a private key and CSR according to the certificate authority's instructions. This example gives the key the extension accepted by Amir's form; enter the company's actual identity values in the CSR configuration as required by the issuer:

```bash
openssl req -new -newkey rsa:2048 -nodes -keyout private.pem -out company.csr -config csr.conf
```

Submit the CSR to the appropriate certificate issuer and obtain the issued `.crt` or `.cer` certificate. In the Moadian portal, find the unique tax memory ID associated with that certificate and enter the same value as the Moadian Username in Amir. **Amir does not require a separate public-key upload.** The `private.pem` file is the company's signing key: keep it confidential and maintain a secure backup.

## Send an invoice and check the result

1. Create and approve or settle a sales, sales-return, or void invoice. Purchase and purchase-return invoices cannot be sent.
2. Check that every line has a tax product/service identifier (`SSTID`). Before sending a void invoice, its original invoice must already have been sent successfully.
3. Use the send-to-Moadian action on the invoice page. An invoice with a successful prior submission cannot be sent again.
4. In [Moadian Histories]({{ site.app_url }}/invoices/moadian-histories), review the result, reference number, and status. If a reference number exists but the status is still unknown, use **Check Status**. For a failed send, use **Send Again** when available.

If the app says the Moadian credentials are incomplete, check all four inputs for the active company. If it reports a missing item identifier, correct the `SSTID` on every invoice line. A failure may also come from the external service; inspect that history entry for details.

[Companies guide](../../../management/system/companies/companies.en.md) · [Sales invoice guide](../sell/sell.en.md)
