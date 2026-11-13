#!/usr/bin/env bash
#
# Production deploy for Maison (Laravel 12 + Filament 5).
#
# Run on the production server, from the app directory:
#
#   ./deploy.sh                 # deploy the default branch (main)
#   BRANCH=release ./deploy.sh  # deploy another branch
#
# Options (environment variables):
#   BRANCH=main          git branch to deploy
#   SKIP_ASSETS=1        skip `npm ci && npm run build` (e.g. assets built in CI)
#   PHP=php              PHP binary (e.g. PHP=php8.3)
#   PHP_FPM_SERVICE=     php-fpm service to reload so OPcache picks up new code
#                        (e.g. PHP_FPM_SERVICE=php8.3-fpm). Empty = don't reload.
#
# Never seeds the database — demo seeders would overwrite live content.

set -Eeuo pipefail

BRANCH="${BRANCH:-main}"
SKIP_ASSETS="${SKIP_ASSETS:-0}"
PHP="${PHP:-php}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-}"

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"

MAINTENANCE=0
MIGRATED=0

log()  { printf '\n\033[1;34m==>\033[0m %s\n' "$*"; }
fail() {
    printf '\n\033[1;31mERROR:\033[0m %s\n' "$*" >&2
    trap - ERR
    on_error "${BASH_LINENO[0]}"
    exit 1
}

on_error() {
    local line=$1
    printf '\n\033[1;31mDeploy failed at line %s.\033[0m\n' "$line" >&2
    if [[ $MAINTENANCE -eq 1 ]]; then
        if [[ $MIGRATED -eq 1 ]]; then
            # Schema may no longer match the old code — keep the site down.
            echo "Migrations already ran, so the site stays in maintenance mode." >&2
            echo "Fix the problem, then re-run ./deploy.sh or run: $PHP artisan up" >&2
        else
            echo "Nothing touched the database; bringing the site back up." >&2
            "$PHP" artisan up || true
        fi
    fi
}
trap 'on_error $LINENO' ERR

# ---------------------------------------------------------------------------
log "Pre-flight checks"

[[ -f .env ]] || fail ".env is missing. Copy .env.example, fill in production values, run '$PHP artisan key:generate'."

env_value() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | tr -d '"'"'"; }

[[ "$(env_value APP_ENV)" == "production" ]] || fail "APP_ENV must be 'production' in .env."
[[ "$(env_value APP_DEBUG)" == "false" ]]     || fail "APP_DEBUG must be 'false' in .env."
[[ -n "$(env_value APP_KEY)" ]]               || fail "APP_KEY is empty. Run '$PHP artisan key:generate'."

command -v git >/dev/null      || fail "git not found."
command -v composer >/dev/null || fail "composer not found."
if [[ "$SKIP_ASSETS" != "1" ]]; then
    command -v npm >/dev/null  || fail "npm not found (or set SKIP_ASSETS=1)."
fi

# storage/ and bootstrap/cache/ get chmod'ed for the web server; that is not a code change.
git config core.fileMode false

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    git status --short --untracked-files=no
    fail "Tracked files were edited on the server. Commit or discard them before deploying."
fi

# ---------------------------------------------------------------------------
log "Fetching origin/$BRANCH"
git fetch --prune origin "$BRANCH"
PREVIOUS_COMMIT="$(git rev-parse HEAD)"
TARGET_COMMIT="$(git rev-parse "origin/$BRANCH")"

if [[ "$PREVIOUS_COMMIT" == "$TARGET_COMMIT" ]]; then
    echo "Already at $(git rev-parse --short HEAD); re-running build steps anyway."
fi

# ---------------------------------------------------------------------------
log "Entering maintenance mode"
"$PHP" artisan down --retry=15 --refresh=15 || true
MAINTENANCE=1

log "Checking out $BRANCH @ $(git rev-parse --short "$TARGET_COMMIT")"
git checkout -q "$BRANCH" 2>/dev/null || git checkout -q -b "$BRANCH" "origin/$BRANCH"
git merge --ff-only "origin/$BRANCH"

log "Installing PHP dependencies"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

if [[ "$SKIP_ASSETS" != "1" ]]; then
    log "Building front-end assets"
    npm ci --no-audit --no-fund
    npm run build
fi

log "Running migrations"
MIGRATED=1
"$PHP" artisan migrate --force

CLIENT_ISOLATION="$(env_value CLIENT_ISOLATION)"
if [[ "$CLIENT_ISOLATION" == "database" || "$CLIENT_ISOLATION" == "schema" ]]; then
    # Applies new database/migrations/client migrations to every client database.
    # --no-seed: existing client content is never re-seeded or overwritten.
    log "Migrating client databases"
    PROVISION_OUT="$(mktemp)"
    if ! "$PHP" artisan clients:provision --no-seed --no-interaction 2>&1 | tee "$PROVISION_OUT"; then
        if grep -q "No matching client" "$PROVISION_OUT"; then
            echo "No clients yet — nothing to migrate."
        else
            rm -f "$PROVISION_OUT"
            fail "clients:provision failed (see output above)."
        fi
    fi
    rm -f "$PROVISION_OUT"

    # Creates or updates each template's theme-<template> preview. It only seeds a
    # preview database the first time it is created, so existing previews keep their content.
    log "Building theme previews"
    "$PHP" artisan themes:build --no-interaction
else
    log "Skipping clients:provision and themes:build (CLIENT_ISOLATION='${CLIENT_ISOLATION:-shared}')"
fi

log "Linking storage"
[[ -e public/storage ]] || "$PHP" artisan storage:link

log "Rebuilding caches"
"$PHP" artisan optimize:clear
"$PHP" artisan optimize            # config, events, routes, views
"$PHP" artisan filament:optimize   # Filament components + Blade icons

log "Restarting queue workers"
"$PHP" artisan queue:restart

if [[ -n "$PHP_FPM_SERVICE" ]]; then
    log "Reloading $PHP_FPM_SERVICE"
    sudo systemctl reload "$PHP_FPM_SERVICE"
fi

log "Leaving maintenance mode"
"$PHP" artisan up
MAINTENANCE=0

trap - ERR
log "Deployed $(git rev-parse --short HEAD) (was $(git rev-parse --short "$PREVIOUS_COMMIT"))"
git --no-pager log --oneline "$PREVIOUS_COMMIT..HEAD" | head -20
