# Permissions and role assignment

[نسخه فارسی](permissions.md)

A permission determines which operation a user may perform. A role is a reusable set of permissions that you assign to users. Company and fiscal-year data scope is configured separately by selecting the user's fiscal years. These settings do not create accounting documents.

## Before you start

The current application places **Permissions** and **Roles** under the **Management Center** (`/management/permissions` and `/management/roles`). Opening this center requires `access-super-admin-panel`; each page operation also checks its own permission, such as `permissions.create` or `roles.update`. `roles.*` or `permissions.*` alone does not grant access to the Management Center. This guide describes the current screens; the `amir/user-management` package is not installed in this branch.

## Exact and wildcard permissions

| Permission | Access granted |
|---|---|
| `users.index` | Only the operation that checks this permission; it does not allow creating or editing users |
| `users.*` | All user operations, including listing, creating, editing, deleting, and impersonation; operation-specific rules still apply |
| `invoices.payments.*` | Invoice payment operations, including recording and editing; it does not grant role-management access |
| `invoices.*` | All invoice operations, including payments, ancillary costs, and future operations under this resource |
| `home` | The standalone home capability; it is different from `home.*`, which covers nested operations |

A wildcard does not deny a permission. If a user has `invoices.*`, removing `invoices.payments.update` from another role does not block payment editing. To restrict access, remove the broad permission from all of the user's roles and direct assignments, then grant only the exact permissions needed. Permissions from multiple roles and direct user permissions are combined.

## Permissions that must be added manually

On a fresh installation, `RolesAndPermissionsSeeder` creates resource wildcards such as `users.*`, `roles.*`, `permissions.*`, `activity-logs.*`, `products.*`, `companies.*`, `invoices.*`, and `reports.*` with the `web` guard. It also declares standalone `home`, `change-company`, `report`, `access-super-admin-panel`, `update-global-configs`, and `api.*`. The source of truth is `database/seeders/RolesAndPermissionsSeeder.php`.

Permission migrations no longer create or assign new permissions. For an existing installation, review and add these manually when a restricted role needs them:

- Check for `cheques.*`, `chequebooks.*`, and `budgets.*` in older installations; migrations no longer add them.
- A resource wildcard such as `products.*` covers new operations such as `products.recalculate-quantity`; no separate exact record is needed.
- For a restricted role, create the exact operation, for example `invoices.dashboard`, `invoices.bookmark`, or `invoices.sort-numbers`.
- Add `invoices.payments.update` for payment editing. `invoices.payments.store` only grants recording payments.
- Add `companies.closing-wizard.recalculate` for the closing workflow and `companies.test-moadian-connection` for testing the Moadian connection when a restricted role needs them.
- Review `reports.inventory-turnover` and `reports.inventory-turnover.pdf` for inventory reports. `reports.*` covers both.
- For activity history, use `activity-logs.index` and `activity-logs.details`, or `activity-logs.*`. For commercial ledgers, use the required `commercial-ledgers.index`, `store`, `show`, `download`, and `destroy` permissions, or `commercial-ledgers.*`.
- The standalone `report` permission is required for emailing reports, together with the permission for the source report or export. The old `send-to-email` name is not a replacement.
- When a new resource is added outside the existing wildcards, create its exact permission or resource wildcard and assign it only to the required roles.

These are examples, not a list to grant to every role. Keep `access-super-admin-panel` and `update-global-configs` limited to system administrators, and avoid a global `*` permission for working roles.

Deleting migration files does not delete permissions or assignments already stored in the database. Running the seeder again synchronizes the declared built-in roles and also creates demo users and employees. For an existing installation, make required changes from the management screens.

## Creating permissions, roles, and assigning users

1. With an authorized account, open **Management Center → Permissions** and choose **Add new Permission**. Enter the exact name, such as `invoices.payments.update`, without translating it. The name must be unique and 1–255 characters. Select at least one receiving role and save. The permission is stored with the `web` guard.
2. Open **Management Center → Roles** and choose **Add new Role**. The name must be 3–255 characters. Select the required permissions and save. You can create the role first and select it while creating a permission.
3. Open **Management → System → Users** and create or edit a user. Select at least one role and one fiscal year. An ordinary manager can assign only roles and fiscal years within their own scope; assigning `Super-Admin` is restricted to that role.
4. Saving a role replaces that role's previous permission selection. Saving a user replaces the user's previous role and fiscal-year assignments. Review existing selections before saving.
5. Test with an ordinary account: the required menu or button should be visible, the permitted operation should work, and operations without permission or without an assigned fiscal year should be rejected. Other rules such as document status and closed fiscal years still apply.

## How `@can` works

`@can('invoices.payments.update')` asks Laravel Gate whether the current user has that ability. With Spatie wildcard permissions enabled, it checks the exact permission and matching wildcards such as `invoices.payments.*` and `invoices.*`. `@can` does not create permissions or assign roles; when a matching wildcard exists, an exact database record is not required.

```blade
@can('invoices.payments.update')
    <button type="submit">Save payment changes</button>
@endcan
```

Pair view checks with server-side checks. Workspace routes use `check-permission`, which checks the route name or an explicitly configured permission. For a new capability, define the name checked by the view and the route, then assign it to the appropriate role. Use `@canany(['users.index', 'roles.index'])` when either of several permissions should reveal an option.

`Super-Admin` bypasses Gate permission checks in the current application, so do not use that role when testing denial. A resource wildcard does not grant another resource, access to the Management Center, or access to another fiscal year's data. Roles are global in the current configuration (`teams=false`); fiscal-year assignment remains a separate scope.

## If a button is missing or access is denied

Check the exact permission name and `web` guard, every role assigned to the user, matching wildcards, direct user permissions, and the user's fiscal-year assignments. Multi-step operations may require separate list, view, and final-operation permissions; for example, viewing an invoice uses `invoices.show`, while editing its payment uses `invoices.payments.update`. Management changes clear the permission cache. If a change made outside the application is not visible, an administrator can run `php artisan permission:cache-reset` and have the user reload the page.

[Users and fiscal-year guide](../users/users.en.md)
