# Service purchase invoice guide

A service purchase is technically a **purchase invoice**, but its form accepts services only.

## Purpose

Use it to record services received, such as consulting, repairs, rented services, or contracting services.

## Before you start

- Select the correct company and fiscal year.
- Define the service provider and its accounting subject.
- Define the service and its expense or purchase subject.
- Check the service's tax rate.
- Configure purchase-tax, purchase-discount, and deduction subjects.

## Menu path

**Invoices → Purchases → Buy Service → Add Invoice**

## Enter the invoice

1. Select the service provider.
2. Check the date, invoice number, and document number.
3. Add at least one service.
4. Enter the unit price, discount, and tax.
5. Add deductions and a description if needed.
6. Compare the total with the provider's bill.
7. Save the invoice, or save and approve it.

## Service-specific rules

- Only a service can be selected.
- Service quantity is treated as 1 during internal processing.
- Discount cannot exceed the service's gross amount.
- On creation, tax is entered as a percentage rate and converted to an amount.
- On editing, the stored tax is displayed as an amount.

## Approval and accounting effects

The service purchase document normally posts:

- Debit: service or expense subject.
- Debit: purchase tax.
- Credit: purchase discount.
- Deductions to the configured subject.
- Credit: the counterparty for the final amount.

## Payment and return

After approval, cash, bank, or cheque payments can be recorded. To return a service purchase, use **Invoices → Purchases → Purchase Returns** and select the source service invoice.

## Checks after saving

- [ ] The provider and service are correct.
- [ ] The service subject matches the expense or purchase nature.
- [ ] Discount, tax, deductions, and final total are correct.
- [ ] The accounting document balances.
- [ ] The counterparty balance and payments are correct.
