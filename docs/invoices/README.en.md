# Invoices guide

**[Documentation index](../menu/README.en.md)** · **[نسخه فارسی](README.md)**

This page maps the invoice workflows. Each invoice type and related action has its own guide.

> **Key distinction:** Saving an invoice, approving it and creating its accounting document, recording a payment, creating the payment document, and sending it to Moadian are five separate steps.

## Which guide should I read?

| Your task | Guide |
|---|---|
| Sell goods or services | [Sales invoice](sells/sell/sell.en.md) |
| Buy goods and receive stock | [Goods purchase invoice](purchases/buy/buy.en.md) |
| Buy services | [Service purchase invoice](purchases/buy-service/buy-service.en.md) |
| Return all or part of a sale or purchase | [Sales returns](sells/return-sell/return-sell.en.md) and [purchase returns](purchases/return-buy/return-buy.en.md) |
| Fully reverse an approved sale | [Void a sales invoice](sells/void/void.en.md) |
| Record a receipt or payment and its document | [Invoice payments](sells/sell/invoice-payments.en.md) |
| Allocate freight, insurance, or another purchase cost | [Ancillary purchase costs](purchases/ancillary-costs/ancillary-costs.en.md) |
| Set up tax submission | [How to use Moadian](sells/moadian-histories/how-to-use-moadian.en.md) |
| Transfer an invoice or close a year | [Fiscal-year operations](../management/system/companies/fiscal-year-operations.en.md) |
| Check stock and product cost | [Inventory costing](../warehouse/products/inventory-costing.en.md) |

## Guides in invoice menu order

1. [Invoice Dashboard](dashboard/dashboard.en.md)
2. Sales:
   - [Sell List](sells/sell/sell.en.md)
   - [Return Sell List](sells/return-sell/return-sell.en.md)
   - [Voided Sell](sells/void/void.en.md)
   - [Moadian Histories](sells/moadian-histories/moadian-histories.en.md) and [how to use Moadian](sells/moadian-histories/how-to-use-moadian.en.md)
3. Purchases:
   - [Buy List](purchases/buy/buy.en.md)
   - [Buy Service](purchases/buy-service/buy-service.en.md)
   - [Return Buy List](purchases/return-buy/return-buy.en.md)
   - [Service Buy Return](purchases/service-buy-return/service-buy-return.en.md)
   - [Ancillary Cost List](purchases/ancillary-costs/ancillary-costs.en.md)
4. [Beginning Inventory](beginning-inventory/beginning-inventory.en.md)
5. [Activate Confirmed Invoices](inactive/inactive.en.md)
6. [Add Customer](../customers/customers/customers.en.md) — the same canonical customer guide; no duplicate route guide is needed.

## Before you start

Before entering an invoice, check that:

1. The correct company and fiscal year are active.
2. The invoice date falls within the fiscal year.
3. The customer or supplier and its accounting subject exist.
4. The product or service and its related subject exist.
5. The form's required warehouse has been selected.
6. Subjects for tax, discounts, deductions, inventory, and cost of goods sold are configured.
7. The user has permission for the intended action.

## Header fields

| Field | Rule |
|---|---|
| Invoice type | Required; set by the menu path |
| Customer | Required and belongs to the company |
| Warehouse | Required in the form |
| Title | Optional; at most 255 characters |
| Invoice number | Positive integer, unique within the company |
| Document number | If entered, a positive unique integer; required for simultaneous approval |
| Date | Required, Jalali, and within the fiscal year |
| Deductions | Nonnegative and no more than the line total before deductions |
| Description | Optional |

## Line fields and calculations

At least one line is required. Each line selects either one product or one service.

- Quantity: integer, at least 1.
- Unit price: nonnegative.
- Discount: nonnegative and no more than the gross amount.
- Tax: nonnegative.
- Description: optional, at most 500 characters.

Calculations:

- Gross amount = quantity × unit price.
- Amount after discount = gross amount − discount.
- Tax = amount after discount × tax rate ÷ 100.
- Line total = amount after discount + tax.
- Invoice total = sum of lines − deductions.

When editing, stored tax appears as an amount; do not mistake it for the percentage rate.

## Statuses

| Status | Meaning |
|---|---|
| Pending | Saved but not approved |
| Pro forma | Proposal before final posting |
| Ready for approval | Ready for final review |
| Approved | Accounting and, where applicable, inventory effects exist |
| Approval revoked | Earlier approval effects have been reversed |
| Approved inactive | Not deleted, but removed from the active list |
| Rejected | Invoice was rejected |
| Partially settled | Part of the amount has been paid |
| Settled | Payments total the invoice amount |

A status change may depend on payments, returns, ancillary costs, later documents, or fiscal-year transfer. An `HTTP 409` response is usually a conflict warning: read it instead of blindly repeating the action.

## Checks after saving

- [ ] Type, date, number, and counterparty are correct.
- [ ] Lines, discounts, tax, deductions, and final total have been checked.
- [ ] An approved invoice has a related balanced document.
- [ ] Inventory movement was created only for goods.
- [ ] The customer or supplier balance agrees with the document and payments.
- [ ] Print, CSV, and Moadian results were checked separately when used.
