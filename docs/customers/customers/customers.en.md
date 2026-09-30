<div dir="ltr" lang="en">

# Customers

**[Back to the Customers guide](../README.en.md)**

This guide covers customer records and types, customer accounts, moving a customer between groups, deletion, and balance checks.

## Create and edit a customer

Open **Customers → Customers → Create Customer**. Name, customer group, and customer type are required by the registration request; the other fields are optional.

### Identity

| Field | Form and validation rule |
|---|---|
| Customer group | Required |
| Name | Required text, at most 100 characters; letters, digits, and spaces only; unique within the selected group |
| Account code | Optional; after spaces and `/` are removed, digits only, at most 20 characters |
| National ID | Optional text, at most 15 characters; current request does not validate digit-only format, national length, or check digit |
| Economic code | Optional text, at most 20 characters; current request has no dedicated structure check |
| Type | Required; one of four defined values |

### Contact

| Field | Form and validation rule |
|---|---|
| Mobile | Optional; 11 digits starting with `09`; uniqueness is not checked |
| Phone | Optional; digits, at most 15 |
| Fax | Optional text; at most 15 characters according to the database |
| Email | Optional; valid email format, at most 64 characters |
| Website | Optional; at most 50 characters and limited to letters, digits, and spaces; URL validation is not performed, and characters such as `.` and `/` do not fit the current pattern |
| Postal code | Optional text, at most 15 characters; digit-only format is not checked |
| Address | Optional, at most 100 characters and limited to letters, digits, and spaces |

### Financial and other details

The form accepts two bank accounts. For each, account-holder and bank names are optional and at most 50 characters. The account number is optional and at most 30 characters. All three fields are limited to letters, digits, and spaces. This form does not validate IBAN, official bank name, or account-number authenticity.

| Other field | Rule |
|---|---|
| Connector | Optional text, at most 50 characters |
| Responsible person | Optional text, at most 50 characters |
| Description | Optional, at most 150 characters, limited to letters, digits, and spaces |

### Customer types

| Type | Record purpose |
|---|---|
| Individual | Personal information |
| Legal entity | Company or institution information |
| Civil partnership | Partnership information |
| Foreign national | Foreign individual's information |

Type is an identity classification. Validation of national ID, economic code, and other fields does not vary by type; type also does not change accounting postings or balance calculation. Accountants should enter identity details matching the counterparty's documents.

## Account code and existing-account link

Customer account code has three cases:

1. **Blank:** The application generates the next code beneath the selected customer-group account.
2. **Short, up to three digits:** It prefixes the group code and left-pads the entered part to three digits. For example, `7` becomes `007`.
3. **Full:** It must have three more digits than the group account code and begin with that group code.

If a full code identifies an existing account not linked to a customer, the customer is linked to it and the previous account name is preserved at creation. Editing is allowed if the account already belongs to the same customer. An account linked to another customer or record is rejected, as is a code outside the selected group's account.

Renaming a customer synchronizes its account name. Changing its customer group moves the same account beneath the new group account and regenerates its code. If a different free account code is supplied while editing, the link is removed from the old account and transferred to the new one. Check the ledger before moving groups: moving the account changes where its balance appears in the account hierarchy and reports.

### Delete one customer

If the customer's account has transactions, deletion is rejected with “A customer with transactions cannot be deleted.” This check only examines account transactions. Without them, deletion is allowed and removes comments, the customer, and their account. A zero balance alone is not enough assurance: also inspect draft invoices, cheques, comments, and the ledger.

## Balance, credit, and the ledger

The customer detail page shows two figures with different meanings:

- **Accounting balance** sums the customer-account transactions and links to that account's ledger.
- **Credit** or credit limit is a value in customer details. It is not calculated from transactions and does not represent actual debt.

A negative customer-account balance means the customer owes the business; a positive balance means the customer is a creditor. The list's **Debtor Customers** and **Creditor Customers** filters use the same sign convention.

To check a balance:

1. Confirm the active fiscal year.
2. Open the customer detail page.
3. Choose the account code or accounting balance.
4. Check debit and credit movements, dates, descriptions, and each row's document in the ledger.
5. Reconcile invoices and receipts with documents in that ledger.

Renaming a customer does not change the balance. Changing groups places the account beneath the new group, changing the totals of both groups and structure-based reports, even though the account's own transactions remain.

## Customer detail page

It shows name, group, type, and account code; accounting balance, recorded credit limit, and total invoice count; contact and identity details, responsible person, connector, and description; two bank accounts; recent comments; cheques for which the customer is the original party or transferee; and all customer invoices with type and status.

Invoices and cheques are paginated separately. The order count is the total number of related invoices without a type or status filter. Do not equate it with approved sales on the dashboard.

## Related guides

- [Customer groups](../customer-groups/customer-groups.en.md)
- [Customer comments](customer-comments.en.md)
- [Customer import, export, and transfer](customer-import-export.en.md)
- [Customer Relationship Dashboard](../dashboard/dashboard.en.md)
- [Accounts](../../management/finance/subjects/subjects.en.md)

</div>
