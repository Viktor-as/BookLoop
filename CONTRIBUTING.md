# Contributing

## Running tests

PHPUnit is configured in `phpunit.dist.xml` with the **test** environment (`APP_ENV=test`), **DAMA Doctrine Test Bundle** (transaction rollback per test), and split **testsuites**: `Unit`, `Integration`, `Functional`, `Smoke`.

### Database and JWT (integration, functional, smoke HTTP)

1. Create a **dedicated MySQL database** for tests (do not use your dev database).
2. In `.env.test` or `.env.test.local`, set `DATABASE_URL` to that database.
3. Copy the same **JWT variables** you use for development (`JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE`) into the test env so `/api/auth/login` can issue cookies. Paths must resolve in the test environment.
4. Apply the schema once:

   ```bash
   APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
   ```

5. Run the full suite:

   ```bash
   vendor/bin/phpunit
   ```

   Or a single layer:

   ```bash
   vendor/bin/phpunit --testsuite Unit
   vendor/bin/phpunit --testsuite Integration
   vendor/bin/phpunit --testsuite Functional
   vendor/bin/phpunit --testsuite Smoke
   ```

The **Unit** suite does not use the database or HTTP kernel and is suitable for CI when no MySQL instance is available.

### JWT cookies in tests

BrowserKit uses `http://localhost`. Test env enables **non-secure** JWT cookies via `config/packages/test/lexik_jwt_authentication.yaml` so the `BEARER` cookie is sent after `POST /api/auth/login`.

## CI

The GitHub Actions workflow runs the **Unit** testsuite on push and pull request. Full layers require secrets or a job-level `DATABASE_URL` and migrations.
