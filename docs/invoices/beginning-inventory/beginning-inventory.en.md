---
title: "Beginning Inventory"
description: "Record opening quantities and values of products for each warehouse"
---

# Beginning Inventory

Menu path: **Invoices → Beginning Inventory**. [Open the list in the app]({{ site.app_url }}/invoices?invoice_type=beginning_inventory). This invoice type records opening product quantities and values in a warehouse for the active fiscal year; it is not a supplier purchase.

1. Select the correct fiscal year and one of its warehouses. Only one beginning-inventory invoice is allowed per warehouse.
2. Select **Create Beginning Inventory**. The button stays available while at least one warehouse in the active fiscal year has no record; it becomes inactive when every warehouse has a record, including pending or unapproved records. Check the title, number, and date; the form defaults to the start of the fiscal year.
3. Select products and enter quantity and unit cost for each. This form accepts products only; it has no customer, discount, or tax.
4. Save, then reconcile total quantity and value with the stock count and opening balance before approval.

On approval, the app includes the incoming products in stock and cost calculations and creates a related document; the **Beginning Inventory** subject must be configured. In the list, check the number, document, warehouse, date, quantity, status, and available actions. If it says that beginning inventory already exists for the selected warehouse, review that record instead of creating a second one.

Beginning inventory must be approved before ordinary inventory movements in the active fiscal year. Its date must be strictly earlier than every posted or approved-inactive inventory invoice; same-day movements are not allowed. Pending drafts can be saved, but approval checks the order again. Normal invoices also cannot later be approved or backdated to or before an approved opening.

For late registration or correction, use **Fix Conflict** to deactivate all posted inventory invoices across the active fiscal year, including other warehouses. The group workflow reverses them from newest to oldest and keeps payment records. Approve or correct the opening, then reactivate previously approved invoices from oldest to newest. The app rebuilds stock, costs, ancillary-cost documents, and non-cheque payment documents. Paid and partially paid invoices must also be deactivated; cheque documents continue to follow their cheque lifecycle. Historical incorrect costs require separate reconciliation.

[Inventory costing guide](../../warehouse/products/inventory-costing.en.md)
