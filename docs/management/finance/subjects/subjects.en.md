<div dir="ltr" lang="en">

# Accounts Guide

This guide explains the account hierarchy, creating and editing accounts, and transferring account transactions.

## Before you start

- Confirm the active fiscal year. Accounts are displayed within its scope.
- Open **Management → Finance → Accounts**. Menu visibility and account operations depend on the user's permissions.
- For debits and credits, read [Accounting Basics](../../../general/developer/accounting-basics.en.md). For generated documents, see [Accounting Documents](../../../accounting/document-list/accounting-documents.en.md).

## Account structure in FreeAmir

An account is a ledger subject. One without a parent is a root account. Each can have children, and the hierarchy is not limited to three levels.

The account list does not assign fixed “general,” “subsidiary,” or “detail” labels to levels; parent–child relationships define the hierarchy. Each child level adds a three-digit segment to its parent's code. In a conventional use, the first segment may be treated as general, the second as subsidiary, and the third as detail:

| Practical level | Example code | Meaning |
|---|---:|---|
| General | `101` | Account without a parent |
| Subsidiary | `101001` | Direct child of `101` |
| Detail | `101001001` | Child of `101001` |

Leave the three-digit segment blank to have FreeAmir assign the next number at that level. If entered, it is padded to three digits and appended to the parent code. The full code must be unique within the active fiscal year, and each level has at most 999 available numbers.

### Debit, credit, or both

- **Debit** denotes an account expected to have a debit nature.
- **Credit** denotes an account expected to have a credit nature.
- **Both** denotes an account that can have either balance.

This type does not prevent either a debit or a credit entry in a document; it classifies and controls the structure. A child of a Debit or Credit account inherits that type and cannot choose another. Children of a Both account may be any of the three types. Changing a parent's type updates descendants under the same rule.

The current form displays a type choice for a new child of a Both account, but the creation path may save that child as Both. Check the type shown in the list after creation and correct it if needed.

### Permanent and temporary accounts

Permanent/temporary status is selected on the root and inherited by all descendants. In conventional accounting, permanent accounts such as assets, liabilities, and equity carry balances forward, while temporary income and expense accounts are closed at period end. This choice matters for FreeAmir's classification and fiscal-year operations. Changing it on an account also changes its descendants.

## Create, browse, and edit accounts

1. To create a root, choose **Create Account** at the first level. For a child, first open the parent name and then choose **Create Account**.
2. Enter a **name**; it is required and limited to 60 characters.
3. Choose a **type**. FreeAmir checks compatibility with the parent when saving.
4. For a root, choose **Permanent/Temporary**. A child inherits its parent's value.
5. Enter the three-digit **code** segment or leave it blank for automatic numbering.
6. After saving, check the resulting code in the message and list.

The list shows one account level at a time. Select an account to descend; choose **Back** to return to its parent level. Selecting a code opens that account's transactions.

When editing, you can change name, type, and code, and, for a child, its parent. A parent change rebuilds that account's and all descendants' codes and aligns descendant type and permanent/temporary status with the new parent. Moving an account beneath one of its own descendants is prohibited.

> Changing a code, parent, type, or permanent/temporary status after posting documents changes the structure of historical reports. Back up data and retain ledger and trial-balance reports from before and after the change.

## Links to operational records

Some accounts are linked to operational records. The list marks the record type, and **Edit** opens that record's form.

- Customers and customer groups use linked accounts for counterparty balances and invoice/payment postings.
- Products, product groups, services, and service groups may have revenue, inventory, cost-of-goods-sold, or return accounts.
- Creating a bank account creates and links a same-named account beneath the configured fiscal-year bank account.

An account with children, transactions, or an operational link cannot be deleted. Resolve its dependencies first.

## Transfer account transactions

**Transfer Transactions** appears on an account only when it has no children. Two methods are available:

- **Merge into destination account** moves transactions to an existing account.
- **Transfer to a new child of the destination** creates a same-named child under the selected destination parent and moves the transactions there.

**Transfer source account link** also moves a customer, customer-group, or bank-account link and updates that record's account ID. **Delete source account after transfer** succeeds only if no other dependencies remain.

Only source-account transactions dated from the beginning of the active fiscal year through today are transferred. Source and destination must differ, and the destination cannot be a descendant of the source. Afterward, reconcile the reported count and total against both ledgers.

## Common errors

| Message or condition | Likely cause | Action |
|---|---|---|
| Selected type is incompatible with parent | Parent permits only Debit or only Credit | Choose its type or review the structure |
| Code already exists | Full code is duplicated in the active fiscal year | Enter another three-digit segment or use automatic numbering |
| Account cannot be deleted | It has children, transactions, or an operational link | Find and, if appropriate, transfer or resolve the dependency |

## Related guides

- [Banks](../banks/banks.en.md)
- [Bank accounts](../bank-accounts/bank-accounts.en.md)
- [Accounting documents](../../../accounting/document-list/accounting-documents.en.md)
- [Accounting basics](../../../general/developer/accounting-basics.en.md)
- [Accounting reports](../../../accounting/document-list/accounting-documents.en.md#find-a-document-and-review-transactions)

</div>
