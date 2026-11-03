# Extension API

Issue #453 provides the shared core navigation tree and the host context it needs.
Navigation registration and rendering are available independently of optional packages.
Invoice slots, invoice query/persistence hooks and fiscal-year events belong to the
dependent invoice integration (#445) and are not part of this document.

`App\Extensions\ExtensionApi::VERSION` is `1`. FreeAmir is an application, not a Composer
library; do not invent a dependency on `amir/freeamir`.

The host registers `App\Extensions\NavigationRegistry` as a singleton from
`App\Providers\ExtensionServiceProvider`. Navigation IDs are globally unique and duplicate
registrations are configuration errors. Visibility and authorization are checked for the
current request, never at registration.

## Navigation tree

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

## Navigation integration notes

Package Blade views should use the host `<x-app-layout>` components. Definitions must come
from trusted installed package code; do not use request input as a route, class or registry
definition.
