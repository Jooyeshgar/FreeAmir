# Extension API

**[نسخه فارسی](extensions.md)**

**[Back to programmer guide](README.en.md)**

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
- `App\Extensions\InvoiceExtensionRegistry` for the current invoice-model extension lifecycle.

Navigation IDs are globally unique, view item IDs are unique within a slot, and invoice extension keys
are globally unique. Duplicate registrations are configuration errors. Multiple packages may register
different IDs at the same location. Lower priorities run first; ties are sorted by ID/key. Navigation and
view registrations default to priority 100. Model handler classes are resolved through the container per
operation; registries do not retain handler instances or validated request payloads. Handlers must not bind
request-specific state as singletons. View data callbacks must use the supplied context rather than capture
a request at application boot. Visibility and authorization must be checked for the current request.

View slots accept arbitrary page data through `context`, for example:

```blade
<x-extension-slot slot="customer.show.panels" :context="['customer' => $customer]" />
```

The callback receives this context and returns an array of view data, or `null` to skip the item.
The view receives the context merged with the callback's data; callback data takes precedence.
All page data is passed through `context`; invoice pages use `:context="['invoice' => $invoice]"`.
Each item is rendered independently. Callback errors, invalid callback return values, and Blade rendering
errors are reported through Laravel's exception handler and the failing item is omitted. Other items
continue rendering. Missing views and `null` results are skipped without reporting an error.
Slot views render as self-contained fragments with isolated Blade state, including nested components.
Pass page data explicitly through `context`; do not depend on the parent's component scope, sections, or
stacks, or use `@push` inside a slot to add host-page assets. Use the shared frontend bundle for assets.
Registration errors, such as duplicate item IDs, remain configuration errors and are not suppressed.

An extension implements `App\Extensions\Contracts\ModelExtension` and registers a key, handler
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

## Navigation tree foundation (issue #453)

The registry also accepts an immutable `App\Extensions\MenuItem` definition:

```php
$registry->register(new \App\Extensions\MenuItem(
    id: 'example.categories',
    label: 'example::messages.title',
    route: 'example.index',
    parent: 'core.management.system',
    priority: 150,
    permissions: ['example.view'],
    permissionMode: 'any',
    visible: static fn (array $context): bool => $context['fiscal_year_id'] !== null,
    routeParameters: ['mode' => 'categories'],
));
```

`parent: null` declares a root. Core IDs use the stable `core.*` namespace, including
`core.invoices`, `core.management`, and `core.management.system`. Routes remain trusted
named routes; route existence is checked by the renderer after providers finish booting.
`routeParameters` contains fixed, named scalar or null values. It cannot retain request or
model objects in the singleton. Permissions are a list; `permissionMode` accepts `any` or
`all`. These are definitions: registration and `tree()` do not evaluate authorization or
visibility callbacks, resolve the user, or cache a filtered tree.

`tree()` assembles a fresh array of `['item' => MenuItem, 'children' => [...]]` nodes.
Children can be registered before their parents. Siblings sort by priority, then ID.
An unknown parent, self-reference, or disconnected parent cycle raises `LogicException`
outside production. In production, the invalid branch and its descendants are omitted;
valid roots remain available. Definitions remain registered and can be read again after
a missing parent is registered. Null labels are retained in the definition tree for the
renderer to filter; they are not an instruction to display an untitled group.

The version 1 `register($id, $array)` / `items($area)` API retains its array fields,
single permission, callback, metadata, defaults and ordering. The `<x-extension-navigation>`
component continues to consume it; the shared `<x-menu>` adapts these entries into its tree. In the new tree, legacy entries use these placements:

| Legacy area | Parent | Core slot priority | Intended position |
|---|---|---:|---|
| `navigation.workspace.invoices` | `core.invoices` | 700 | After the existing invoice items at priorities 100–600 |
| `navigation.management` | `core.management` | 400 | After the three existing groups at 100–300 and before About at 500 |

The legacy item's own priority sorts only within that area's slot. It cannot move an old
item across core siblings, even at an extreme integer priority. A new typed sibling at the
slot's exact priority sorts before that legacy block. A legacy area outside these two
names maps to its area string as a parent ID, without a special slot priority; that parent
must also be registered when reading the tree.

`CoreNavigationServiceProvider` registers the core menu under stable `core.*` IDs. The `<x-menu>`
component evaluates permissions and visibility callbacks on each request, omitting empty groups,
null labels and missing routes. Desktop and mobile share the recursive renderer, which displays
both typed items and legacy registrations.

Active matching checks the route and menu discriminator parameters exactly, including absence of
`service_buy` for ordinary purchases. Non-menu filters such as pagination and search are ignored,
and the active item's parent summaries are highlighted too.

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
existing closing document do not dispatch it. `VoidInvoiceCreated` carries the original invoice and the
new void invoice. It is dispatched inside the void transaction after saving the void invoice and before
copying its items and approving it; it does not signal completion or commit of the operation.

The category package owns its tables and migrations. Its invoice model is configured by the host and its
dynamic relation is registered by the package provider, without a package trait in `App\Models\Invoice`.
Category-only operations must not change accounting amounts, postings, statuses, or payments.

## Implementation checkpoints

1. Local development environment and standard Sail test command.
2. Registries, API version, host context, and event contract.
3. Package schema, discovery, and dynamic invoice relation.
4. Category management UI, routes, validation, and registered navigation.
5. Invoice form slots and transactional extension persistence.
6. Detail assignment for every invoice type/status, including paid invoices.
7. List filters integrated before statistics and pagination.
8. Transactional fiscal-year definition copying and derived-invoice inheritance.
9. Package archive installation, integration verification, and delivery documentation.

Each checkpoint is verified and committed separately. Installation and CI results are recorded only when
they have actually been observed.
