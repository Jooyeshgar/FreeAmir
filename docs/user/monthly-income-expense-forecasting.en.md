# Monthly Income and Expense Forecasting

**[نسخه فارسی](monthly-income-expense-forecasting.md)**

**[Back to the ordinary user guide](README.en.md)**

This guide explains how Amir calculates forecasts, actual values, variances, and performance percentages in the **Monthly Income and Expense Workbench**.

## Overview

For each Jalali month in the active company's fiscal year, the workbench compares:

| Value | Meaning |
|---|---|
| Forecast | Expected income or expense, entered manually or calculated by the system |
| Actual | The result of approved accounting transactions in the selected month |
| Variance | The favorable or unfavorable difference between actual and forecast |

Forecasting operates on temporary accounting subjects; income and expense accounts closed at fiscal year-end.

## Actual Income and Expense

A transaction contributes to actual values only when its accounting document:

- belongs to the active company;
- is approved (`approved_at` is not null);
- is dated within the selected Jalali month; and
- posts to a relevant temporary subject or one of its descendants.

Draft and unapproved documents are excluded. The selected Jalali month is converted to a Gregorian date range before documents are queried.

For actual totals, Amir combines each temporary root with all of its descendants, at every depth. Transactions posted to permanent detail subjects beneath a temporary root are included as well.

```text
Root balance = root transactions + all descendant transactions
```

The root's direction is determined once from the signed sum of all approved transactions on the root and every descendant in the active fiscal company:

```text
If complete hierarchy balance > 0:
    Subject direction = income

If complete hierarchy balance < 0:
    Subject direction = expense
```

The selected month's balance is assigned to that direction while retaining its accounting sign. An opposite-signed movement is a reversal: it reduces that category instead of being converted into a positive amount. This makes the sum of the monthly values reconcile with the complete approved hierarchy balance.

Therefore:

```text
Actual Income  = SUM(signed monthly balances of income roots)
Actual Expense = SUM(signed monthly balances of expense roots)
```

The complete approved hierarchy balance determines the actual-income or actual-expense classification, not merely the configured subject type or only the selected month's movement. For a manual forecast row on a subject configured as **Both**, the entered sign determines that forecast row's direction; it does not reclassify the actual totals.

If both the applied forecast and the available actual value are zero, there is no amount to classify. In that case, the workbench uses the configured subject type: **Debtor** is shown as expense and **Creditor** as income. A **Both** subject retains its hierarchy-based direction because its configured type has no single direction.

### Example

| Temporary root | Net balance | Income | Expense |
|---|---:|---:|---:|
| Sales revenue | +1,200 | 1,200 | 0 |
| Operating expenses | -700 | 0 | -700 |
| Other revenue | +300 | 300 | 0 |

```text
Actual Income  = 1,200 + 300 = 1,500
Actual Expense = -700
```

Each root is netted before classification. Transactions of `+1,000` and `-200` under the same root produce `800` of income, not separate income of `1,000` and expense of `200`.

## Manual Forecasts

A manual forecast contains a month, a temporary subject, and an amount. The input sign specifies its direction:

```text
Positive amount = income forecast
Negative amount = expense forecast
```

| Subject type | Accepted direction |
|---|---|
| Creditor | Income (positive input) |
| Debtor | Expense (negative input) |
| Both | Income or expense |

The sign determines `budget_type`, while `forecast_amount` is stored as a positive magnitude:

```text
Input: -800
Stored budget_type: expense
Stored forecast_amount: 800
```

Only one forecast can exist for a company, month, and subject. A manual forecast always takes priority over the system forecast for that subject, including completed, current, and future months.

The **Copy Previous Month** action replaces the selected month's complete manual forecast set with a copy of the preceding month's set.

## System Forecasts

Month completion is determined from the current Jalali date in the `Asia/Tehran` timezone:

- all months in an earlier fiscal year are completed;
- in the current fiscal year, months before the current Jalali month are completed;
- the current month and later months are not completed.

### Completed month

For a completed month, the system forecast is its actual result:

```text
System Forecast Income  = Actual Income
System Forecast Expense = Actual Expense
```

Completed months act as historical reporting points rather than predictions.

### Current or future month

For a current or future month, an unforecasted subject inherits the preceding month's **applied forecast**. Documents entered in the open month affect its actual value and variance, but do not replace this inherited system forecast:

```text
System Forecast(month N) = Applied Forecast(month N - 1)
```

The first fiscal month has no preceding month. When it has an approved document, its system forecast starts from its actual signed value; otherwise it starts from zero:

```text
System Forecast(Farvardin) = Actual(Farvardin), if a document exists
System Forecast(Farvardin) = 0, otherwise
```

This calculation is sequential and cached during analysis. The system does not search from the beginning of the year every time: each month needs only the already resolved applied forecast of the preceding month. If a preceding forecast is changed, the sequence is resolved again from the updated value.

## Applied Forecast

The applied forecast is selected separately for every subject and every month:

```text
If a manual forecast exists:
    Applied Forecast = Manual Forecast
Otherwise:
    Applied Forecast = System Forecast
```

This priority also applies to completed months. A completed month's total is therefore a combination of manual values for forecasted subjects and actual values for subjects without manual forecasts. Manual values are not replaced by the total actual result.

For example:

```text
Subject A manual forecast = 1,000
Subject A actual value    = 1,200
Subject B has no manual forecast
Subject B actual value    = 300

Applied total forecast = 1,000 + 300 = 1,300
```

For current and future months, the priority creates a carry-forward chain. For example, if Tir has a manual forecast of `1,800`, Mordad has no manual value, Shahrivar has a manual forecast of `2,200`, and Mehr has no manual value:

```text
Tir applied forecast       = 1,800 (manual)
Mordad applied forecast    = 1,800 (inherited from Tir)
Shahrivar applied forecast = 2,200 (manual)
Mehr applied forecast      = 2,200 (inherited from Shahrivar)
```

## Root and Child Forecasts

Forecast selectors expose temporary roots and their immediate lower-level subjects, while calculations can include deeper levels. Amir prevents double-counting:

1. If a root has no manual forecast but an immediate lower-level subject does, that lower subtree is separated from the root. The root becomes the **remainder excluding lower-level forecasts**.
2. If both a root and a child subject have manual forecasts, the root controls the forecast total. The child row is shown for reference and marked **Detail only — included in root total**.

The warning above **New Forecast** instructs users to enter the combined forecast amount for the root itself and all its child subjects—not only the root's own amount. Thus, a root forecast of `10,000` and child forecast of `3,000` produce a total forecast of `10,000`, not `13,000`.

Because the child rows are a breakdown of the root total, they must use the same income or expense direction and their combined amount cannot exceed the root forecast. Amir rejects a save that would violate either condition.

If the following month keeps only the lower-level manual forecast, the inherited root value is first reduced by that lower-level subject's preceding applied forecast. Only the root remainder and the new lower-level value are then added, so carry-forward does not count the lower-level forecast twice.

Forecast groups containing a manual value are listed first. Root and lower-level rows remain together: the root appears first and is immediately followed by its manually forecasted immediate lower levels. Groups containing only system-calculated values appear below the manual groups. Consequently, a system-calculated root may appear in the manual section when one of its lower levels has a manual forecast.

Each applied value displays its source badge next to the number. The explanation for **Manual Forecast** and **System forecast** appears once above the table instead of being repeated as a tooltip on every row. A manual row also displays the system value for comparison.

## Variance Calculations

Variance is oriented so that a positive result is favorable and a negative result is unfavorable.

### Income variance

```text
Income Variance = Actual Income - Forecast Income
```

| Forecast | Actual | Variance | Meaning |
|---:|---:|---:|---|
| 1,000 | 1,200 | +200 | Favorable; income exceeded forecast |
| 1,000 | 800 | -200 | Unfavorable; income fell below forecast |

### Expense variance

Expense values retain their negative accounting sign. The same signed subtraction is therefore used, and spending less than forecast produces a positive result:

```text
Expense Variance = Actual Expense - Forecast Expense
```

| Forecast | Actual | Variance | Meaning |
|---:|---:|---:|---|
| -1,000 | -800 | +200 | Favorable; spending was under budget |
| -1,000 | -1,200 | -200 | Unfavorable; spending exceeded budget |

### Variance percentage

```text
Income Variance %  = (Variance / Forecast Income) * 100
Expense Variance % = (Variance / (-1 * Forecast Expense)) * 100
```

For an expense forecast of `-800` and actual expense of `-600`:

```text
Variance   = -600 - (-800) = 200
Variance % = (200 / (-1 * -800)) * 100 = 25%
```

When the forecast is zero, Amir reports `0%` to avoid division by zero.

## Achievement and Utilization

These forecast performance ratios are distinct from variance percentage:

```text
Income Achievement % = (Actual Income / Forecast Income) * 100
Expense Utilization % = (Actual Expense / Forecast Expense) * 100
```

- Income achievement above `100%` means income exceeded forecast.
- Income achievement below `100%` means income fell below forecast.
- Expense utilization below `100%` means spending remained under budget.
- Expense utilization above `100%` means spending exceeded budget.

When a forecast denominator is zero, the corresponding ratio defaults to zero.

## Number Display and Charts

The workbench keeps expense forecasts and actuals negative during calculations. In tables and metric cards, `formatNumber()` displays a negative value using accounting parentheses:

```text
-800 is displayed as (800)
```

Charts display magnitudes as positive numbers. Only at the chart presentation boundary, Amir applies `ABS` to income and expense values:

```text
Signed income reversal used in calculations = -200
Income value sent to chart                  = ABS(-200) = 200
Signed expense used in calculations         = -800
Expense value sent to chart                 = ABS(-800) = 800
```

The monthly workbench charts and the **Monthly Income vs Cost** chart in `reports.cost-income` therefore display positive bars, lines, and slices without changing the signed accounting calculations.

Charts show magnitude only. To determine whether a value was originally positive or negative, use the signed values in the table or metric cards.

## Months Without Accounting Documents

Actual values are available only if at least one approved document exists in the selected month. If none exists:

```text
Actual Income  = unavailable
Actual Expense = unavailable
Variance       = unavailable
```

If an approved document exists but contains no relevant income or expense transactions, actual calculation is enabled and may produce zero values.

## Full-Year Reporting

Annual analysis applies the same calculation to all 12 Jalali months:

```text
Annual Forecast Income  = SUM(monthly forecast income)
Annual Forecast Expense = SUM(signed monthly forecast expense)

Annual Actual Income =
    SUM(actual income for months containing approved documents)

Annual Actual Expense =
    SUM(signed actual expense for months containing approved documents)
```

Annual calculations retain signed income and expense totals. Charts display their absolute magnitudes, keeping presentation positive while the workbench and cost-income report calculations remain consistent.
