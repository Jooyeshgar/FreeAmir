# Invoice payments

Recording a payment, creating its accounting document, and reaching settled status are related but separate. Creating an invoice does not pay it; a recorded payment does not necessarily create a cash or bank accounting movement until its payment document is created.

## Invoices that can be paid

Payments are processed only for approved, partially settled, or settled invoices. A new payment is not accepted for a fully settled invoice.

The payment amount must be numeric, greater than zero, and no more than the unpaid balance. The payment date is required, Jalali, and within the active fiscal year.

## Payment methods

### Cash

Enter the amount, date, and cash account or subject.

### Bank

Enter the amount, date, and bank-account subject.

### Cheque

The following are required:

- Payment amount and date.
- A 16-digit Sayad number.
- Due date.
- Bank name, at most 100 characters.
- Branch, at most 200 characters.

The issuer name is optional and at most 255 characters. The payment description is optional and at most 500 characters.

## Steps

1. Open the invoice.
2. Select **Record Payment**.
3. Enter amount and date.
4. Select the method and complete its additional fields.
5. Save the payment.
6. If needed, run **Create Payment Document** for that payment.

## Settlement status

The app compares total payments with the final invoice amount:

- Less than the invoice amount: **partially settled**.
- Equal to the invoice amount: **settled**.

Deleting a payment recalculates the status from the remaining payments.

## Payment document

No more than one document is created for each payment.

- Sales: debit cash or bank and credit the counterparty.
- Purchases: debit the counterparty and credit cash or bank.

The payment document is separate from the invoice document. Check both individually.

## Accountant's checklist

- [ ] The amount does not exceed the balance.
- [ ] The date is within the active fiscal year.
- [ ] Cash, bank, or cheque details are correct.
- [ ] Settlement status agrees with total payments.
- [ ] The payment document exists and balances.
- [ ] The customer or supplier balance is correct.
