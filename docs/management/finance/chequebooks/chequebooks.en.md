<div dir="ltr" lang="en">

# Cheque Books Guide

This guide explains how to create and manage payable-cheque books in the current version of FreeAmir.

## Before you start

- Confirm the active fiscal year. Cheque books are displayed within its scope.
- The menu path is **Management → Finance → Cheque Books**. Menu visibility and related operations depend on the user's permissions.
- A cheque book is used only for payable cheques and must belong to a [bank account](../bank-accounts/bank-accounts.en.md).

## Create a cheque book

1. Choose **Create Cheque Book** and select the bank account.
2. If needed, enter a **serial prefix** of at most 50 characters.
3. Enter the **first leaf** and **last leaf** numbers. The last cannot be lower than the first.
4. Enter the **next leaf**, or leave it empty to default to the first leaf. It may be at most one number beyond the last leaf; that represents an exhausted cheque book.
5. Enter an optional description of at most 1,000 characters and save.

On the cheque-book list, you can filter by serial prefix, bank account, and **Available Leaves** or **Exhausted** status to find a book. A book has available leaves when `next leaf ≤ last leaf`.

## Allocate a leaf to a payable cheque

When you register a payable cheque using a cheque book, FreeAmir assigns the next leaf as the cheque number and increments the next-leaf value by one in a single transaction. Choosing a cheque book is optional; without one, you enter the cheque number yourself. The book must belong to the selected bank account, and an exhausted book cannot allocate another leaf.

## Edit or delete a cheque book

Once a cheque book has related cheques, its bank account cannot be changed. The current version still allows editing its range and next leaf after leaves have been used. Change these values only as a controlled correction: FreeAmir does not independently invalidate or re-release the allocation history for each leaf. Deleting the cheque book does not delete cheques; it only clears their link to the book.

## Common error

| Message or condition | Likely cause | Action |
|---|---|---|
| Invalid cheque book or no leaves remain | The book's account does not match, or its next leaf is beyond its last | Match the bank account or create a new cheque book |

## Related guides

- [Bank accounts](../bank-accounts/bank-accounts.en.md)
- [Cheques](../../../accounting/cheque-management/cheques.en.md)

</div>
