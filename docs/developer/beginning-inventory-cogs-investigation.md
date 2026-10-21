# Beginning-inventory ordering and COGS (#435)

## Confirmed cause and agreed correction

Beginning inventory used to be applied to current stock even after ordinary
inventory invoices had already been posted. A backdated opening changed the
current product average, but the previous purchase selected by date retained an
earlier `cog_after`. For example: buy 10 at 100 on January 2, then approve opening
stock of 10 at 300 dated January 1, then buy 10 at 200 on January 3. The final
purchase produced 133.33 instead of 200; selling four units then recorded 533.32
rather than 800 in COGS.

The review at `8c5ca11e` confirmed an opening-first posting workflow with the MR
author. The revision removes the proposed `cog_before` migration, attribute, and
calculation branches. Existing `cog_after` and quantity inputs remain in use.
There is no new schema migration in this MR.

## Posting rule

- Beginning inventory can be saved as a pending draft while ordinary movements
  are posted. Approval, correction of an approved opening (including ordinary
  Save), and reversal require every ordinary inventory invoice in the active
  fiscal year to be inactive. Approved, partially paid, and paid invoices all
  count as posted. Drafts, unapproved, and approved-inactive records do not.
- The restriction covers every warehouse and product in the active fiscal year,
  because product average cost is shared across warehouses. Service-only invoices
  and other fiscal years do not block opening inventory.
- **The opening date must be strictly earlier than ordinary movement dates.**
  Same-day ordinary movements are rejected, regardless of internal ID or invoice
  number. Opening approval also checks approved-inactive movements so later
  reactivation cannot put a pre-existing purchase ahead of the opening.
- Ordinary inventory invoices cannot subsequently be approved, reactivated, or
  edited while posted to a date on or before an approved opening. Pending drafts
  can still be saved; their dates must be corrected before approval.
- Multiple warehouses' openings that share products retain date/ID order:
  approve oldest first and reverse newest first.

Validation decisions expose conflicts to the existing interface. Service guards
repeat the opening-order check inside transactions before inventory or documents
change, including direct creation, approval, approved editing, reversal, voiding,
and group reactivation. Fiscal-year row locks serialize these posting checks.

## Late registration and correction

1. Save the opening as a draft. For an existing approved opening, use its conflict
   workflow.
2. Use `GroupActionService::inactivateDependentInvoices()` to deactivate posted
   movements across the fiscal year in descending date/internal-ID order. The
   existing workflow reverses ancillary costs and removes non-cheque payment
   documents while retaining payment records. A pending opening remains pending.
3. Give the opening a date strictly before the ordinary movements, then approve
   it. Correct an existing opening after its dependencies have been deactivated.
4. Use `approveInactiveInvoices()` to reactivate previously approved invoices in
   ascending date/internal-ID order. Documents, quantities, and COGS are rebuilt.
   Ancillary costs are restored before payments, including purchases paid in full
   for both goods and ancillary costs. Payment reconstruction errors roll back
   the group operation. Ancillary-only reactivation does not post an already
   approved purchase twice.

Ancillary validation now uses date/internal-ID order too, so same-day purchases
and sales with reversed display numbers follow the same sequence as costing and
group reactivation. The existing Create-button availability fix and the
one-record-per-warehouse-per-fiscal-year restriction remain in place.

## Verification and limits

Regression coverage includes blocked late approval, approved edit and reversal,
all posted payment statuses, cross-warehouse/unrelated-product conflicts, pending
drafts, same-day dates, later backdating, group reactivation, and fiscal scoping.
The full late-registration and correction workflow uses opening 10 at 300, buy 10
at 100 plus 300 ancillary cost, and sell four: quantity ends at 16, average cost
is 215, and sales COGS is 860. Correcting the opening to 500 per unit and replaying
produces average 315 and COGS 1260. Both purchase and sale return to paid status;
all rebuilt documents balance. A second warehouse's opening is also tested with
purchase reversal and ancillary approval (expected averages 200 and 220).

Tests run through Sail with SQLite in memory. No production migration or
recalculation is performed. SQLite does not verify database row-lock behavior.
Historical incorrect costs are not automatically repaired; reconciliation needs
separate review. Accounting-domain review remains required before merging under
`AGENTS.md`. Automated HTTP checks cover availability and posting rejection;
browser interaction is not part of this verification.

Final local verification for this revision:

- 179 tests passed (927 assertions) across `BeginningInventoryTest` (32 tests),
  `COGSCalculationTest`, `InvoiceGroupActionTest`, `InvoiceAncillaryCostDateSyncTest`,
  `WarehouseInvoiceStockTest`, `VoidSellInvoiceTest`, `InventoryTurnoverReportTest`,
  `InvoicePaymentTest`, and `InvoiceDashboardTest`.
- A broader run passed 114 tests and found six failures: CSV export filtering,
  three mixed-return document-balance failures, warehouse inventory filtering,
  and an SQL `FOR UPDATE` assertion unsupported by SQLite. All six reproduced
  with `origin/main`'s original invoice, ancillary-cost, and group-action services.
- Pint, translation JSON parsing, and `git diff --check` passed.
