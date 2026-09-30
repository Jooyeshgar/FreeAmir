<div dir="ltr" lang="en">

# Customers and Customer Relationships

**[Back to the documentation index](../README.en.md)**

This is the starting point for the Customers guides. Each guide covers an actual **Customers** menu section so an accountant can find the relevant workflow.

## Guides in this section

| Guide | Topic |
|---|---|
| [Customer Relationship Dashboard](dashboard/dashboard.en.md) | Sales, receipts, receivables, aging analysis, and differences between filters |
| [Customers](customers/customers.en.md) | Customer details and types, account codes, moving groups, balances, invoices, and cheques |
| [Customer groups](customer-groups/customer-groups.en.md) | Base-account configuration; differences between groups and accounts; creating and deleting groups |
| [Customer comments](customers/customer-comments.en.md) | Adding, rating, editing, and deleting comments |
| [Customer import, export, and transfer](customers/customer-import-export.en.md) | CSV files, account matching, and transfer between fiscal years |

## Application paths and access

The **Customers** menu has three items:

1. **Customer Relationship Dashboard**;
2. **Customers**;
3. **Customer Groups**.

The Invoices menu also includes **Add Customer**. Comments can be added from the customer list or a customer's detail page, while all comments have a separate page.

| Task | Required permission |
|---|---|
| View the Customer Relationship Dashboard | `crm.dashboard` |
| List, create, store, view, edit, update, and delete customers | Respectively `customers.index`, `customers.create`, `customers.store`, `customers.show`, `customers.edit`, `customers.update`, and `customers.destroy` |
| Export customers | `customers.export` |
| View the import form and submit a file | `customers.import` and `customers.import.store` |
| List, create, store, view, edit, update, and delete customer groups | Corresponding `customer-groups.*` permissions |
| List, create, store, edit, update, and delete comments | Corresponding `comments.*` permissions |
| Open account transactions from a customer or group page | `transactions.index`; the group-balance link in group detail also depends on `reports.ledger` |
| View cheque details from a customer detail page | `cheques.show` |

A visible button does not grant permission to access its route. For example, viewing a customer does not authorize adding a comment without `comments.create`.

## Common errors

| Symptom | Likely cause | Accountant action |
|---|---|---|
| Customer base account is not configured | The base customers account is unset in the active fiscal year | Correct **System → Configurations**, then review the group |
| Invalid group | The group does not exist or the submitted ID is invalid | Create and reselect it in the active year |
| Duplicate name | The same name exists in the same group | Edit the existing record or use a distinct, accurate name |
| Duplicate phone | Another customer has this phone number | Search for the existing customer; uniqueness does not apply to mobile numbers |
| Nonnumeric account code | Characters other than digits remain after spaces and `/` are removed | Enter digits only |
| Code outside the group | Full code length or prefix does not match the group's account | Check the group code or leave the code blank |
| Code belongs to another record | The account is already linked to a customer | Choose another account; do not change the previous link without review |
| Customer cannot be deleted | Its account has transactions | Keep the customer and correct details if necessary |
| File cannot be imported | File type/size, headers, group, account code, or a row is invalid | Read the error and line number; start from an application export and retry |
| Balance differs from credit limit | These have different sources and meanings | Check balance in the account ledger and credit limit in customer details |
| Statistics differ between pages | Period, invoice type, or status differs between calculations | Use the filter-difference table in the [dashboard guide](dashboard/dashboard.en.md) |

## Accountant's check scenario

1. Select the active fiscal year and set the customers configuration.
2. Create a “Wholesale Customers” group and open its group account code from the group list.
3. Register a customer with the correct type and identity details, leaving account code blank.
4. Open the customer detail page and check name, group, code, and initial zero balance.
5. Issue a sales invoice and approve it after review. To appear in the dashboard's sales-invoice section, it must have an accepted status.
6. Check the invoice document and its effect on the customer's account in the ledger.
7. Record a receipt and reconcile the positive customer-account transaction against the cash or bank account on the other side of the document.
8. Check the new balance on the customer page and in the ledger. Do not confuse the credit limit with the balance.
9. Review cheques originally belonging to and cheques transferred to the customer on their detail page.
10. Record the result of a call or collection follow-up as a customer comment and rating.
11. Open the Customer Relationship Dashboard and inspect monthly sales, monthly payments, receivables, aging, and top customers.
12. If a figure differs from expectations, reconcile fiscal year, invoice status, sales returns, and positive or negative account transactions before making manual corrections.

</div>
