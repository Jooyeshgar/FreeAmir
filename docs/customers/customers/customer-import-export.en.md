<div dir="ltr">

# Importing, Exporting, and Transferring Customer Data

**[Back to the customer guides](../README.en.md)**

## Exporting customers

The customer export creates a CSV with these columns in this order:

```text
name, group_name, subject_code, type, phone, mobile, fax, address,
postal_code, email, ecnmcs_code, personal_code, web_page, responsible,
connector, desc, credit, disc_rate, acc_name_1, acc_no_1, acc_bank_1,
acc_name_2, acc_no_2, acc_bank_2
```

The exported type is `individual`, `legal_entity`, `civil_partnership`, or `foreign_national`. The export includes name, group, subject code, type, contact and identity details, description, credit limit, discount rate, and two bank accounts.

Linked introducer, commission, flags, reason, and email-report preference are not exported. Nor is the customer balance: an auditable balance must be calculated from subject transactions.

## Importing customers

A customer CSV or TXT file can be at most 5 MB. To avoid header errors, export customers from Amir first and edit that file.

- `name` and `group_name` headers are required; column order does not matter.
- English header capitalization is ignored and surrounding whitespace is trimmed.
- Unknown columns and completely empty rows are ignored.
- A same-named group in the active fiscal year is reused; otherwise the group and its subject are created.
- A blank or unknown type becomes **Individual**.
- When updating an existing customer, blank columns do not overwrite previous field values; only nonblank values in the row are updated.

Import does not use the customer form's validation rules. An Amir export is therefore the safest template. Check telephone length/format, addresses, and other data before import.

## Subject-code matching on import

A blank `subject_code` causes an automatic customer code to be created. Unlike the form, import does not complete a short code as a group suffix: a supplied code must be the full customer-subject code under its customer-group subject. Import silently removes all nondigit characters from the code, whereas the form rejects nondigits after removing spaces and `/`. Check the final code after import.

- If the code identifies an existing customer subject, that customer is updated.
- If it identifies an unlinked subject, a new customer is attached to it.
- If the code does not exist, both customer and subject are created using it.
- Import rejects a subject linked to a non-customer record or outside the named group.
- With a blank or new code, import rejects a duplicate customer name within the group.

An error in any row rolls back customers and groups created or changed by previous rows in the same file. The error message identifies the row number.

## Active fiscal year and transfer

Customers, groups, and subjects are visible within the active fiscal year. Switching years shows a different list and balances.

During fiscal-year export/import, the `customers` section includes customer groups, customers, and comments. Correct transfer also depends on subject mappings:

1. Map subjects to the target year.
2. Create customer groups with mapped subjects.
3. Create customers with mapped groups and subjects.
4. Link any customer introducer using previous customer mappings.
5. Create comments with the new customer IDs.

If a group or subject mapping is missing, the related customer is skipped and a warning is written to the application's report. To transfer later relationships completely, include invoices and cheques with their dependencies.

</div>
