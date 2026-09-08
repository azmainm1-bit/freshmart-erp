# Setup guide

## Quick demo

Install Docker with Compose v2 and Node.js 22 LTS. Use Linux, macOS, or WSL2 on Windows. Start Docker, open a terminal in `laravel/`, then run:

```bash
bin/setup-demo
bin/serve-dev.sh
```

The setup copies the example configuration, starts PostgreSQL, builds the PHP toolchain, installs locked dependencies, generates an application key, migrates and seeds the database, builds the frontend and checks reconciliation. The server remains in the foreground; press Ctrl+C to stop it.

The app is available at `http://localhost:8000`. Both published ports bind to your computer's loopback interface. The demo is not exposed to your LAN by default.

## Existing installation

`bin/setup-demo` intentionally stops when `.env` exists. To update an existing local checkout without reseeding:

```bash
cd laravel
docker compose up -d --wait postgres
docker compose build tooling
bin/run composer install --no-interaction --prefer-dist
bin/run php artisan migrate
npm ci
npm run build
bin/serve-dev.sh
```

Keep your existing `.env` and application key. Do not run `migrate:fresh` or delete Docker volumes to update an installation.

## Native PHP option

Use PHP 8.3+ with the extensions required by `composer check-platform-reqs`, including PostgreSQL PDO, mbstring, XML, bcmath and intl; install Composer 2 and Node.js 22.

From a fresh checkout's `laravel/` directory:

```bash
cp .env.example .env
docker compose up -d --wait postgres
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm ci
npm run build
php artisan serve
```

The example environment uses `127.0.0.1:55433` for native PHP. Container helpers override the connection to `postgres:5432`, the address inside Compose. Do not use the host port for container-to-container connections.

## Frontend development

Run `npm run dev` in one terminal and the PHP application server in another. For the simplest review experience, use `npm run build` and only the PHP server. Rebuild assets after changing frontend source.

## Troubleshooting

| Symptom | What to check |
| --- | --- |
| Environment file cannot be parsed | Start from `.env.example`; quote values containing spaces. |
| Database connection refused | Start PostgreSQL and wait for its health check. Use `127.0.0.1:55433` natively or `postgres:5432` inside Compose. |
| Port already allocated | Stop the service using that port or update the relevant Compose binding and local configuration together. |
| Blank page or missing assets | Run `npm ci` and `npm run build`; remove a stale `public/hot` file if the Vite development server was interrupted. |
| Permission denied for a helper | Run `chmod +x bin/run bin/setup-demo bin/serve-dev.sh`; ZIP extraction can lose executable bits. |
| Application-key error | For a new local installation, run `bin/run php artisan key:generate`. Preserve the key for existing data. |
| Test database missing | The initialization SQL runs only on the first database-volume initialization. Create `supermarket_erp_laravel_test` explicitly in an existing local PostgreSQL installation; do not delete your data volume to fix this. |

`docker compose stop postgres` stops the database while retaining data. `docker compose down` removes the local containers/network but retains the named database volume. Keep that volume if you want to retain your demo work.
