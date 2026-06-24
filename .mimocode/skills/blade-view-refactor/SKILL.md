---
name: blade-view-refactor
description: >-
  Refactor a Laravel Blade view to match the style, layout, and component
  patterns of a reference view. Handles Persian RTL, metric-card components,
  dashboard layouts, and compact detail pages.
---

# Blade View Refactoring with Reference

Refactor a target Blade view to match the structure, component usage, and
styling of an existing reference view. Common in dashboard, customer, and
report pages of this project.

## When to Use

- User asks to "update X using Y as reference" or "refactor X like Y"
- Migrating a view from a raw table layout to card/component-based UI
- Aligning metric cards, charts, or detail pages across different sections
- Applying consistent Persian RTL styling to new or updated views

## Workflow

### 1. Read Both Views

Read the **target** view and the **reference** view. Note:

- Which Blade components are used (`metric-card`, `bar-chart`, `pie-chart`, `stat-strip`, etc.)
- Layout structure (grid, flex, cards vs tables)
- Chart.js integration patterns (background gradient fills, smooth lines, datalabels)
- Persian/RTL alignment (`dir="rtl"`, text alignment classes)

### 2. Identify the Reference Patterns

Extract the key UI patterns from the reference view:

- **Component usage**: Which `resources/views/components/` components are called and how
- **Data flow**: How data is passed (via `$variable`, service methods, collections)
- **Chart config**: Chart.js options, colors, responsive settings
- **Card structure**: Metric card props (`title`, `value`, `trend`, `icon`, `series`, `chartType`)
- **Styling**: Tailwind classes, custom CSS, gradient backgrounds

### 3. Rewrite the Target View

Apply the reference patterns to the target view:

- Replace raw HTML tables with card/component layouts where appropriate
- Use the same Blade components with matching prop signatures
- Preserve the target view's data source (its own service/controller method)
- Keep the target view's business logic and route bindings intact
- Do NOT copy data-fetching code from the reference; only copy UI structure

### 4. Fix Persian RTL Issues

After structural changes, verify:

- All text containers respect RTL direction
- Numbers and currency values display correctly in RTL context
- Use `text-right` or `text-left` intentionally, not by default
- Tables with mixed content (Persian text + numbers) need careful alignment
- Charts should render correctly in RTL context (Chart.js handles this via `mirrored` option)

### 5. Validate

- Check that Blade component props match their definitions in `app/View/Components/`
- Verify referenced routes exist in `routes/web.php`
- Ensure data variables used in the view are available from the controller
- If using chart components, confirm the data format matches expected series structure

## Key Files

- **Metric card component**: `resources/views/components/metric-card.blade.php`
- **Chart components**: `resources/views/components/charts/bar-chart.blade.php`, `pie-chart.blade.php`
- **Stat strip**: `resources/views/components/stat-strip.blade.php`
- **Dashboard examples**: `resources/views/home/financial-metrics.blade.php`, `resources/views/home/sales-metrics.blade.php`
- **Report examples**: `resources/views/reports/cost-income/index.blade.php` (with `_metrics.blade.php`, `_monthly.blade.php`, `_breakdown.blade.php`, `_top-customers.blade.php`)
- **Detail examples**: `resources/views/customers/show.blade.php`, `resources/views/customerGroups/show.blade.php`
- **List examples**: `resources/views/customers/index.blade.php`, `resources/views/employees/index.blade.php`

## Examples

### Example: Metric Card Refactoring

```
User: "update sales-metrics.blade.php based on financial-metrics.blade.php and replace card with metric card"
```

1. Read `financial-metrics.blade.php` to see how `<x-metric-card>` is used
2. Read `sales-metrics.blade.php` to see current layout
3. Replace raw `<div>` cards with `<x-metric-card>` components
4. Preserve the sales-specific data source (`$salesData`)
5. Verify icon props (use default icons if not provided)

### Example: Dashboard Layout Alignment

```
User: "refactor cost-income dashboard use payrolls/dashboard.blade.php as reference"
```

1. Read `payrolls/dashboard.blade.php` for reference layout
2. Read `reports/cost-income/index.blade.php` for current state
3. Extract sub-views: `_metrics.blade.php`, `_monthly.blade.php`, `_breakdown.blade.php`, `_top-customers.blade.php`
4. Align grid structure, chart sizing, and card spacing
5. Keep cost-income specific data and business logic

### Example: Detail Page Compact Layout

```
User: "refactor customer detail show.blade.php, I need more compact detail"
```

1. Read current `customers/show.blade.php`
2. Identify a compact reference (e.g., employee detail page)
3. Restructure into compact cards: contact info priority, comments in popup
4. Fix Persian RTL alignment for labels and values
5. Ensure comment system still functions

## Notes

- This project uses Laravel Blade components under `resources/views/components/`
- Charts use Chart.js with `chartjs-plugin-datalabels`
- Persian (Farsi) is the primary UI language with RTL direction
- Tailwind CSS + DaisyUI for styling
- Alpine.js for interactive elements (popups, dropdowns)
- Date pickers use `@majidh1/jalalidatepicker` for Jalali calendar
