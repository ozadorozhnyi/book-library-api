# Book Library REST API

A small REST API for managing a library of books, built as a job-application
test task. The API exposes a single resource (`Book`) with full CRUD
semantics, validation, pagination, and an interactive Swagger UI.

## Tech stack

| Layer       | Choice                                                       |
| ----------- | ------------------------------------------------------------ |
| Language    | PHP **8.5** (in Docker), 8.3+ supported locally              |
| Framework   | Laravel **13.6**                                             |
| Database    | MySQL **8.4** LTS                                            |
| ORM         | Eloquent (Laravel built-in)                                  |
| API docs    | OpenAPI 3.0 via `darkaonline/l5-swagger` (PHP 8 attributes)  |
| Tests       | PHPUnit (SQLite `:memory:` for speed and isolation)          |
| Container   | Laravel Sail (Docker Compose)                                |

## Requirements

The recommended way is to run everything inside Docker — you only need a
container runtime on the host:

* **Docker** or **OrbStack** (drop-in replacement for Docker Desktop on macOS)
* **PHP 8.3+** locally — only needed once, to run `composer install` for the
  initial bootstrap. After that, every command is run inside the container.
* **Composer 2.x** — for the same one-time bootstrap step.

If you do not want to use Docker at all, you can run the API directly against
a local PHP 8.3+ and a MySQL 8.x server; see
[Running without Docker](#running-without-docker) below.

## Quick start

After cloning the repository and `cd`-ing into this directory:

```bash
# 1. Install PHP dependencies once on the host
composer install

# 2. Copy the environment template and generate an APP_KEY
cp .env.example .env
php artisan key:generate

# 3. Bring up the containers (PHP 8.5, MySQL 8.4) — the first run builds
#    the image and may take ~5–10 minutes
./vendor/bin/sail up -d

# 4. Bootstrap the application: migrate, seed sample data, regenerate Swagger
./vendor/bin/sail artisan app:setup --demo
```

The API is now available on `http://localhost:8080` and the interactive
Swagger UI on `http://localhost:8080/api/documentation`.

> **Tip:** add a shell alias for Sail so you can drop the `./vendor/bin/`
> prefix:
>
> ```bash
> alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'
> ```

## Local setup

### Environment variables

The defaults in `.env.example` are tuned for Sail and avoid colliding with
other projects on the same host:

| Variable               | Default          | Purpose                           |
| ---------------------- | ---------------- | --------------------------------- |
| `APP_PORT`             | `8080`           | HTTP port published on the host   |
| `FORWARD_DB_PORT`      | `33306`          | MySQL port published on the host  |
| `COMPOSE_PROJECT_NAME` | `bookapi`        | Container/network name prefix     |
| `DB_DATABASE`          | `book_library`   | MySQL database name               |
| `DB_USERNAME`          | `sail`           | MySQL user                        |
| `DB_PASSWORD`          | `password`       | MySQL password (dev only)         |

Override any of these in `.env` if your machine already uses those ports.

### Running with Docker (Sail)

```bash
./vendor/bin/sail up -d            # start the stack in the background
./vendor/bin/sail down             # stop and remove containers
./vendor/bin/sail down -v          # also drop the MySQL volume (clean slate)
./vendor/bin/sail logs -f          # tail container logs
./vendor/bin/sail shell            # bash inside the application container
./vendor/bin/sail mysql            # MySQL CLI connected to the project DB
```

Two containers run under the `bookapi-` prefix:

* `bookapi-laravel.test-1` — PHP-FPM + Nginx, exposed on `localhost:8080`
* `bookapi-mysql-1`        — MySQL 8.4, exposed on `localhost:33306`

### Running without Docker

If you have PHP 8.3+, Composer and a MySQL server on the host:

```bash
composer install
cp .env.example .env

# Point Laravel at your local MySQL
sed -i '' 's/DB_HOST=mysql/DB_HOST=127.0.0.1/' .env

php artisan key:generate
php artisan app:setup --demo
php artisan serve --port=8080
```

The rest of the commands below are identical — just drop the `sail` prefix.

## API endpoints

All endpoints are versioned under `/api/v1`. The full machine-readable
contract lives at `/docs`; the human-readable one at `/api/documentation`.

| Method | Path                     | Description                                      |
| ------ | ------------------------ | ------------------------------------------------ |
| GET    | `/api/v1/books`          | Paginated list (`?page=`, `?per_page=` 1–100)   |
| POST   | `/api/v1/books`          | Create a book                                    |
| GET    | `/api/v1/books/{book}`   | Fetch a book by id                               |
| PUT    | `/api/v1/books/{book}`   | Update (full or partial)                         |
| PATCH  | `/api/v1/books/{book}`   | Update (full or partial)                         |
| DELETE | `/api/v1/books/{book}`   | Delete                                           |

### Quick `curl` examples

```bash
# List with custom pagination
curl -s "http://localhost:8080/api/v1/books?per_page=5"

# Show one
curl -s "http://localhost:8080/api/v1/books/1"

# Create
curl -s -X POST "http://localhost:8080/api/v1/books" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "The Hobbit",
    "publisher": "Allen & Unwin",
    "author": "J. R. R. Tolkien",
    "genre": "Fantasy",
    "publication_date": "1937-09-21",
    "word_count": 95022,
    "price_usd": 14.99
  }'

# Patch
curl -s -X PATCH "http://localhost:8080/api/v1/books/1" \
  -H "Content-Type: application/json" \
  -d '{"price_usd": 19.99}'

# Delete
curl -s -X DELETE "http://localhost:8080/api/v1/books/1"
```

### Error envelope

* `200 / 201 / 204` for success
* `404 { "message": "…" }` — neutral message; never leaks model class names
* `422 { "message": "…", "errors": { field: [string, …] } }` — validation
* `405 { "message": "…" }` — wrong HTTP method on a route
* `500 { "message": "Server error" }` — internals never reach the client

## API documentation (Swagger)

The OpenAPI 3.0 specification is generated from PHP 8 attributes on the
controller, the resource, and `App\OpenApi\OpenApiSpec`. Two URLs:

* **`http://localhost:8080/api/documentation`** — interactive Swagger UI;
  every operation has a *Try it out* button that hits the live API.
* **`http://localhost:8080/docs`** — raw OpenAPI 3.0 JSON; useful for
  generating client SDKs or piping into other tools.

### Regenerating after attribute changes

```bash
# Just the docs
./vendor/bin/sail artisan l5-swagger:generate

# Or implicitly via app:setup (regenerates by default)
./vendor/bin/sail artisan app:setup
```

The generated artefact is committed to the repository at
`storage/api-docs/api-docs.json`, so the UI works on a fresh clone without a
build step.

### Peeking at the raw JSON

```bash
curl -s http://localhost:8080/docs | python3 -m json.tool | head -30
```

## Available artisan commands

```bash
# Bootstrap helper — see "app:setup details" below
./vendor/bin/sail artisan app:setup [--fresh] [--seed] [--demo] [--no-swagger]

# Standard Laravel commands worth knowing
./vendor/bin/sail artisan about              # framework / env summary
./vendor/bin/sail artisan migrate:status     # which migrations have run
./vendor/bin/sail artisan route:list         # registered routes
./vendor/bin/sail artisan tinker             # REPL with the app booted
./vendor/bin/sail artisan l5-swagger:generate  # regenerate Swagger only
```

### `app:setup` details

A single command that brings the application to a known good state — runs
migrations, optionally seeds, and regenerates the OpenAPI document.

| Flag           | Effect                                                      |
| -------------- | ----------------------------------------------------------- |
| (no flag)      | Run pending migrations, regenerate Swagger.                 |
| `--fresh`      | Drop every table then migrate from scratch (destructive).  |
| `--seed`       | Run database seeders (10 fake books).                       |
| `--demo`       | Shorthand for `--fresh --seed` — clean reset with sample data. |
| `--no-swagger` | Skip the Swagger regeneration step.                         |

Examples:

```bash
# First-time bootstrap on a clean clone
./vendor/bin/sail artisan app:setup --demo

# Pull a hot fix that adds a migration; safe to run any number of times
./vendor/bin/sail artisan app:setup

# CI / scripted contexts where Swagger is regenerated separately
./vendor/bin/sail artisan app:setup --no-swagger
```

## Running tests

The suite (145 tests, ~350 assertions) runs against SQLite `:memory:` and
finishes in ~5 seconds.

```bash
# All tests
./vendor/bin/sail artisan test
./vendor/bin/sail artisan test --parallel              # parallelise (Paratest)
./vendor/bin/sail artisan test --coverage              # with coverage (PCOV / Xdebug)
./vendor/bin/sail artisan test --coverage --min=80     # fail if coverage < 80 %

# Pick a suite
./vendor/bin/sail artisan test --testsuite=Unit
./vendor/bin/sail artisan test --testsuite=Feature

# A single class or method
./vendor/bin/sail artisan test tests/Feature/Api/Books/ListBooksTest.php
./vendor/bin/sail artisan test --filter=ListBooksTest
./vendor/bin/sail artisan test --filter='ListBooksTest::test_default_per_page_is_20'

# A whole sub-tree
./vendor/bin/sail artisan test tests/Feature/Api/Books/
./vendor/bin/sail artisan test tests/Unit/Http/Requests/

# PHPUnit directly (finer control)
./vendor/bin/sail bin phpunit
./vendor/bin/sail bin phpunit --filter=ListBooksTest
./vendor/bin/sail bin phpunit --testdox                # human-readable output
./vendor/bin/sail bin phpunit --stop-on-failure        # halt on first red

# Stop early
./vendor/bin/sail artisan test --stop-on-failure
```

### Test layout

```
tests/
├── Unit/
│   ├── Console/SetupCommandTest.php
│   ├── Enums/PaginationSizeTest.php
│   ├── Exceptions/ApiExceptionRendererTest.php
│   ├── Http/Requests/{Store,Update}BookRequestTest.php
│   ├── Http/Resources/BookResourceTest.php
│   └── Models/BookTest.php
└── Feature/
    ├── Api/Books/{List,Show,Create,Update,Delete,MethodNotAllowed}BookTest.php
    ├── Documentation/SwaggerEndpointsTest.php
    └── Routing/ApiRoutesTest.php
```

## Project structure

```
src/
├── app/
│   ├── Console/Commands/SetupCommand.php   # app:setup
│   ├── Enums/PaginationSize.php            # page-size policy + clamp()
│   ├── Exceptions/ApiExceptionRenderer.php # invokable JSON error renderer
│   ├── Http/
│   │   ├── Controllers/Api/BookController.php
│   │   ├── Requests/{Store,Update}BookRequest.php
│   │   └── Resources/BookResource.php
│   ├── Models/Book.php
│   └── OpenApi/OpenApiSpec.php             # top-level OpenAPI metadata
├── bootstrap/app.php                        # routing + exception wiring
├── compose.yaml                             # Sail / Docker Compose
├── config/l5-swagger.php
├── database/{factories,migrations,seeders}/
├── routes/{api,web,console}.php
├── storage/api-docs/api-docs.json           # generated, committed
└── tests/
```

## Notable design decisions

* **`/api/v1` prefix** from the start — versioning is cheap to add now and
  expensive to retrofit.
* **`decimal(10, 2)` + `decimal:0,2` validation rule** for `price_usd` —
  `numeric` would silently accept `19.999` and the column would truncate it.
* **`ApiExceptionRenderer`** rewrites or hides Laravel-generated 404
  messages (`No query results for model …`) so the API never leaks model
  class names, even with `APP_DEBUG=true`. Validation errors are deferred
  to Laravel's default renderer to keep the standard `errors{}` shape.
* **Page-out-of-range → 404**: `?page=2` against a one-page result is a
  client error, not an empty 200.
* **No `install:api` / Sanctum** — authentication is out of scope; adding
  it would have been a YAGNI violation.
* **Form requests are independent (no abstract base)** — duplication of
  seven rule lines is a smaller cost than the indirection of a Template
  Method base class for two implementations.
