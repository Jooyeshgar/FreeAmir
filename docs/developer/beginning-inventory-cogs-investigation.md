# Beginning-inventory COGS investigation (#435)

## Confirmed reproduction

Beginning inventory is applied to current stock when approved, even when its date
precedes purchases already approved. Earlier invoice documents are not rewritten.
The following single-product, single-warehouse sequence exposed a cost loss:

| Approval order | Invoice date | Movement | Quantity afterward | Expected average | Previous result |
|---|---|---|---:|---:|---:|
| 1 | 2026-01-02 | Buy 10 at 100 | 10 | 100 | 100 |
| 2 | 2026-01-01 | Beginning inventory: 10 at 300 | 20 | 200 | 200 |
| 3 | 2026-01-03 | Buy 10 at 200 | 30 | 200 | 133.33 |
| 4 | 2026-01-04 | Sell 4 at 500 | 26 | COGS: 800 | COGS: 533.32 |

The purchase calculation selected the previous invoice by date. It therefore
used the first purchase's average of 100 rather than the current average of 200
that already included opening stock. Warehouse average cost stayed at 200, while
the product average and subsequent sales COGS lost opening value.

## Correction

For products with approved beginning inventory, purchase approval captures
`invoice_items.cog_before`, the product average before applying that purchase,
alongside the existing `quantity_at` snapshot. The cost
calculation uses these saved inputs both at approval and when ancillary costs are
recalculated. Reversing a purchase restores its saved average. Reapproval captures
fresh inputs. Approved edits synchronize the replacement's inputs after adjusting
quantity, and approval reloads item models so cached relations cannot supply old
snapshots.

The regression covers the reproduction, a balanced sales document with COGS 800,
ancillary-cost approval and reversal, purchase reversal and reapproval, approved
purchase edits after opening inventory, and legacy nullable snapshots. Existing
opening approval, reversal, multi-warehouse, and equal/lower purchase-number tests
remain in place.

## Compatibility and review

The migration adds a nullable column and does not backfill historical inputs or
rewrite existing documents. Purchases without approved beginning inventory and
legacy purchases without a snapshot retain the previous calculation path. Reapproving a purchase captures
a snapshot when approved beginning inventory exists for its product. Existing
incorrect historical costs therefore need a separate reviewed reconciliation; this change does not repair
them automatically. Fiscal-year export/import includes the nullable snapshot
through the existing invoice-item serialization.

Accounting-domain review is required before merging the cost calculation change,
as specified in `AGENTS.md`. Review the approval-time cost-flow convention and the
numeric sequence above. No production data migration or recalculation was run.

## Verification

All commands ran through Sail with the PHPUnit SQLite in-memory database.

- The focused run passed 111 tests (533 assertions): `BeginningInventoryTest`,
  `COGSCalculationTest`, `InvoiceGroupActionTest`, `InvoiceAncillaryCostDateSyncTest`,
  `WarehouseInvoiceStockTest`, `VoidSellInvoiceTest`, and `InventoryTurnoverReportTest`.
- `BeginningInventoryTest` includes seven new tests and passes all 23 tests.
- `ReturnInvoiceCOGSTest`: 27 passed, two failed with document-balance errors in
  `test_inventory_value_is_consistent_after_mixed_returns` and
  `test_complex_mixed_scenario_maintains_average_and_quantity_at_each_step`.
  Both failures also reproduce with the original cost and invoice services.
- Broader checks found five further failures, also reproduced with the original
  services: warehouse inventory filtering; a warehouse lock assertion requiring
  SQL `FOR UPDATE` on SQLite; a customer enum conversion in fiscal-year transfer;
  and CSV/Parsian import idempotency. These are outside this change.
- Pint and `git diff --check` passed. No browser session was available; HTTP tests
  verify the rendered Create link and disabled button.
