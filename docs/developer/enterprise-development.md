# Developing optional packages with Sail

This workflow supports GitLab issue #445 without making optional Enterprise packages a dependency of
the FreeAmir installation. Keep the checkouts beside each other:

```text
work/
  FreeAmir/
  amir-enterprise/
    packages/
```

## Mount the package checkout

`docker-compose.enterprise.yml` adds only a bind mount to the application service. It is opt-in and is
not loaded by a normal `sail up`. It never mounts the Docker socket or changes database volumes.
Compose fails when the source checkout is absent rather than creating an empty directory.

For a one-off check without restarting the running application:

```bash
docker compose -f docker-compose.yml -f docker-compose.enterprise.yml \
  run --rm --no-deps --entrypoint php laravel.test \
  -r 'exit(is_file("/var/www/amir-enterprise/composer.json") ? 0 : 1);'
```

To use the mount during normal development, set the following in your local `.env` and run Sail again:

```dotenv
SAIL_FILES=docker-compose.yml:docker-compose.enterprise.yml
```

```bash
./vendor/bin/sail up -d
```

For a different checkout location, set `AMIR_ENTERPRISE_PATH` to that checkout's absolute host path.
The container destination remains `/var/www/amir-enterprise`. This path is also a sibling of the application
root `/var/www/html`, so the relative Composer repository URL `../amir-enterprise/packages/*` works
both in Sail and with the default sibling checkout on the host.

Keep `SAIL_FILES` local; CI and deployments must not require a sibling checkout. A local path repository
is enabled only after the target package exists. Local `dev-local` requirements and path-based lock entries
must not be committed as production dependencies. Release verification uses an archive or published version.

## Run host tests without using the development database

Compose can inject the development `DB_CONNECTION` and `DB_DATABASE` into the application container.
PHPUnit's default XML environment settings do not override existing environment values. Use the helper
below for local SQLite tests:

```bash
./script/test-sail tests/Unit/Extensions tests/Feature/Extensions
./script/test-sail --filter 'InvoiceBookmarkTest|InvoiceGroupActionTest|BeginningInventoryTest|FiscalYearControllerTest|FiscalYearClosingRecalculationTest'
```

The helper overrides the test process environment to `APP_ENV=testing`, `DB_CONNECTION=sqlite`, and
`DB_DATABASE=:memory:`. It also uses an absent config-cache path in a temporary directory so a cached
development configuration cannot override the test database. The directory is removed after PHPUnit exits.
Arguments are forwarded unchanged to the repository-pinned PHPUnit binary inside Sail. No `.env`, database
volume, or existing configuration cache is modified.

This is a SQLite runner, not MariaDB verification. Schema and database-specific behavior must additionally
be checked against a dedicated MariaDB test database with credentials that cannot access development data.
The project's existing CI configuration is not changed by this helper.

## Run the Enterprise skeleton tests

After the sibling mount is active, the Enterprise checkout uses its own Composer lock and vendor directory:

```bash
./vendor/bin/sail run sh -c 'cd /var/www/amir-enterprise && composer install --no-interaction'
./vendor/bin/sail run sh -c 'cd /var/www/amir-enterprise && composer run validate-packages && composer test'
```

These are the existing package-skeleton checks. They do not prove that invoice categories are implemented.
The package-specific and host integration suites are added in the later checkpoints documented in
[the extension API](extensions.md).
