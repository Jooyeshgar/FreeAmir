<div dir="ltr">

# Products and Services

**[Back to the warehouse guides](../README.en.md)**

This guide covers defining products and services, their groups and automatic accounting subjects, and CSV import/export.

## Product or service?

| Choose | Use for | Quantity and cost effects |
|---|---|---|
| Product | Items bought, stored, transferred, or sold in quantities | A confirmed invoice changes product and warehouse stock; a confirmed sale records cost of goods sold |
| Service | Work that does not need stock | Appears on invoices and accounting documents but does not change product or warehouse stock |

An invoice may contain both. Warehouse selection affects goods lines only. See the [invoice guides](../../invoices/README.en.md) for invoice-specific details.

## Setup before the first invoice

1. Check the active fiscal year.
2. Connect sales, sales-return, inventory, cost-of-goods-sold, service-revenue, and service-cost configurations to their subjects.
3. Create a product or service group. Amir creates its subjects beneath the configured parent subjects.
4. Create a product or service in the appropriate group. Amir creates its subjects beneath those of the group.
5. For goods, create at least one warehouse and record a purchase or opening stock in the intended warehouse.
6. Review then confirm the invoice. Draft and unconfirmed invoices do not change product stock.

If inventory configuration is missing, the product-group creation page redirects to configurations. Service-group creation also depends on service-revenue configuration.

## Subjects Amir creates

Each product group gets four same-named subjects: sales revenue, sales returns, cost of goods sold, and inventory. Each product gets four corresponding same-named subjects beneath its group. Renaming a product or group updates the names of linked subjects.

Each service group gets three same-named subjects: revenue, sales returns, and service cost. Each service gets three corresponding subjects beneath its group; services have no inventory subject. Renaming a service or group updates linked subject names.

## Group fields

A product or service group has name, tax rate, and Taxpayer System ID (`SSTID`). Name is required, at most 20 characters, and limited to letters, digits, spaces, and characters accepted by the form. Tax rate must be 0–100. When an individual product/service SSTID is blank on its edit form, the group's SSTID is displayed.

## Product fields

| Field | Behavior |
|---|---|
| Group | Required |
| Code | Unique in the active fiscal year; blank generates the number after the highest code |
| Name | Required, at most 20 characters |
| SSTID | Optional; if blank, the form displays the group's SSTID |
| Sale price | Optional; blank becomes zero |
| Tax rate | 0–100; blank becomes zero |
| Warehouse location | Optional text up to 50 characters; a general location label, not one warehouse's stock |
| Quantity | Nonnegative number with optional thousands separator and fractional part; blank becomes zero |
| Alert threshold | Same number format as quantity; a positive value is used for reorder detection |
| Sell beyond stock | If on, confirmation with insufficient stock is allowed with a warning; if off, confirmation is rejected |
| Discount formula | Optional text up to 100 characters, e.g. `1-30:400, 30-100:360.7` |
| Description | Optional text up to 150 characters |
| Websites | Every address must be a valid URL |

Entering quantity on the product form changes product database fields only; it does **not** create an accounting document, invoice movement, or warehouse stock. Use a purchase or opening-stock invoice for auditable inventory.

## Service fields

A service has group, code, name, SSTID, sale price, and tax rate. Code, name, price, and tax follow product rules. It has no stock, alert threshold, warehouse location, discount formula, websites, or sell-beyond-stock option.

## Create, edit, and delete

- Changing a name or group synchronizes linked subjects with the new name and parent.
- A product/service delete button is disabled after it appears on an invoice line.
- A group containing products/services cannot be deleted.
- A group cannot be deleted if one of its subjects has descendants, transactions, or another relationship.
- Deleting an eligible group deletes that group's automatically created subjects.

Check accounting history before deletion. Renaming is usually better than deleting and recreating merely to correct a title.

## CSV export and import

From the product or service list, choose export columns and download or email a file. Name is always included. A full product export contains details, four related subject codes, and stock in each warehouse; a full service export contains details and three subject codes.

Imports must be CSV or TXT, at most 5 MB. Exporting first gives the safest header template.

- `name` and `group_name` are required.
- Persian, English, and internal-key headers are recognized. Order does not matter; unknown columns are ignored.
- A code matching an existing product, service, product group, or service group in the active fiscal year updates it. A blank or new code creates a new record.
- A same-named group is reused; a new group is created automatically.
- An imported subject code must match the relevant group subject's code structure and not already be linked to another record.
- In a product CSV, a column named after a warehouse sets stock in that warehouse and product stock is updated from their sum.
- The entire file imports in one transaction; any row error rolls back earlier rows from that file.

CSV stock import creates no invoice movement or inventory document. Use an opening-stock invoice for a reconcilable opening balance.

## Common errors

| Error | What to check |
|---|---|
| Duplicate code | Active fiscal year and existing product/service codes |
| Invalid group | Create/select it in the same fiscal year |
| Missing base subject | Complete fiscal-year configurations before creating the group |
| Delete disabled | Invoice lines or group/subject dependencies |
| CSV does not fully import | Error row number, required name/group, and subject code |
| Stock changed by CSV but lacks a document | Correct quantity through an opening-stock invoice to record accounting movement |

</div>
