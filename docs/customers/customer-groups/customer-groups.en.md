<div dir="ltr">

# Customer Groups

**[Back to the customer guides](../README.en.md)**

## Groups, customers, and subjects

| Concept | Purpose |
|---|---|
| Customer group | A category such as wholesale or retail |
| Customer-group subject | Account created beneath the configured customer subject; the group's combined balance can be followed here |
| Customer | Identity, contact, banking, and relationship details for a counterparty |
| Customer subject | Account beneath the group subject; invoices, receipts, and other postings appear in its balance |

A customer or group does not replace an accounting subject. The actual balance is the sum of that subject's transactions.

## Prerequisites and setup

Before creating the first group, check the active fiscal year. Under **Management → System → Configurations**, connect **Customers** to an appropriate subject. Viewing and changing this configuration require `configs.index` and `configs.edit`, respectively.

1. Configure Customers in the active fiscal year.
2. Create a group under **Customers → Customer Groups**. Its subject is created beneath the configured customer subject.
3. Create a customer in that group. The customer subject is created beneath the group subject unless you supply the code of another unlinked subject.
4. Check the subject code and name on the customer's detail page.
5. Then record invoices, receipts, cheques, and ratings.

## Group form

| Field | Current rule |
|---|---|
| Name | Required text, at most 20 characters, limited to letters, digits, and spaces |
| Description | Optional text, at most 150 characters |

Creating a group creates a same-named subject beneath the configured customer subject. Renaming a group also renames its subject. The group detail page shows member count, group-subject balance, sales, returns, net sales, top customers, and recent invoices.

## Deletion warning

Deleting a customer group is not a simple removal. It deletes all customers in the group, all their comments and subjects, and the group and its subject. Deleting each customer also deletes its dependent invoices, cheques, and cheque history. If a customer was only a cheque transferee, that transfer-recipient field is cleared.

Unlike deleting one customer, group deletion does **not** check whether its customer subjects have transactions. Deleting those subjects deletes the group's customer transactions too. A group deletion can therefore destroy sales, cheque, and accounting history. Do not delete a group containing customers or ledger activity. Back up first; renaming or retaining the group is safer.

</div>
