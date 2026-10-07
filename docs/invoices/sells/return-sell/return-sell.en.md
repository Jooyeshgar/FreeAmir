# Sales return guide

Menu path: **Invoices → Sales → Return Sell List**. A sales return reverses all or part of the lines on a sales invoice. It creates a separate invoice linked to the source; editing or deleting the original sale is not a substitute. For the purchase side, see the [purchase return guide](../../purchases/return-buy/return-buy.en.md).

## Before you start and record the return

Select the correct fiscal year. The source must be a sale in that fiscal year, and the return's customer must match the sale's customer. Select the warehouse and check the quantity and amount still returnable after earlier returns.

1. From Return Sell List, select the source invoice; its customer and lines are transferred to the form.
2. Remove unrelated lines and adjust the return quantity.
3. Compare each line's amount, discount, tax, and total with the remaining returnable balance.
4. Save, or select **Save and Approve** to post the financial effects. The source invoice selection is locked after creation.

A return requires at least one line. Each line must link to a valid source line of the same product or service type. Returned quantity, unit price, discount, tax, and total cannot exceed the corresponding remaining values on the source line. A duplicate return with the same type, customer, amount, and quantities is rejected.

## Previous-year invoice

If the source invoice is not in the current fiscal year, enable **Previous Years' Invoices**. A source invoice is then not required, and you enter the customer and lines manually; the source-line balance is not checked automatically. Reconcile amount, tax, quantity, and inventory yourself.

## Effects of approval

Approving a sales return reduces the customer's receivable and adjusts sales revenue, tax, and discount in proportion to the return. Goods are received into the warehouse, and the cost-of-goods-sold effect is reversed. The returned goods' cost is recovered from the source sale's inventory outflow, not from the sales price.

Example: three of ten units sold are returned. Enter a return quantity of 3, check the related discount and tax, then after approval verify the reverse document, receipt of three units, cost of goods sold, and customer balance.

## Payments, limits, and checks

Payments on the original invoice can restrict its status change, approval revocation, or deletion. A return does not automatically delete the original payment; correct the receipt separately with an appropriate document.

Editing, deleting, or revoking approval may be blocked by an approved return, a later document or invoice that depends on inventory cost, ancillary costs, fiscal-year transfer, or another dependent record. Review dependencies backward from the latest document; do not delete records directly from the database.

- [ ] The source invoice type and customer are correct.
- [ ] Quantity and amount do not exceed the returnable balance.
- [ ] The return document exists and balances.
- [ ] Inventory receipt was recorded only for goods.
- [ ] Cost of goods sold and customer balance were corrected.
- [ ] Receipts on the original invoice were reviewed separately.

If the source is invalid, check its type and fiscal year. If the amount or quantity exceeds the balance, inspect prior returns. For a blocked status change, inspect later documents, payments, and ancillary costs.
