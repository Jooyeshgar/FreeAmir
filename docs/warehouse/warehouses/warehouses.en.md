<div dir="ltr">

# Warehouses, Stock, and Transfers

**[Back to the warehouse guides](../README.en.md)**

This guide distinguishes product-wide from per-warehouse stock and explains warehouse creation and transfers.

## Two stock levels

Amir tracks two amounts for each product:

| Stock | Meaning | What changes it? |
|---|---|---|
| Product stock | Total product quantity in the active fiscal year | Confirming or unconfirming goods invoices, CSV import, or direct product-stock edits |
| Warehouse stock | Quantity in one warehouse | Invoices selecting that warehouse, warehouse transfers, CSV import, or stock recalculation |

The sum across warehouses should equal product stock. A transfer changes neither product-wide quantity nor its total: it subtracts from the source and adds to the destination.

## Creating and maintaining warehouses

Warehouse name is required, at most 100 characters, and unique in the active fiscal year. Code is optional, at most 30 characters, and unique in that year. Description accepts up to 1,000 characters.

The warehouse list can be filtered by name, code, and exact stock quantity. A warehouse detail page shows products with stock and that warehouse's average cost.

Deletion is not permitted when the warehouse is the only one left in the fiscal year, has any positive stock, or appears as a source or destination in transfer history.

## Selecting a warehouse on an invoice

An invoice containing goods must use a warehouse from the same fiscal year. Once confirmed:

- Purchases, sales returns, sales voids, and opening stock increase warehouse stock.
- Sales and purchase returns decrease it.
- Unconfirming reverses the corresponding change.

When **Sell Beyond Stock** is off, the application checks the selected warehouse's quantity—not the total across the fiscal year. Ten units in the central warehouse do not permit a sale from an empty warehouse.

## Transfers

Under **Warehouse → Transfer Goods**, select the product, source and destination warehouses, quantity, and optional description.

The transfer is recorded only if the product and both warehouses belong to the same fiscal year, the warehouses differ, the quantity is greater than zero, and source stock covers it. **Sell Beyond Stock** does not affect transfers; sufficient source stock is always required.

Transfer unit cost comes from the source warehouse's average cost. The destination recalculates its weighted average after receiving the goods:

```text
destination average =
((previous destination quantity × previous destination average)
 + (transfer quantity × source average))
÷ new destination quantity
```

A transfer creates no accounting document, changes neither product-wide stock nor its moving-average cost, and only changes distribution and average costs among warehouses.

## Transfer history

History records product, source, destination, quantity, unit cost, user, date, and description. Filter by product name/ID, source, destination, and a Jalali date range. The interface does not offer edit or delete for a saved transfer. Correct a mistake with a reverse transfer of the appropriate quantity and describe the correction.

## Product/warehouse quantity differences

1. Open the product detail page and note its stock.
2. Sum its quantities in warehouses or the product report.
3. Check confirmed invoices and their selected warehouses.
4. Review transfer history and the latest imported CSV.
5. Consider direct product-stock edits as well.

**Recalculate Stock** on the product detail page recomputes product and warehouse stock from confirmed or settled invoice lines, ordered by date and number. Purchases, sales returns, voids, and opening stock are receipts; sales and purchase returns are issues.

Recalculation does **not** account for transfer history. For a product that has been transferred, using this button can restore warehouse distribution to what invoices alone imply. Record and review transfer history and correct per-warehouse quantities before running it.

## Purchase, transfer, and sale example

1. Create “Central” and “Store” warehouses.
2. Confirm a purchase of 10 units into Central. Product and Central stock become 10.
3. Transfer 4 units from Central to Store. Product stock stays 10; Central has 6, Store 4.
4. Confirm a sale of 3 units from Store. Product stock becomes 7; Central has 6, Store 1.
5. Check product and warehouse detail pages and the sales invoice document.

## Common errors

| Symptom | Likely cause | Action |
|---|---|---|
| Insufficient source stock | Transfer quantity exceeds stock in that warehouse | Select the right warehouse or first record a receipt |
| Sale rejected despite stock | Stock is in another warehouse | Transfer it or change the invoice's selected warehouse |
| Source/destination rejected | Same warehouse or different fiscal years | Select two different warehouses in the active year |
| Warehouse sum differs from product stock | CSV import, direct edit, or recalculation without transfer history | Reconcile invoice movements, CSV, and transfers together |
| Warehouse cannot be deleted | Only warehouse, positive stock, or transfer history | Move stock and retain warehouses with history |

</div>
