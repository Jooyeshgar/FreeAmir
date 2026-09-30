<div dir="ltr">

# Warehouse Dashboard and Product Reports

**[Back to the warehouse guides](../README.en.md)**

This guide explains warehouse dashboard metrics, product-list filters, and stock report columns for accountants.

## Dashboard metrics

Stock cards use current product quantity and cost. Movement charts include only goods lines from confirmed or settled invoices in the selected period.

The **Inbound / Outbound Trend** chart counts purchases, sales returns, and voids as inbound; sales and purchase returns as outbound. Opening stock and inter-warehouse transfers are excluded from this dashboard movement chart.

## Dashboard filters

| Filter | Current behavior |
|---|---|
| Last 30 days | From the start of the day 30 days ago through the end of today |
| Last quarter | From the start of the day 90 days ago through the end of today |
| All time | Currently means from 365 days ago through today |
| Group | Restricts products, values, movement, and status to that group |
| Below reorder point | Positive alert threshold and stock at or below that threshold |
| Dormant | Has stock but no movement, or last movement more than 60 days ago |
| Normal | Neither below reorder point nor dormant |

The status filter populates the status table; other charts and tables retain their own definitions.

## Measures

- **Total inventory value** sums `product quantity × overall product cost` for all goods in the active fiscal year.
- **Product count** counts distinct product types.
- **Below reorder point** counts products with a positive alert threshold and stock no greater than it.
- **Dormant** means products with stock but no movement in the last 60 days.
- **Turnover rate** is total cost of goods sold in the selected period divided by current inventory value.
- **Days in stock** is days in the selected period divided by turnover rate; if the rate is zero, zero is displayed.

Top-seller net sales means sales less sales returns. Revenue amount also excludes line tax.

## Charts and tables

The dashboard shows inventory value, turnover, and product count by group. Monthly inbound/outbound trends are grouped by Jalali month. The group breakdown chart shows at most the five groups with the highest monetary inventory value.

Below-reorder, dormant, top-selling, and selected-status tables show at most 15 rows. No movement in the period does not mean zero stock: check current stock and last movement date separately.

## Product list

Filter by name, code, part of group name, minimum stock, and **Needs Reorder**. The minimum-stock filter includes the entered amount itself. **Needs Reorder** returns only products with a positive threshold and stock at or below it.

The list shows confirmed stock separately from net unconfirmed purchases and sales. The red number beside stock is **not** a stock forecast; it is only unconfirmed purchases less unconfirmed sales.

## Warehouse report

Product name is always included. Optional columns include:

- Fiscal-year-to-date inbound and outbound quantities
- Stock, product group, and code
- Sale price, cost, and last product cost
- Sales profit and amounts for revenue, cost-of-goods-sold, inventory, and sales-return subjects
- Product quantity in each warehouse

The report spans the start of the active fiscal year through today and counts only invoices with **Confirmed** status. Opening-stock invoices are not counted. Current stock and each warehouse's column are displayed independently of the selected date range.

Subject amounts are the absolute sums of their transactions. Reported sales profit is the revenue-subject amount less the cost-of-goods-sold-subject amount.

## Product and service report downloads

A product CSV may include all PDF report columns, product details, related subject codes, and each warehouse's stock. A service report gives details, balances of revenue, service cost, and return subjects, and those subject codes.

Name is always present; other columns are optional. Files can be downloaded or emailed. To reuse a CSV report for product/service import, keep name and group columns.

## Reconcile inventory

1. Open the unfiltered product list and find the product by code.
2. Compare product stock with its summed warehouse stock.
3. Export the report with inbound, outbound, stock, cost-of-goods-sold, and inventory-subject columns.
4. Review purchase, sale, and return invoices in the active year using the same status scope.
5. Open the inventory subject's subledger/detail ledger.
6. For quantity differences, review CSV imports, direct edits, and transfers. For monetary differences, also check purchase confirmation order, ancillary costs, and cost of goods sold.

## Accountant's checklist

- The active fiscal year is correct.
- Expected invoices are confirmed.
- Each invoice's warehouse matches the physical receipt or issue location.
- Summed warehouse stock equals product stock.
- Low-stock products appear as needing reorder according to their thresholds.
- Ancillary purchase costs were allocated to the correct product and confirmed.
- Product stock, warehouse average, and cost of goods sold have not been confused.
- Accounting-report filters match the warehouse report's period and status scope.

</div>
