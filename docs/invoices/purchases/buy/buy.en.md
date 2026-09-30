# Goods purchase invoice guide

This guide covers **goods purchases** only. Service purchases are described in the [service purchase invoice guide](../buy-service/buy-service.en.md).

## Purpose

Use a goods purchase invoice to record a supplier purchase, receive products into a warehouse, establish the amount owed to the counterparty, and calculate inventory cost.

## Before you start

- Select the correct company and fiscal year.
- Define the supplier as a customer/counterparty with an accounting subject.
- Define each product, its product group, and inventory subject.
- Identify the destination warehouse.
- Configure the subjects for purchases, purchase tax, purchase discounts, deductions, and inventory.

## Menu path

**Invoices → Purchases → Buy List → Add Invoice**

## Enter the invoice

1. Select the supplier and destination warehouse.
2. Check the date, invoice number, and document number.
3. Add at least one product.
4. Enter quantity, unit price, discount, and tax.
5. Add deductions and a description if needed.
6. Compare the final total with the supplier's bill.
7. Save the invoice, or save and approve it.

## Line rules

Each line requires an integer quantity of at least 1 and nonnegative unit price, discount, and tax. The discount cannot exceed quantity × unit price.

For each line:

- Gross amount = quantity × unit price.
- Amount after discount = gross amount − discount.
- Tax = amount after discount × tax rate ÷ 100.
- Line total = amount after discount + tax.

The invoice total is the sum of the lines minus overall deductions.

## Approval and accounting effects

After approval, the app normally posts:

- Debit: inventory.
- Debit: purchase tax.
- Credit: purchase discount.
- Purchase deductions to the configured subject.
- Credit: the counterparty for the final invoice amount.

At the same time, goods are received into the warehouse and their weighted-average cost is recalculated.

## Ancillary costs

Record freight, insurance, and similar costs through **Invoices → Purchases → Ancillary Cost List** instead of manually adding them to the purchase price. A product-based cost is allocated to inventory cost. An invoice-based cost creates an expense document but is not allocated to each product unit.

See the [ancillary purchase cost guide](../ancillary-costs/ancillary-costs.en.md).

## Payment

After approval, cash, bank, or cheque payments can be recorded. The purchase payment document normally debits the counterparty and credits cash or bank. Payment and its document are separate from the purchase document.

## Purchase return

To return goods, use **Invoices → Purchases → Return Buy List** and select the source purchase invoice. The returned quantity and amount cannot exceed the remaining returnable balance. See the [purchase return guide](../return-buy/return-buy.en.md).

## Important limits

Editing, deleting, or revoking approval can be blocked by:

- A payment.
- A purchase return.
- A dependent ancillary cost.
- A later sale or approved document whose cost of goods sold depends on it.
- Transfer to the next fiscal year.

## Checks after saving

- [ ] The supplier and destination warehouse are correct.
- [ ] Product, quantity, price, discount, and tax agree with the supplier's bill.
- [ ] The purchase document exists and balances.
- [ ] Warehouse receipt was recorded.
- [ ] The product's weighted-average cost is reasonable.
- [ ] Any ancillary cost was allocated to the correct invoice.
- [ ] The counterparty balance and total payments are correct.
