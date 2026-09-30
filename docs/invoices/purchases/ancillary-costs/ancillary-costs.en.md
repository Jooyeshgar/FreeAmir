# Ancillary purchase costs

Use an ancillary cost to record freight, insurance, or another cost related to a purchase. It can be attached only to a purchase invoice.

## Menu path

**Invoices → Purchases → Ancillary Cost List → Add**

## Before you start

- The purchase invoice must be approved.
- Define the cost counterparty and its accounting subject.
- Use a unique document number.
- Choose a date within the active fiscal year.
- For product-based allocation, the invoice's product lines and their costs must be processable.

## Allocation types

After an ancillary cost is saved and approved, one of two allocation types applies:

### Product-based

The cost is allocated among product lines of the selected invoices and changes inventory cost. After approval, cost of goods sold is recalculated from the cost date onward.

### Invoice-based

The cost is recorded at invoice level and an accounting document is created, but it is not allocated to the cost of each product unit.

## Fields

- Title: required, at most 255 characters.
- Date: required, within the fiscal year, and equal to the purchase invoice date.
- Document number: required, positive, and unique within the company.
- Ancillary-cost type: required.
- Cost subject and counterparty: required.
- At least one purchase invoice: required.
- Amount for each invoice: nonnegative.
- Tax for each line: nonnegative and no more than that line's amount.
- Total cost: greater than zero.
- Description: optional, at most 500 characters.

Selected invoices must be approved purchases for the same counterparty.

## Approval lifecycle

The cost starts in pending status. On approval, the app creates its accounting document, records line allocations for product-based costs, and recalculates inventory cost. Revoking approval removes the document and allocations and reverses the recalculation.

## Limitations

A later approved invoice or an incompatible dependency can block editing, deletion, or a status change. Review the chronological order of purchases, sales, returns, and ancillary costs to resolve the error.

## Checks after saving

- [ ] The correct purchase invoice and counterparty were selected.
- [ ] The cost type matches the nature of the expense.
- [ ] Each line's amount and tax are correct.
- [ ] The cost document balances.
- [ ] For product-based allocation, inventory cost was recalculated.
