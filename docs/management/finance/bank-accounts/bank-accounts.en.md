<div dir="ltr" lang="en">

# Bank Accounts Guide

This guide covers creating, validating, editing, and deleting bank accounts in the current version of FreeAmir.

## Before you start

- Confirm the active fiscal year. Bank accounts are shown within its scope.
- Open **Management → Finance → Bank Accounts**. Menu visibility and operations depend on the user's permissions.
- Before creating an account, create its [bank](../banks/banks.en.md) and check the relevant configuration in the active fiscal year.

## Create a bank account

Choose **Create Bank Account** under **Management → Finance → Bank Accounts**. The fields are:

| Field | Current rule |
|---|---|
| Name | Required; at most 20 characters |
| Account number | Required; at most 40 characters and unique within the active fiscal year |
| Type | Required; current, savings, interest-free loan, or other |
| Owner | Optional |
| IBAN | Optional; if entered, it must be valid and unique within the fiscal year |
| Bank | Required |
| Branch, phone, address, and website | Optional; the website must be a valid URL |
| Description | Optional; at most 150 characters |

FreeAmir removes spaces from an entered IBAN and uppercases its letters. It supports 26-character Iranian, 22-character German and UK, and 27-character Greek IBANs. Validation first checks the country code (which must be two characters) and the length, then runs the `mod-97` check. For example, `IR` alone, or a number that merely looks valid, is not accepted.

## Link between bank account and accounting account

Creating a bank account creates an accounting account with the same name under the fiscal year's configured bank account and links it to the bank account. Renaming the bank account also renames that accounting account.

Deletion attempts to remove both the bank account and its accounting account. It is rejected if that accounting account has transactions or cheque books or cheques depend on the bank account. To preserve history, do not delete an account that has been used; treat it as inactive in your internal process, because the current version has no active/inactive field.

## Common errors

| Message or condition | Likely cause | Action |
|---|---|---|
| Invalid IBAN | Country, length, structure, or `mod-97` check fails | Remove spaces and check the official bank number again |
| Bank account cannot be deleted | Its accounting account has transactions, or cheque books and cheques depend on it | Check the dependencies and retain the used account |

## Related guides

- [Banks](../banks/banks.en.md)
- [Accounts](../subjects/subjects.en.md)
- [Cheque books](../chequebooks/chequebooks.en.md)
- [Cheques](../../../accounting/cheque-management/cheques.en.md)

</div>
