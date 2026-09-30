# Sales invoice guide

This guide covers the **sales invoice** workflow only. For rules common to all invoices, see the [Invoices guide](../../README.en.md).

## Purpose

A sales invoice is used to sell goods, services, or both to a customer. Approving it:

- Creates the sales accounting document.
- Removes goods from inventory.
- Records the cost of goods sold.
- Creates the customer's receivable balance.
- Makes payment recording and submission to Moadian possible.

Saving an invoice alone does not finalize any of these steps. Approval, payment, and Moadian submission are separate actions.

## Before you start

- Select the correct company and fiscal year.
- Define the customer, its customer group, and its accounting subject.
- Define each product or service and its related subject.
- Check the product or service tax rate.
- For goods, check the warehouse and available stock.
- Configure the subjects for sales, sales discounts, sales tax, deductions, inventory, and cost of goods sold.

## Menu path

**Invoices → Sales → Add Invoice**

## Enter the invoice

1. Select the customer.
2. Select a warehouse; the form requires one even for a services-only invoice.
3. Check the date and invoice number, and the document number if approving immediately.
4. Select a product or service on each line.
5. Enter the quantity, unit price, discount, and tax.
6. Add overall deductions and a description if needed.
7. Check line totals and the final total.
8. Select **Save** to review later or **Save and Approve** to post the financial effects.

## Line rules

- At least one line is required.
- Select either a product or a service on each line, not both.
- Quantity must be an integer of at least 1.
- Unit price and tax cannot be negative.
- Discount cannot exceed the line's gross amount.

For each line:

- Gross amount = quantity × unit price.
- Amount after discount = gross amount − discount.
- Tax = amount after discount × tax rate ÷ 100.
- Line total = amount after discount + tax.

The final invoice total is the sum of the lines minus overall deductions.

> On the edit form, stored tax is displayed as a **tax amount**, not a percentage rate. Check this column again before saving changes.

## Approval and accounting document

On approval, the app normally posts:

- Debit: the customer's account for the final invoice amount.
- Debit: sales discount, if any.
- Credit: goods or services sales revenue.
- Credit: sales tax.
- Deductions to the configured sales subject.

For product lines it also posts cost of goods sold separately:

- Debit: cost of goods sold.
- Credit: inventory.

The document is saved only when total debits equal total credits.

## Payment

After approval, you can record cash, bank, or cheque payments. Paying less than the balance changes the status to **partially settled**; completing payment changes it to **settled**.

The sales payment document is separate from the invoice document:

- Debit: cash or bank.
- Credit: the customer's account.

See the [invoice payments guide](invoice-payments.en.md) for details.

## Submit to Moadian

Moadian submission is allowed only for sales invoices with approved, approved-inactive, partially settled, or settled status. An existing invoice or accounting document does not mean submission succeeded. Check the result under **Invoices → Moadian Histories**.

## Returns and voiding

- To return all or part of a sale, see the [invoice returns guide](../return-sell/return-sell.en.md).
- To fully reverse an eligible sale, see the [void sales invoice guide](../void/void.en.md).

A return, void, deletion, and deactivation are different actions and must not be used interchangeably.

## Important limits

Editing, deleting, or revoking approval may be blocked if:

- A payment has been recorded.
- The invoice has been returned.
- The invoice has been transferred to the next fiscal year.
- A later approved invoice or document depends on its stock and cost calculation.
- The invoice has been voided.

Some status changes return an `HTTP 409` conflict warning. Read it and confirm the action only when you understand the impact.

## Checks after saving

- [ ] The customer, date, invoice number, and warehouse are correct.
- [ ] Products, services, quantities, prices, discounts, and tax are correct.
- [ ] The sales document exists and balances.
- [ ] The customer balance is correct.
- [ ] Product lines have an inventory outflow.
- [ ] Cost of goods sold was posted.
- [ ] Payments and the remaining invoice balance agree.
- [ ] Moadian submission succeeded if required.
