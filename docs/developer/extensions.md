# Extension API

Issue #453 provides the shared core navigation tree and its generic extension API foundation.
Navigation registration and rendering are available independently of optional packages.
The dependent invoice-category integration in issue #445 wires invoice slots, persistence,
query hooks and fiscal-year events into application flows; those integrations are not part
of this navigation branch.

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

The `extendQuery()` handlers modify the same Eloquent builder. The dependent invoice integration
must call them before statistics, totals and pagination are computed. Handlers should append
constraints without discarding the host's fiscal-year or permission restrictions.

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
single permission, callback, metadata, defaults and ordering. Existing array consumers
can continue to use it. In the new tree, legacy entries use these placements:

| Legacy area | Parent | Core slot priority | Intended position |
|---|---|---:|---|
| `navigation.workspace.invoices` | `core.invoices` | 700 | After the existing invoice items at priorities 100–600 |
| `navigation.management` | `core.management` | 400 | After the three existing groups at 100–300 and before About at 500 |

The legacy item's own priority sorts only within that area's slot. It cannot move an old
item across core siblings, even at an extreme integer priority. A new typed sibling at the
slot's exact priority sorts before that legacy block. A legacy area outside these two
names maps to its area string as a parent ID, without a special slot priority; that parent
must also be registered when reading the tree.

The core provider registers the complete stable tree, and the shared Blade menu renders
that tree recursively for both desktop and mobile consumers. Permission and visibility
callbacks are evaluated on every request; empty groups are omitted. Legacy area
registrations are still assembled into their original slots, so installed packages keep
their existing API while typed package items can be placed under any core parent.

The shared renderer matches the route and menu discriminator
parameters exactly, including absence of `service_buy` for the ordinary Buy variant, while
ignoring non-menu filters such as pagination and search. Merely matching the route name
or a subset of the query would incorrectly activate several invoice variants at once.

## Host context

Packages resolve `App\Extensions\Contracts\HostContext` for the current authenticated user and selected
fiscal-year ID. The host implementation reads them on each call. It does not use the CLI helper's fallback
to the first fiscal year when no year is selected. `authorizeFiscalYear()` requires authentication and the
selected year, then checks fiscal-year membership. `ensureFiscalYearOpen()`
also delegates to the existing host mutation guard. Operation permissions are separate and must still be
checked by package endpoints.

## Reserved integration locations

The shared navigation tree retains these registered package locations:

- Navigation areas: `navigation.workspace.invoices` in the Invoices menu and `navigation.management` in the Management menu. Both are rendered by the host component.
- Planned invoice view slots: `invoice.create.fields`, `invoice.edit.fields`, `invoice.show.panels`, `invoice.index.filters`, `invoice.index.row.badges`. Wiring these slots belongs to issue #445.

Navigation registered in either legacy area is adapted into its existing slot in the shared
tree; packages may also register a typed `MenuItem` under a stable core parent. The shared
renderer checks the current user, route existence, permission and callback context on every
request, escapes labels and ignores missing routes. Definitions must come from trusted
code rather than request input. The separate `<x-extension-navigation>` compatibility
component belongs to the dependent invoice integration.

Package Blade views should use the host `<x-app-layout>`, `<x-input>`, `<x-textarea>`, and
`<x-show-message-bags>` components. Package views must be included in the host's
Vite/Tailwind source configuration by the integrating change; this branch does not add
package asset scanning. Avoid nested forms inside host slots. Keep interaction local to
Alpine data attributes unless a shared host behavior is required.

Views and handlers must come from trusted installed package code. Do not use request input as a class,
view, file path, or registry definition. Fields rendered inside an existing form must not add nested forms.
The dependent invoice integration must render the same detail slot for beginning inventory as for other invoice types.

## Fiscal-year transitions

The foundation includes the `App\Events\FiscalYearTransitioning` event contract without
wiring a dispatch point. The concrete `FiscalYearCopied` and `InvoiceDerived` events and
their transactional dispatch points belong to the dependent invoice integration in #445.

The category integration must keep package-owned tables, migrations and dynamic relations
outside core models. Category-only operations must not change accounting amounts,
postings, statuses or payments.

## Dependent invoice integration checkpoints (#445)

This list describes the dependent work, not completed navigation deliverables.

1. Local development environment and isolated test command.
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
