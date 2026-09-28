# Cheque management

English | [فارسی](cheque-management.md)

The cheque module manages receivable, payable, and guarantee cheques. Cheques are scoped to the active
company through `FiscalYearScope`; account sides and bank accounts selected in the web forms must also belong to
that company. Dates are stored as Gregorian dates and entered and displayed through the application's
Jalali date helpers.

Cheque printing and lifecycle reversal are not part of the current implementation. A completed transition
cannot be reverted; an invalid transition, including `revert`, is rejected.

## Data and validation

Direction, purpose, and status are represented by the integer-backed `App\Enums\ChequeType` enum and stored
in regular `SMALLINT` columns rather than database `ENUM` columns. `directions()`, `purposes()`, and
`statuses()` return the valid subset for each field.

A cheque requires an operation type (receivable or payable), purpose, account side, positive amount, issue date,
due date, and a unique 16-digit Sayad number. The due date cannot be before the issue date. The cheque number and
serial are optional. An issued (payable) cheque requires a bank account at registration; a received (receivable)
cheque selects its destination bank account when it is deposited.

A payable cheque may optionally be linked to a chequebook belonging to its selected bank account. Receivable
cheques cannot be linked to chequebooks.

## Receiving and issuing cheques

The cheque list provides separate **Receive cheque** and **Issue cheque** buttons. Both buttons open the same
form and use the same store endpoint; the selected operation type is passed to the form as its `direction` value.
The form intentionally has no visible direction selector, which prevents users from switching workflows while
entering a cheque.

The receive-cheque form contains the shared cheque fields. The issue-cheque form additionally displays the bank
account and optional chequebook fields. When editing an existing cheque, the form preserves its original direction
and does not offer a control for changing it. The form heading and submit button identify the active operation.

The account side must have an accounting subject. The following company-scoped configurations select the
subjects used by cheque postings. Their seeded subjects use these default codes, but administrators may point
the configurations to subjects with different codes:

| Configuration key | Default code | Subject |
|---|---|---|
| `cheque_documents_receivable` | `013001` | Documents receivable |
| `cheque_documents_in_collection` | `014001` | Documents in collection |
| `cheque_documents_payable` | `020001` | Documents payable |

Every bank account used for clearance must have its own accounting subject.

## Chequebooks

Chequebooks are scoped to the active company and belong to a bank account. Each record stores an optional
serial prefix, the first and last leaf numbers, the next leaf number, and an optional description. The next
leaf defaults to the first leaf.

The chequebook pages list each book's leaf range, next leaf, and associated cheque count. A chequebook's bank
account cannot be changed after a cheque has been linked to it. Deleting a chequebook preserves its cheques and
sets their `chequebook_id` to `null`.

Selecting a chequebook on a payable-cheque form automatically assigns its current `next_leaf` as the cheque
number and advances `next_leaf` atomically. This overrides a cheque number entered in the form. After the last
leaf is assigned, the next-leaf value becomes one greater than the configured range and further registrations
from that chequebook are rejected until its range or next-leaf value is updated.

## Lifecycle

Only the actions returned by `Cheque::availableActions()` are accepted.

| Direction/purpose | Current status | Available action | Resulting status |
|---|---|---|---|
| Receivable settlement | `registered` | `deposit` | `deposited` |
| Receivable settlement | `registered` | `endorse` | `endorsed` |
| Receivable settlement | `registered` | `return` | `returned` |
| Receivable settlement | `deposited` | `clear` | `cleared` |
| Receivable settlement | `deposited` | `bounce` | `bounced` |
| Receivable settlement | `bounced` | `deposit` | `deposited` |
| Receivable settlement | `bounced` | `return` | `returned` |
| Payable settlement | `issued` | `clear` | `cleared` |
| Payable settlement | `issued` | `bounce` | `bounced` |
| Payable settlement | `issued` | `cancel` | `cancelled` |
| Payable settlement | `bounced` | `cancel` | `cancelled` |
| Received guarantee | `guarantee_received` | `execute` | `registered` |
| Given guarantee | `guarantee_given` | `execute` | `issued` |
| Either guarantee | guarantee status | `cancel` | `cancelled` |

Executing a guarantee changes its purpose from `guarantee` to `settlement`, creates the initial settlement
document, and moves it into the corresponding receivable or payable lifecycle. Registering or cancelling a
guarantee does not create an accounting document.

Each registration and transition adds a `ChequeHistory` row with the previous and resulting statuses, user,
description, and any related accounting document or payment. Updates and transitions lock the cheque row and
compare its `version` value to reject concurrent changes.

Master data can be edited only before the first lifecycle transition. Editing rebuilds the initial accounting
document and any invoice-linked payment in one database transaction. Deleting a cheque is allowed at any
status and transactionally deletes its histories, cheque-linked payments, and all documents generated by the
cheque; linked invoice statuses are then recalculated.

## Accounting entries

FreeAmir stores debit transaction values as negative numbers and credit values as positive numbers. All cheque
documents are created, balanced, and approved through `DocumentService`.

| Event | Debit | Credit |
|---|---|---|
| Receive settlement cheque | Configured documents receivable | Original account side |
| Deposit received cheque | Configured documents in collection | Configured documents receivable |
| Clear received cheque | Bank account subject | Configured documents in collection |
| Bounce deposited cheque | Configured documents receivable | Configured documents in collection |
| Endorse received cheque | Endorsee subject | Configured documents receivable |
| Return received cheque | Original account side | Configured documents receivable |
| Issue settlement cheque | Original account side | Configured documents payable |
| Clear issued cheque | Configured documents payable | Bank account subject |
| Bounce issued cheque | Configured documents payable | Original account side |
| Cancel issued cheque | Configured documents payable | Original account side |
| Execute received guarantee | Configured documents receivable | Original account side |
| Execute given guarantee | Original account side | Configured documents payable |

Cancelling an already bounced payable cheque creates no additional document because the bounce already
reversed the issue entry. Clearance and endorsement create a `Payment` linked through `payments.cheque_id`;
deposit, bounce, return, cancellation, and guarantee execution do not.

## Invoice settlement

An approved or partially paid invoice can be settled from its details page by registering a settlement cheque:

- Sales and purchase-return invoices create a receivable cheque.
- Purchase and sales-return invoices create a payable cheque.
- The cheque account side is the invoice account side, and a guarantee cheque cannot settle an invoice.
- The amount cannot exceed the remaining invoice balance, and the cheque issue date cannot precede the
  invoice date.

The registration or issue document is reused by a `Payment` linked to both the invoice and the cheque, so no
duplicate accounting document is created. Clearing the cheque later remains a separate lifecycle posting.

Removing a cheque payment from an invoice deletes the cheque, its payments, complete history, and every
accounting document and transaction generated by that cheque. The invoice status is recalculated after cleanup.
Deleting an invoice also applies this cleanup to every cheque used to settle it. Non-cheque invoice payments
are deleted by the invoice-payment cascade. Deleting a cheque directly applies the same cleanup and
recalculates any affected invoice.

## Lists, reports, and permissions

The cheque list supports search by cheque number, serial, Sayad number, or account side, plus direction, purpose,
status, account side, amount-range, and due-date filters. The report summarizes count and amount by status, lists
open cheques due in the next 30 days, and totals overdue `registered`, `deposited`, and `issued` cheques. An account
side's details page lists cheques where that account side is either the original account side or the endorsee.

Cheque pages use the `cheques.*` CRUD, `cheques.report`, and `cheques.transition` permissions. Chequebook pages
use the `chequebooks.*` CRUD permissions. The invoice payment flow uses the separate
`invoices.payments.store-cheque` permission.
