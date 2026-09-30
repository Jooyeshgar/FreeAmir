# Purchase return guide

Menu path: **Invoices → Purchases → Return Buy List**. A purchase return returns all or part of purchased goods or services. It is a separate invoice linked to the source purchase; editing or deleting the original purchase is not a substitute. For sales, read the [sales return guide](../../sells/return-sell/return-sell.en.md).

## Before you start and record the return

Select the correct company and fiscal year. The source must be a purchase in the same company, and the return's counterparty must match it. Identify the warehouse for returned goods and check the quantity and amount still returnable after earlier returns.

1. From Return Buy List, select the source purchase; its counterparty and lines are transferred to the form.
2. Remove unrelated lines and adjust the return quantity.
3. Compare each line's amount, discount, tax, and total with the remaining returnable balance.
4. Save or select **Save and Approve**. The source selection is locked after creation.

At least one line is required. Each line must link to a valid source line of the same product or service type. Returned quantity, unit price, discount, tax, and total cannot exceed their remaining source-line values. A duplicate return with the same type, counterparty, amount, and quantities is rejected.

## Previous-year invoice

If the source invoice is not in the current year, enable **Previous Years' Invoices** and enter the counterparty and lines manually. A source selection is then not required, and source-line balances are not checked automatically; reconcile the amount, tax, quantity, and stock yourself.

## Effects of approval

Approving a purchase return reduces the amount owed to the supplier and adjusts inventory or purchase expense, tax, and discount. Goods leave the warehouse, and the weighted average and cost of subsequent movements are recalculated.

Example: after a goods purchase, some goods are returned to the supplier. Select the source purchase, enter the quantity sent back, and check amount, discount, and tax. After approval, verify the stock outflow, reverse purchase document, supplier balance, and weighted average.

## Payments, limits, and checks

Payments on the original purchase can restrict its status change, approval revocation, or deletion. The return does not automatically remove those payments; correct a payment separately with an appropriate document.

Editing, deleting, or revoking approval may be blocked by an approved return, a later document or invoice dependent on inventory cost, ancillary costs, fiscal-year transfer, or another dependency. Review the chain backward from the latest dependent document and do not delete records directly from the database.

- [ ] Source type, counterparty, and invoice are correct.
- [ ] Quantity and amount do not exceed the returnable balance.
- [ ] The return document exists and balances.
- [ ] Stock outflow was recorded only for goods.
- [ ] Weighted average and supplier balance were corrected.
- [ ] Payments on the original invoice were reviewed separately.

For an invalid source, check its type, company, and year. For an excessive amount, inspect earlier returns. For a blocked status change, inspect later documents, payments, and ancillary costs.

## Accounting example

Suppose goods worth 10,000 rials are returned to the supplier from a 150,000-rial purchase. With no tax, discount, cash payment, or ancillary cost in this simplified example, the basic return posting is:

| Account | Debit | Credit |
|---|---:|---:|
| Supplier account (liability decreases) | 10,000 | — |
| Inventory (stock decreases) | — | 10,000 |

This is only the simple case. A real document may also contain tax, discount, ancillary-cost, or returned-cost-difference lines. Review the generated entry in the [Document List](../../../accounting/document-list/accounting-documents.en.md).
