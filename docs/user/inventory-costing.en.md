# Inventory Costing and COGS in Amir

**[نسخه فارسی](inventory-costing.md)**  
**[Back to user guide](README.en.md)**

This guide is for users who want to understand how cost of goods sold, or COGS, is calculated and which inventory costing method Amir uses.

## What Is Inventory Cost?

Inventory cost is the total cost paid to make goods ready for sale. It usually includes:

- Purchase price
- Freight to the warehouse or business location
- Transportation insurance
- Customs duties and import charges
- Direct loading, unloading, and preparation costs
- Non-refundable taxes or charges

Administrative costs, sales costs, warehousing after goods are ready for sale, advertising, and general company overhead are not part of inventory cost. They are usually recorded as period expenses.

## Perpetual Inventory System

Amir manages inventory with a perpetual inventory approach. In a perpetual system, every purchase and sale immediately affects inventory quantity and inventory value.

When goods are sold, two main accounting entries are involved:

```text
Accounts receivable / Cash and bank ........ Debit
    Sales revenue .......................... Credit

Cost of goods sold ......................... Debit
    Inventory .............................. Credit
```

The first entry records sales revenue. The second moves the value of goods leaving inventory into cost of goods sold.

## Common Cost Flow Methods

When an item is purchased several times at different prices, the software must decide which cost rate to use when the item is sold. Common methods are:

| Method | Simple meaning |
|---|---|
| FIFO | Assumes older units are sold first; sales cost comes from older purchases. |
| LIFO | Assumes newer units are sold first; sales cost comes from newer purchases. |
| Periodic weighted average | Calculates one average for the period at period end. |
| Moving weighted average | Updates the average unit cost after every purchase. |

## Method Used in Amir

Amir currently uses only the **moving weighted average** method. FIFO, LIFO, and periodic average are not implemented in Amir right now.

With this method, each new purchase changes the average unit cost. When a sale is recorded, COGS is calculated from the average cost at that moment.

Simple formula:

```text
New average =
((Previous quantity × Previous average) + (New purchase quantity × New purchase unit cost))
÷
(Previous quantity + New purchase quantity)
```

If a direct ancillary purchase cost is recorded, that cost increases inventory value and raises the average unit cost.

## Numeric Example

Suppose item A is purchased like this:

- First purchase: 100 units × 1,000,000 IRR = 100,000,000 IRR
- Second purchase: 200 units × 1,200,000 IRR = 240,000,000 IRR

New average:

```text
(100,000,000 + 240,000,000) ÷ 300 = 1,133,333 IRR
```

If 30,000,000 IRR of direct freight cost is later recorded for this stock:

```text
(340,000,000 + 30,000,000) ÷ 300 = 1,233,333 IRR
```

If 50 units are sold:

```text
COGS = 50 × 1,233,333 = 61,666,650 IRR
```

After the sale:

- Quantity on hand: 250 units
- Approximate inventory value: 250 × 1,233,333 IRR
- Cost recorded in COGS: 61,666,650 IRR

## Practical Notes

- Before recording sales, enter purchases and direct item costs as completely as possible.
- If a direct purchase cost becomes known later, record it as a direct cost related to the purchase so the item average can be corrected.
- Gross profit reports depend on the sales price and the item average cost at the time of sale.
- If an item's stock is negative or incomplete, COGS is not reliable. Review inventory before reporting.
