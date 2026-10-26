# Package frontend integration

Optional packages render their own Laravel Blade pages inside FreeAmir. The host supplies the layout,
shared components, translated navigation renderer, Tailwind/daisyUI build, and Alpine runtime.
The first consumer is `amir/invoice-categories`.

## Package-owned pages

Keep package views under `resources/views/` and translations under `lang/fa/` and `lang/en/`.
Register both from the package service provider:

```php
$this->loadViewsFrom(__DIR__.'/../resources/views', 'example');
$this->loadTranslationsFrom(__DIR__.'/../lang', 'example');
$this->loadRoutesFrom(__DIR__.'/../routes/web.php');
```

Render a namespaced view such as `view('example::index', $data)`. Reuse the host components:

```blade
<x-app-layout :title="__('example::messages.title')">
    <x-show-message-bags />
    <form method="POST" action="{{ route('example.store') }}">
        @csrf
        <x-input id="name" name="name" :title="__('Name')" required
            :value="old('name')" />
        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
    </form>
</x-app-layout>
```

Available host components include `x-input`, `x-textarea`, `x-button`, and `x-show-message-bags`.
Use translated text, escaped Blade output, semantic controls, responsive grids, and theme tokens such as
`bg-base-100` and `text-base-content`. Use complete Tailwind class strings; runtime string concatenation
cannot be detected reliably by the build. Put dynamic color values in validated hexadecimal style values,
never in arbitrary generated class names. Alpine is already loaded by the host; do not load it a second time.

## Management navigation

After its routes are registered, a package registers an item with the host:

```php
$this->app->make(\App\Extensions\NavigationRegistry::class)->register('example.categories', [
    'area' => 'navigation.management',
    'label' => 'example::messages.title',
    'route' => 'example.index',
    'permission' => 'access-super-admin-panel',
    'priority' => 100,
    'visible' => static fn (array $context): bool => $context['fiscal_year_id'] !== null,
]);
```

IDs are globally unique. Lower priorities appear first; equal priorities sort by ID. The host renders
`navigation.management` through `x-extension-navigation` in the shared desktop/mobile menu. The component
checks authentication, named-route existence, permission, and the visibility callback on each render. It
escapes translated labels and marks the current route with `aria-current="page"`.

Invoice features can register in `navigation.workspace.invoices` to appear in the Invoices menu.
The navigation area controls placement; each item's permission and endpoint authorization still apply.
The invoice-category package uses this area while retaining the existing management permission for its CRUD screen.

The callback receives `user` and `fiscal_year_id`. Do not capture the boot-time request or authenticated
user. Navigation visibility is presentation only: protect every endpoint separately with middleware,
Form Request authorization, and the host fiscal-year context. Hiding a menu does not authorize a request.

## Assets and local development

FreeAmir uses Tailwind v4 and daisyUI v5. `resources/css/app.css` explicitly scans `vendor/amir`, and
the Vite configuration preserves Laravel's default refresh paths while adding package Blade views.
Install Composer packages before building frontend assets. The same installed path is used by local
Composer symlinks and release archives; production builds do not require a sibling Enterprise checkout.

```bash
./vendor/bin/sail npm run dev
./vendor/bin/sail npm run build
```

When developing a symlinked package in Sail, enable the sibling mount as described in
[enterprise-development.md](enterprise-development.md). Rebuild after installing or removing a package
so the output reflects the installed views. New dependencies, standalone scripts, and custom asset
bundles need their own explicit integration contract; this checkpoint uses the existing shared bundle.

## Validation and verification

Keep controllers thin. Normalize inputs before uniqueness checks, validate with Form Requests, and scope
reads and writes to the selected fiscal year through `HostContext`. Check record ownership before checking
its closed-year state so requests cannot reveal information about records in another year. Call
`ensureFiscalYearOpen()` for writes and create/edit forms. Escape old input and persisted values.

Test endpoint permissions independently of menu visibility. Cover guests, unauthorized users, missing
year context, other-year IDs (including closed years), validation errors, and CRUD. Verify that removing
a category only removes its assignments and preserves invoice data. The invoice-category package has
separate actual-host tests under its repository's `integration/freeamir/`.

Run the host's empty-package and navigation suites:

```bash
./script/test-sail tests/Feature/Extensions tests/Unit/Extensions
```

Also build with and without the package installed, and inspect the page in the browser for Persian RTL,
English LTR, narrow layouts, menu visibility, form submission, and light/dark themes.

## Extension locations

The invoice form, detail, and filter slots are rendered by the current host. Package providers must still
register trusted views and validate all submitted payloads through `InvoiceExtensionRegistry`.
