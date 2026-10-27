# Extension API

FreeAmir is introducing a small host API for optional Laravel packages, tracked by GitLab issue #445.
The registry foundation, management navigation, invoice form/detail/filter slots, transactional invoice
extension persistence, list-query extension hook, and fiscal-year transition events are available.

`App\Extensions\ExtensionApi::VERSION` is `1`. Packages target this host API explicitly. FreeAmir is
an application, not a Composer library; do not invent a dependency on `amir/freeamir`. Package tests cover
their own behavior and host integration tests verify compatibility with the actual FreeAmir checkout.

## Registries

The host registers these services from `App\Providers\ExtensionServiceProvider`:

- `App\Extensions\NavigationRegistry` for management and workspace menu entries.
- `App\Extensions\ViewSlotRegistry` for package views rendered at documented page locations.
- `App\Extensions\InvoiceExtensionRegistry` for invoice query filters and request-scoped persistence.

Navigation IDs are globally unique, view item IDs are unique within a slot, and invoice extension keys
are globally unique. Duplicate registrations are configuration errors. Multiple packages may register
different IDs at the same location. Lower priorities run first; ties are sorted by ID/key. Navigation and
view registrations default to priority 100. Invoice handler classes are resolved through the container per
operation; registries do not retain handler instances or validated request payloads. Handlers must not bind
request-specific state as singletons. View data callbacks must use the supplied context rather than capture
a request at application boot. Visibility and authorization must be checked for the current request.

An invoice package implements `App\Extensions\Contracts\InvoiceExtension` and registers a key, handler
class, and optional priority with `InvoiceExtensionRegistry::register()`. Keys use lowercase letters,
digits, and underscores and start with a letter. Payloads are submitted under `extensions[<key>]`.
Missing payloads mean no change; an explicitly submitted empty array is passed to the handler so it can
implement remove-all. Malformed payloads and unknown submitted keys are rejected with validation errors
instead of silently becoming empty selections. Validation is completed before writes and persistence
must be called within the same database transaction as the invoice operation. The registry coordinates
handlers; it does not start a transaction or authorize a request itself.

The `extendQuery()` handlers modify the same Eloquent builder. The host calls them before statistics, totals,
and pagination are computed. Handlers should append constraints without discarding the host's fiscal-year or
permission restrictions.

## Host context

Packages resolve `App\Extensions\Contracts\HostContext` for the current authenticated user and selected
fiscal-year ID. The host implementation reads them on each call. It does not use the CLI helper's fallback
to the first fiscal year when no year is selected. `authorizeFiscalYear()` requires authentication and the
selected year, then checks membership or the host super-administrator permission. `ensureFiscalYearOpen()`
also delegates to the existing host mutation guard. Operation permissions are separate and must still be
checked by package endpoints.

## Reserved integration locations

The host renders these registered package locations:

- Navigation areas: `navigation.workspace.invoices` in the Invoices menu and `navigation.management` in the Management menu. Both are rendered by the host component.
- View slots: `invoice.create.fields`, `invoice.edit.fields`, `invoice.show.panels`, `invoice.index.filters`, `invoice.index.row.badges`.

Navigation registered in either area is rendered by the host's generic
`<x-extension-navigation>` component. A package provides a stable `id`, translated `label`, named `route`,
optional `permission`, optional `visible` callback, and numeric `priority`. The renderer checks the current
user, route existence, permission, and callback context on every request. It escapes labels and ignores
missing routes. It never evaluates route names, view names, or permissions from request input.

Package Blade views should use the host `<x-app-layout>`, `<x-input>`, `<x-textarea>`, and
`<x-show-message-bags>` components. Package Tailwind classes are scanned from `vendor/amir`; package views
must be included in the host's Vite/Tailwind source configuration. Use `@source` for installed package
views and avoid nested forms inside host slots. Keep interaction local to Alpine data attributes unless a
shared host behavior is required.

Views and handlers must come from trusted installed package code. Do not use request input as a class,
view, file path, or registry definition. Fields rendered inside an existing form must not add nested forms.
The beginning-inventory detail page must render the same detail slot as other invoice types.

## Fiscal-year transitions

`App\Extensions\Events\FiscalYearCopied` carries source and target fiscal-year models. The host dispatches
it synchronously after creating the next year and inside the close/open transaction. Package listeners use
the same connection; failures roll back the transition. Direct year creation and recalculation of an
existing closing document do not dispatch it. `InvoiceDerived` carries the source and generated invoice
when the host creates a derived invoice such as a void invoice.

The category package owns its tables and migrations. Its invoice model is configured by the host and its
dynamic relation is registered by the package provider, without a package trait in `App\Models\Invoice`.
Category-only operations must not change accounting amounts, postings, statuses, or payments.

## Implementation checkpoints

1. Local development environment and isolated test command.
2. Registries, API version, host context, and event contract.
3. Package schema, discovery, and dynamic invoice relation.
4. Category management UI, routes, validation, and registered navigation.
5. Invoice form slots and transactional extension persistence.
6. Detail assignment for every invoice type/status, including paid invoices.
7. List filters integrated before statistics and pagination.
8. Transactional fiscal-year definition copying and derived-invoice inheritance.
9. Package archive installation, integration verification, and delivery documentation.

Each checkpoint is verified and committed separately. Installation from Nexus and an exact-head CI result
are recorded only when they have actually been observed. See [local development](enterprise-development.md)
for the Sail mount and database isolation commands.
