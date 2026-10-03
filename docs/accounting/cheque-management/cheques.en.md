<div dir="ltr">

# Cheques Guide

This guide covers receivable and payable cheques, their lifecycle events, and reconciliation with documents and reports.

## Before you start

- Check the active fiscal year; cheque lists are scoped to it.
- Use **Accounting → Cheque Management**. Menu visibility and operations depend on permissions.
- Configure valid receivable-notes, notes-in-collection, and payable-notes subjects for the active fiscal year. Counterparties and bank accounts also need accounting subjects.
- For a payable cheque, prepare a [bank account](../../management/finance/bank-accounts/bank-accounts.en.md) and optionally a [chequebook](../../management/finance/chequebooks/chequebooks.en.md).
- See [Accounting Basics](../../developer/accounting-basics.en.md) for debits/credits and [Accounting Documents](../document-list/accounting-documents.en.md) to inspect generated documents.

## Record a cheque

Under **Accounting → Cheque Management**, choose **Receive Cheque** or **Issue Cheque**. Common fields include purpose, optional title, counterparty, amount, issue date, due date, Sayad number, cheque number, serial, and description. Amount must exceed zero; due date cannot precede issue date; Sayad number must have 16 digits and be unique in the active fiscal year.

### Scenarios

| Scenario | Counterparty | Bank account | Chequebook | Initial status | Initial document |
|---|---|---|---|---|---|
| Receivable settlement | Customer/debtor handing over cheque | Not required at entry | Not allowed | Received | Debit receivable notes; credit counterparty |
| Payable settlement | Supplier/creditor receiving cheque | Required | Optional | Issued | Debit counterparty; credit payable notes |
| Receivable guarantee | Guarantor | Not required at entry | Not allowed | Guarantee received | None |
| Payable guarantee | Guarantee beneficiary | Required | Optional | Guarantee paid | None |

A **guarantee** stays outside cheque accounting until **Execute Guarantee**. Execution changes purpose to settlement, creates the relevant document, and changes status to Received or Issued.

### Cheque payment for an invoice

On the invoice page, paying by cheque opens a dialog. Saving creates a cheque and linked payment without duplicating the accounting document. Direction must match invoice type:

- Sales and purchase returns require a receivable cheque.
- Purchases and sales returns require a payable cheque.
- A guarantee cheque does not settle an invoice.
- The cheque counterparty must match the invoice counterparty.
- Amount cannot exceed the invoice's unpaid balance.

See [Invoice Payments](../../invoices/sells/sell/invoice-payments.en.md) and the [invoice guides](../../invoices/README.en.md).

## Statuses and lifecycle events

The cheque page shows only actions permitted in its current status.

| Direction/current status | Action | Next status | Additional data |
|---|---|---|---|
| Receivable, Received | Deposit at bank | Deposited at bank | Destination bank account required |
| Receivable, Received | Endorse/spend | Spent | Recipient supplier or endorsee required |
| Receivable, Received | Return to issuer | Returned to issuer | Optional date/description |
| Receivable, Deposited | Clear | Cleared | Bank account from deposit event |
| Receivable, Deposited | Bounce | Bounced | Optional date/description |
| Receivable, Bounced | Deposit again | Deposited | Destination bank required |
| Receivable, Bounced | Return to issuer | Returned to issuer | Optional date/description |
| Payable, Issued | Clear | Cleared | Bank account on cheque |
| Payable, Issued | Bounce | Bounced | Optional date/description |
| Payable, Issued | Cancel/reclaim | Voided | Optional date/description |
| Payable, Bounced | Cancel/reclaim | Voided | Optional date/description |
| Receivable/payable guarantee | Execute guarantee | Received/Issued | Optional date/description |
| Receivable/payable guarantee | Cancel | Voided | No financial document |

Editing closes after the first lifecycle event. Record a replacement cheque for corrections to preserve history. The UI still offers deletion, which removes that cheque's payments, history, and accounting documents and recalculates settlement status of linked invoices. Use deletion only to correct a recording mistake after full review.

Amir cannot automatically roll an event back to the previous status. Record only an available next event.

## Accounting effects

| Event | Debit | Credit | Separate payment |
|---|---|---|---|
| Receive settlement cheque | Receivable notes | Counterparty | Only if entered from invoice |
| Issue settlement cheque | Counterparty | Payable notes | Only if entered from invoice |
| Deposit receivable cheque | Notes in collection | Receivable notes | None |
| Clear receivable cheque | Bank account | Notes in collection | Created, not linked to invoice |
| Clear payable cheque | Payable notes | Bank account | Created, not linked to invoice |
| Endorse receivable cheque | Receiving counterparty | Receivable notes | Created, not linked to invoice |
| Bounce deposited receivable cheque | Receivable notes | Notes in collection | None |
| Bounce payable cheque | Payable notes | Counterparty | None |
| Return receivable cheque to issuer | Counterparty | Receivable notes | None |
| Cancel issued payable cheque | Payable notes | Counterparty | None |
| Execute receivable guarantee | Receivable notes | Counterparty | None |
| Execute payable guarantee | Counterparty | Payable notes | None |

Cheque lifecycle documents are automatically balanced and confirmed. Cheque history is separate: every creation/event stores previous and new status, user, time, description, and any document/payment IDs. Open each event's document from the cheque page.

## Search and report

Combine cheque number/serial/Sayad/counterparty search with direction, purpose, status, counterparty, amount minimum/maximum, and due-date range. **Cheque Report** carries active filters into the report. It shows count and amount by status.

**Due in the next 30 days** includes only Received, Deposited, and Issued cheques due from today to 30 days ahead. Overdue amount sums those same statuses with due date before today. Guarantees, bounced, spent, returned, cleared, and voided cheques are excluded from these two due-date controls.

For daily review, filter direction and counterparty, narrow due dates, and open the report. Compare overdue total with open cheques and the receivable-notes, notes-in-collection, and payable-notes subjects.

## Receive and clear example

1. Check counterparty and cheque-subject configurations under **Management → Finance → Subjects** in the active year.
2. Create the destination bank and bank account.
3. Under **Accounting → Cheque Management → Receive Cheque**, enter settlement purpose, counterparty, amount, dates, and Sayad number.
4. Check for **Received** status and an initial history entry linked to a document.
5. Choose **Deposit at Bank**, entering bank and date; status becomes **Deposited**.
6. After bank confirmation, record **Clear**; status becomes **Cleared**.
7. Inspect all three documents and counterparty, receivable-notes, notes-in-collection, and bank subjects.
8. Search Sayad and open the report. The cleared cheque should no longer appear among open due cheques.

## Issue example

1. Prepare a bank account and, if used, a chequebook with available leaves.
2. Under **Issue Cheque**, enter settlement purpose, counterparty, amount, dates, Sayad, and bank account.
3. Selecting a chequebook replaces the cheque number with its next leaf; check the next-leaf value afterward.
4. The cheque starts as **Issued**, with counterparty debit and payable-notes credit.
5. After the bank withdrawal, choose **Clear**; it debits payable notes and credits bank.
6. Reconcile counterparty and bank balances, cheque history, and status report with the bank statement.

## Common errors

| Symptom | Likely cause | Action |
|---|---|---|
| Bank account required | Payable cheque or clearing without an account | Select a valid bank account |
| Invalid/no-leaf chequebook | Account mismatch or next leaf past last | Match accounts or create a new chequebook |
| Sayad rejected | Not exactly 16 digits or duplicate in fiscal year | Enter the correct digits without separators |
| Action not allowed in current status | Event conflicts with status or cheque changed concurrently | Refresh and use available buttons only |
| Cannot edit after event | Lifecycle history locks edits | Create a replacement; do not alter history |
| Accounting subject not found | Cheque configuration, counterparty, or bank lacks a valid subject | Correct configurations |

## Reconciliation checklist

- [ ] Receivable-notes balance matches open receivable cheques.
- [ ] Notes-in-collection balance matches cheques deposited at bank.
- [ ] Payable-notes balance matches issued payable cheques.
- [ ] Cleared cheques match bank movements and actual bank dates.
- [ ] Bounced, returned, spent, and voided cheques were reviewed separately.
- [ ] Sayad, cheque number, serial, counterparty, amount, and due date match the original/image.
- [ ] Linked invoice payments match invoice balance and settlement status.
- [ ] Each chequebook's next leaf matches the last physically used leaf.

</div>
