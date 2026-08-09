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

For actual totals, Amir combines each temporary root with all of its descendants.

> **Note:** If a root subject is temporary, every descendant beneath it must also be temporary. A permanent subject cannot belong to that subtree.

```text
Root balance = root transactions + all descendant transactions
```

It then classifies each net root balance by sign:

```text
If root balance > 0:
    Income contribution = root balance

If root balance < 0:
    Expense contribution = ABS(root balance)
```

Therefore:

```text
Actual Income  = SUM(positive temporary-root balances)
Actual Expense = SUM(ABS(negative temporary-root balances))
```

The transaction balance sign determines the classification, not merely the configured subject type.

### Example

| Temporary root | Net balance | Income | Expense |
|---|---:|---:|---:|
| Sales revenue | +1,200 | 1,200 | 0 |
| Operating expenses | -700 | 0 | 700 |
| Other revenue | +300 | 300 | 0 |

```text
Actual Income  = 1,200 + 300 = 1,500
Actual Expense = 700
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

Only one forecast can exist for a company, month, and subject. For an open or future month, a manual forecast takes priority over the system forecast.

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

For an open or future month, the system uses the arithmetic mean of actual values from all preceding completed months:

```text
System Income Forecast =
    SUM(actual income in preceding completed months)
    / number of preceding completed months

System Expense Forecast =
    SUM(actual expense in preceding completed months)
    / number of preceding completed months
```

If income actuals from Farvardin through Tir are `1,000`, `1,400`, `800`, and `1,600`, the Mordad forecast is:

```text
(1,000 + 1,400 + 800 + 1,600) / 4 = 1,200
```

If there is no preceding completed month, the system forecast is zero.

## Applied Forecast

For an open or future month:

```text
If a manual forecast exists:
    Applied Forecast = Manual Forecast
Otherwise:
    Applied Forecast = System Forecast
```

For a completed month, forecast totals are replaced by actual totals:

```text
Forecast Income  = Actual Income
Forecast Expense = Actual Expense
```

A stored manual amount can still appear in a completed month's line table for comparison, while its forecast indicators use historical actual totals.

## Parent and Child Forecasts

Forecast selectors expose temporary roots and their direct children, while calculations can include deeper descendants. Amir prevents double-counting:

1. If a root has no manual forecast but a direct child does, the child's subtree is separated from the root. The root becomes the **remainder excluding child forecasts**.
2. If both a root and child have manual forecasts, the root controls the forecast total. The child is displayed as detail and marked **included in parent total**.

Thus, a root forecast of `10,000` and child forecast of `3,000` produce a total forecast of `10,000`, not `13,000`.

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

Because spending less than forecast is favorable, the direction is reversed:

```text
Expense Variance = Forecast Expense - Actual Expense
```

| Forecast | Actual | Variance | Meaning |
|---:|---:|---:|---|
| 1,000 | 800 | +200 | Favorable; spending was under budget |
| 1,000 | 1,200 | -200 | Unfavorable; spending exceeded budget |

### Variance percentage

```text
Variance % = (Variance / Forecast) * 100
```

For an expense forecast of `800` and actual expense of `600`:

```text
Variance   = 800 - 600 = 200
Variance % = (200 / 800) * 100 = 25%
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
Annual Forecast Expense = SUM(monthly forecast expense)

Annual Actual Income =
    SUM(actual income for months containing approved documents)

Annual Actual Expense =
    SUM(actual expense for months containing approved documents)
```

Annual charts use these same monthly results, keeping the workbench and cost-income report consistent.
