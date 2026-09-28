#!/bin/bash
#
# Build a distributable plugin zip.
#
#   npm run package
#
# Installs production dependencies, builds the webpack bundles, and archives
# only the files the plugin needs at runtime — never node_modules, tests or
# build configs.

set -e

cd "$(dirname "$0")"
cd ..

SLUG="radius-hotel-booking"

BLUE_BOLD='\033[1;34m'
GREEN_BOLD='\033[1;32m'
COLOR_RESET='\033[0m'

status () { echo -e "\n${BLUE_BOLD}$1${COLOR_RESET}\n"; }
success () { echo -e "\n${GREEN_BOLD}$1${COLOR_RESET}\n"; }

status "Installing dependencies…"
npm ci
composer install --no-dev --optimize-autoloader

status "Building assets…"
npm run build

# Source maps are dev-only.
find build -name "*.map" -type f -delete

status "Creating archive…"
rm -rf "$SLUG" "$SLUG.zip"
mkdir "$SLUG"

cp "$SLUG.php" readme.txt README.md "$SLUG/"
cp -r includes languages views build templates vendor "$SLUG/"
[ -d assets ] && cp -r assets "$SLUG/"

# Translator sources aren't read at runtime — .mo / .l10n.php / .json are.
find "$SLUG/languages" -type f \( -name '*.po' -o -name '*.pot' -o -name '*.po~' \) -delete 2>/dev/null || true

zip -r "$SLUG.zip" "$SLUG/"
rm -rf "$SLUG"

success "Built $SLUG.zip"
