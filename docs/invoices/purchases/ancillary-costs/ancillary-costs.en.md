# Ancillary purchase costs

**[Back to the invoice guide](../../README.en.md)**

Use ancillary costs for freight, insurance, customs, loading, and other costs of purchasing goods. The cost is split across products on one purchase invoice and affects inventory cost after approval.

## Access and prerequisites

Open a purchase invoice from **Invoices → Purchases → Buy List** and choose **Create Ancillary Cost** in its ancillary-cost section. Existing costs appear under **Invoices → Purchases → Ancillary Cost List**.

- Check the active company and fiscal year. A closed fiscal year cannot be changed.
- The invoice must contain products. Service-only purchases cannot receive ancillary costs, and service lines are excluded from allocation.
- The purchase must be approved or settled before its ancillary cost can be approved.
- Define the cost counterparty and its accounting subject. This can be a freight company, for example.
- Creating, editing, and approving costs require the corresponding permissions. **save and approve** appears with approval permission.
- For weight allocation, enter **Unit weight (kg)** on each product first. For example, 0.125 means 125 grams per unit. Weight must be nonnegative with at most three decimal places; blank becomes zero.

## Entering and allocating a cost

1. Choose the cost counterparty in **Customer** and select the **Cost Type**.
2. Enter the cost's **VAT (%)** if applicable, from 0 to 100.
3. Choose an **Allocation method**:

| Method | Basis | What to enter |
|---|---|---|
| Manual | Amount assigned to each product | Fill each product's **Amount**; this is the default method |
| Value | Purchase value after discount and before VAT | Enter **Ancillary cost before VAT** |
| Weight | Purchase quantity × unit weight in kilograms | Enter **Ancillary cost before VAT** |

Value uses monetary purchase value. Each line's basis is **quantity × unit price − line discount amount**; purchase VAT is excluded. Each product's share is the cost amount × its basis ÷ total positive basis.

Repeated lines for a product are grouped by product, summing quantities and net purchase values. Fractional purchase quantities are retained.

4. For Manual, enter the line amounts. Automatic methods recalculate when you select the method or change the cost amount; line amounts are read-only. Switch back to Manual to adjust shares directly; the current amounts remain.
5. Review **Amount per unit**, which is the product's cost share divided by its purchase quantity. **Total** is before VAT; **Total with VAT** includes VAT on this ancillary cost. Use the currency shown in the form.
6. Choose **save** to record an unapproved cost, or **save and approve** when you have permission and approval prerequisites are met. If saving succeeds but approval is blocked, check the saved cost's status and the reason shown.

Line amounts must be nonnegative; zero shares are allowed. The cost number is generated automatically. VAT is calculated separately and is excluded from the allocation basis.

## Value and weight example

Assume freight costs 600,000 rials before VAT:

| Product | Purchase quantity | Net purchase value (rials) | Unit weight (kg) | Total weight (kg) |
|---|---|---|---|---|
| A | 10 | 2,000,000 | 2 | 20 |
| B | 20 | 1,000,000 | 0.5 | 10 |

- Value assigns 400,000 rials to A and 200,000 to B.
- Weight gives the same shares here because the weight ratio is also two to one. If B weighs 2 kg per unit instead, its total weight is 40 kg and Weight assigns 200,000 to A and 400,000 to B.
- With 10% VAT on freight, VAT is 60,000 and the payable total is 660,000 rials. The allocated cost remains 600,000 rials.

Automatic shares use two decimal places. Cumulative rounding keeps their sum equal to the entered amount rounded to two decimals. An individual share may differ by 0.01 currency units from independently rounding each share.

## Zero weights and correcting an allocation

- Weight gives zero shares to products with zero weight. A warning beside the product says: **“For this product, weight is not defined, so it will be excluded from allocation.”** The entire cost is split among products with a positive weight basis.
- Value gives no share to a product whose net purchase value is zero or negative.
- If there is no positive basis, all shares become zero. Correct weights or purchase values, or use Manual. Entering a cost amount alone does not guarantee allocation; check **Total** before saving.
- Weights come from current product details. After correcting a weight, reopen the cost form and select Weight again to load the new data and recalculate.
- The allocation method is not saved; final product amounts are saved. Editing opens in Manual with the stored shares. Changing a product weight alone does not change previously saved allocations.

## Approval, accounting effects, and editing

Saving without approval does not create an approved accounting document. Approval:

- Debits each product's inventory subject for its cost share.
- Debits the purchase VAT subject for the cost's VAT, if any.
- Credits the cost counterparty for the total including VAT.
- Updates product average cost and related warehouse inventory cost. Approval does not record payment.

Unapprove an approved cost before editing or deleting it. Unapproving removes its accounting document and reverses its effect on cost calculations; you can then correct shares and approve again.

Later approved or settled invoices for the same products, or later approved ancillary costs, can block status changes. Read the reason shown and review the order of dependent operations. If cost items no longer match purchase items, edit the ancillary cost to synchronize and review its shares.

## Checks after saving

- [ ] The purchase invoice, cost counterparty, and cost type are correct.
- [ ] Weights are in kilograms per unit, and zero shares have been reviewed.
- [ ] Shares sum to the expected pre-VAT cost and the VAT-inclusive total is correct.
- [ ] The cost has the intended approval status in the purchase or ancillary-cost list.
- [ ] The cost details and linked accounting document have been reviewed; debits equal credits.

Related guides: [Products and CSV import/export](../../../warehouse/products/products.en.md) and [Inventory costing](../../../warehouse/products/inventory-costing.en.md).
