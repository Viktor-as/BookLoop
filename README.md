# ReadLoop — books service

Symfony 7 application: REST API (API Platform), Doctrine ORM, Twig UI, Lexik JWT, Asset Mapper.

## Run with Docker (production-style demo)

The stack is tuned for **someone who only wants to run and try the app** (no bind-mounted source code, `APP_ENV=prod`, `APP_DEBUG=0`, Composer `--no-dev`, OPcache tuned for production).

Prerequisites: Docker Desktop (or Docker Engine + Docker Compose v2).

`compose.yaml` uses **`env_file: .env.example`**. The file is **committed**, so a clone is runnable with no copy step.

```bash
docker compose up -d --build
```

That command:

- builds an image with the full app, optimized Composer autoloader, warmed prod cache, and compiled front-end assets,
- starts MySQL 8 and creates both `readloop` and `readloop_test` for user `appuser` / `apppass`,
- starts phpMyAdmin,
- on first boot the `app` container generates JWT keys, runs migrations on **`readloop`**, loads **AppFixtures** into **`readloop`** once, then warms the cache again.

Persistent data on disk (under **`.docker_data/`**, gitignored):

| Path                 | Contents                                     |
| -------------------- | -------------------------------------------- |
| `.docker_data/mysql` | MySQL data directory                         |
| `.docker_data/var`   | Symfony `var/` (cache, logs, fixture marker) |
| `.docker_data/jwt`   | Lexik JWT key pair                           |

URLs:

| URL                   | Purpose                   |
| --------------------- | ------------------------- |
| http://localhost:8080 | Application               |
| http://localhost:8081 | phpMyAdmin (as `appuser`) |
| `localhost:3307`      | MySQL from the host       |

**Sign in (web UI).** The database is filled from **`AppFixtures`**. Use the login/registration area at the app base URL. Demo accounts (same for any environment after loading fixtures):

| Email                | Password      | Notes                       |
| -------------------- | ------------- | --------------------------- |
| `admin@admin.com`    | `Admin12345`  | Admin — overdue lists, etc. |
| `member@example.com` | `Member12345` | Member — standard borrowing |

**Security (important for a real deployment):** change **`APP_SECRET`**, MySQL credentials, **`JWT_PASSPHRASE`**, and related values in **`.env.example`**, and set **`JWT_COOKIE_SECURE=1`** when the site is served over HTTPS (the committed demo uses `JWT_COOKIE_SECURE=0` so cookies work on plain `http://localhost`). **`CORS_ALLOW_ORIGIN`** is set in **`compose.yaml`** (next to the `app` service) so the regex’s trailing `$` is not broken by env-file parsing.

Optional environment variables on the `app` service:

| Variable        | Default | Meaning                                                 |
| --------------- | ------- | ------------------------------------------------------- |
| `SEED_FIXTURES` | `1`     | Set to `0` to skip loading `AppFixtures` on first boot. |

The production image **does not** include PHPUnit or dev-only Composer packages. **Run the app and database without Docker** (or start only MySQL from Compose and use PHP on the host), `composer install` (with dev), create **`readloop_test`**, run migrations for dev and test envs as in [Run without Docker](#run-without-docker), then **`php bin/phpunit -c phpunit.dist.xml`**.

## Run without Docker

You need PHP 8.2+, Composer, and MySQL 8. **`.env.example` is built for the Compose stack** (`DATABASE_URL` uses `mysql` as the host and prod-style settings). For local PHP:

1. `cp .env.example .env.local` and set **`DATABASE_URL`** to your MySQL (e.g. `127.0.0.1:3307` if the DB runs from compose), plus **`APP_ENV=dev`**, **`APP_DEBUG=1`**, and as needed **`JWT_COOKIE_SECURE=1`**, **`DEFAULT_URI`**, and CORS for your dev origin (see `config/packages/nelmio_cors.yaml`).
2. `composer install`
3. `php bin/console lexik:jwt:generate-keypair`
4. Create database `readloop` (and, if you will run PHPUnit, `readloop_test` as well) for your user.
5. `php bin/console doctrine:migrations:migrate -n`
6. If you use a test database: `APP_ENV=test php bin/console doctrine:migrations:migrate -n` (or set `DATABASE_URL` in **`.env.test`** and run the same for that URL).
7. `php bin/console doctrine:fixtures:load -n`
8. `composer dev:start` (or point your web server at `public/`).

## Project layout

See `.cursor/rules/project-directory-structure.mdc` for the source map (controllers, entities, fixtures, etc.).
