#!/usr/bin/env bash
# Local preview only. Use PHP-FPM and HTTPS for a real deployment.
set -euo pipefail
cd "$(dirname "$0")/.."
exec docker compose run --rm --no-deps --service-ports \
  --user "$(id -u):$(id -g)" tooling \
  php artisan serve --host=0.0.0.0 --port=8000
