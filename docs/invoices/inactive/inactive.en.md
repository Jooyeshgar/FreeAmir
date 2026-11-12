---
title: "Activate and Deactivate Approved Invoices"
description: "Change invoice activity from its details page, with accounting and inventory effects, payments, ancillary costs, and restrictions"
---

# Activate and Deactivate Approved Invoices

From an invoice's details page, you can deactivate an approved invoice and reactivate it later. The invoice retains its approved inactive status, while its accounting document and inventory and cost effects are reversed. This operation does not delete, return, or void the invoice.

## Before you start

- Select the correct company and fiscal year. The fiscal year must be open.
- From **Invoices**, open the appropriate list, such as sales, goods purchases, sales returns, or purchase returns, then open the invoice details.
- Check the invoice type, number, date, customer or supplier, warehouse, and amount. Review its current accounting document, payments, and ancillary costs so you can compare the results afterward.
- Displaying these action buttons on the details page requires `invoices.approve`. Performing **Approve inactive** requires that same permission, while performing **Activate** requires `invoices.change-status`. To use both directions from the details page, you need both permissions.

## Deactivate from invoice details

1. Check that the status on the details page is **approved**.
2. In the actions section, select **Approve inactive**. The separate **Unapprove** button changes the invoice to unapproved status; use **Approve inactive** to retain approved inactive status.
3. If a warning appears, review its cause and the related invoices before confirming the operation. A blocking conflict may replace the direct action button with a **Resolve conflicts and deactivate invoice** link. Its effect on multiple invoices is explained under restrictions below.
4. After success, check for **“Invoice deactivated successfully.”** and the **approved inactive** status. The **Activate** button appears if the next operation is permitted.

## Reactivate from invoice details

1. Go to **Invoices → Activate Confirmed Invoices** and open the inactive invoice by its number. [Open the list in the app]({{ site.app_url }}/invoices/inactive).
2. Review the invoice, its ancillary costs, and retained payments, then select **Activate**.
3. Review any warnings. If a blocking conflict appears, investigate its cause on the conflicts page first.
4. Check for **“Invoice activated successfully.”** The invoice document is recreated, and the final status becomes approved, partially paid, or paid according to its effective payments.

Reactivation first restores this invoice's previously approved inactive ancillary costs in date order, then by ID, and then creates documents for eligible payments. Unapproved costs are not approved by this operation. If restoring a cost or payment fails, the entire reactivation is rolled back: the invoice remains inactive, and no partial changes to its documents, inventory, or related records are saved.

## Accounting and inventory effects

| Item | After deactivation | After reactivation |
|---|---|---|
| Invoice and line items | Retained with approved inactive status | The same invoice becomes active again |
| Invoice document | The document and its transactions are deleted | A balanced document and new transactions are created |
| Product and warehouse quantities | The invoice's stock receipt or issue is reversed | The stock receipt or issue is applied again |
| Inventory cost | Average costs and line-item costs are recalculated using the remaining active history | Costs are recalculated in chronological order |
| Related approved ancillary costs | Deactivated; their documents and inventory-cost effects are reversed | The related inactive costs' documents and effects are restored |
| Payments | Records and amounts are retained; non-cheque payment documents are removed during reversal | Eligible non-cheque payment documents are recreated without duplicate payment records |

For goods, deactivating a **purchase or sales return** subtracts the invoice quantity from inventory; deactivating a **sale or purchase return** adds it back. Reactivation applies the opposite movement. Service line items do not create stock movements.

For example, deactivating a purchase of 10 units without conflicts reduces the product and relevant warehouse quantities by 10 and deletes the purchase document. Reactivation restores those 10 units and recreates the purchase document. If invoice and cost history has not changed in the meantime, quantities and costs return to their values before deactivation.

## Payments and operation restrictions

- **Invoices with payments:** The direct **Approve inactive** action is unavailable for invoices with **Partially paid** or **Paid** status. This feature does not introduce a new direct deactivation path for settled invoices; payments must be addressed or the permitted dependency-resolution flow used. During dependency deactivation, payment records and amounts are retained. Automatic payment-document restoration during reactivation applies to eligible non-cheque payments; cheque payments remain subject to cheque rules. See the [invoice payments guide](../sells/sell/invoice-payments.en.md).
- **Closed fiscal year or insufficient access:** Neither direction is permitted. Check with the system administrator that the fiscal year is open and you have the required permissions. The invoice must belong to the active fiscal year and be accessible to you.
- **Successful Moadian submission:** **Approve inactive** is hidden for an invoice with a successful submission result, and a deactivation request is also rejected. Review its [Moadian history](../sells/moadian-histories/moadian-histories.en.md).
- **Returns, voids, and chronological dependencies:** A related return or void invoice, a later approved invoice for the same goods, or a related ancillary cost may block the operation. Reactivation also checks stock restrictions for sales and purchase returns. Retrying the request does not resolve the error; review the related invoices and the stated cause.
- **Group conflict resolution:** Opening **Resolve conflicts and deactivate invoice** does not itself change any records. Running the operation from the conflicts page is a separate action requiring `invoices.group-action` and an open fiscal year; viewing the page also requires `invoices.conflicts`. When permitted, it deactivates the selected invoice and its active dependent invoices in descending date and ID order, reversing their accounting documents and stock effects. Review all displayed invoices and costs with the responsible accountant before running it.

A warning differs from a blocking error: you can confirm a warning after reviewing it, but a blocking error must be resolved. An unsuccessful direct request must not automatically deactivate dependent invoices.

## Bulk activation from the list

**Invoices → Activate Confirmed Invoices** shows approved inactive invoices and their related inactive ancillary costs. Before acting, check each row's type, number, customer, date, and amount, and inspect any ancillary-cost row showing a warning.

To activate one invoice, open its number and use **Activate** on its details page. **Approve All** sends a bulk activation request; do not use it to activate only one invoice. After bulk activation, check the result message and the purchase invoice list. If a record was not activated, review that invoice and its related ancillary cost separately.

## Check the result

- After deactivation, verify the inactive status and the absence of the invoice document and documents for reversed costs.
- Reconcile product and warehouse quantities, average costs, and the customer or supplier balance with the effects of reversing or recreating the documents.
- After reactivation, the invoice document, restored cost documents, and eligible payment documents should exist and be balanced.
- Compare the amounts and number of payment records; do not enter duplicate payments. Expect approved status without effective payments, **Partially paid** for partial settlement, and **Paid** for full settlement.

The [general invoices guide](../README.en.md), [purchase ancillary costs guide](../purchases/ancillary-costs/ancillary-costs.en.md), and [inventory costing guide](../../warehouse/products/inventory-costing.en.md) explain the related checks.

This guide covers the behavior added in [merge request !440](https://vc.jooyeshgar.net/JPE/FreeAmir/-/merge_requests/440) for [issue #438](https://vc.jooyeshgar.net/JPE/FreeAmir/-/issues/438).
