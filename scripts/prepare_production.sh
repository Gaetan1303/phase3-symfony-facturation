#!/usr/bin/env bash
set -euo pipefail

# prepare_production.sh
# Idempotent script to prepare this Symfony app for production deployment.
# Run on the server as the application user (not root) after pulling code.

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$ROOT_DIR"

echo "Running production preparation in $ROOT_DIR"

if [ -z "${APP_ENV:-}" ]; then
  echo "APP_ENV not set; defaulting to 'prod'"
  export APP_ENV=prod
fi

echo "Installing PHP dependencies (no-dev, optimized)"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "Dumping environment (if using symfony/flex)"
if command -v composer >/dev/null 2>&1 && php -r "if (file_exists('composer.json')) exit(0); exit(1);"; then
  composer dump-env prod || true
fi

if [ -f package.json ]; then
  if command -v npm >/dev/null 2>&1; then
    echo "Installing node modules and building assets"
    npm ci --no-audit --no-progress
    if npm run build --silent; then
      echo "Assets built"
    else
      echo "No build script or build failed; skipping asset build"
    fi
  else
    echo "npm not found; skipping assets build"
  fi
fi

echo "Clearing and warming up cache"
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod --no-debug

echo "Running database migrations (ensure DB credentials are set in env)"
php bin/console doctrine:migrations:migrate --no-interaction --env=prod || true

echo "Setting permissions for var and public (adjust for your server user)"
if id -u www-data >/dev/null 2>&1; then
  chown -R www-data:www-data var public || true
fi

echo "Production preparation complete. Review DEPLOYMENT.md for next steps."
