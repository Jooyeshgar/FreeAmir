# Voiding a sales invoice

Voiding fully reverses an approved sale. It is not the same as deleting, returning, or deactivating an invoice.

## How the actions differ

| Action | Result |
|---|---|
| Delete | Removes a record that is eligible for deletion |
| Sales return | Returns all or some lines through a separate return invoice |
| Deactivate | Removes an approved invoice from the active list |
| Void | Creates a new void invoice and reverses the original sale's effects |

## Conditions for voiding

- The original invoice must be an approved sales invoice.
- It must have no payments.
- It must have no approved return.
- Each invoice can be voided only once.
- The void date cannot precede the original sale date.
- The date must fall in the active fiscal year.
- The void invoice number must be a positive integer, unique within the company.
- An invoice transferred to another fiscal year cannot be voided.

## Steps

1. Open the sales invoice.
2. Select **Void** from its actions.
3. Enter the void invoice date and number.
4. Read the warning and confirm the action.
5. Review the new invoice under **Invoices → Sales → Voided Sell**.

## Accounting and inventory effects

The app copies the original sale's lines and amounts into the void invoice, then creates a reverse sales document, records a reverse inventory movement for goods, reverses cost-of-goods-sold effects, and links and approves the void invoice against the original.

The original invoice is not deleted. Keeping both records is essential for an audit trail.

## Checks after voiding

- [ ] The original invoice still exists.
- [ ] The void invoice is linked to the original sale.
- [ ] Lines and amounts match across the two invoices.
- [ ] The reverse document balances.
- [ ] Goods stock was restored correctly.
- [ ] The customer balance was corrected.
- [ ] No incompatible payment or return remains.
