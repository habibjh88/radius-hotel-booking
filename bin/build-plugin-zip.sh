#!/bin/bash
#
# Build a distributable plugin zip.
#
#   bun run package
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

status "Checking for WooCommerce dependencies…"
bash ./bin/check-woocommerce-free.sh

status "Installing dependencies…"
bun install --frozen-lockfile
composer install --no-dev --optimize-autoloader

status "Building assets…"
bun run build

status "Checking the public bundle size…"
bash ./bin/check-bundle-size.sh

# Source maps are dev-only.
find build -name "*.map" -type f -delete

status "Creating archive…"
# Stage and zip inside dist/ (git-ignored) so the plugin root stays clean.
DIST="dist"
mkdir -p "$DIST"
rm -rf "$DIST/$SLUG" "$DIST/$SLUG.zip"
STAGE="$DIST/$SLUG"
mkdir "$STAGE"

cp "$SLUG.php" uninstall.php readme.txt README.md composer.json "$STAGE/"
cp -r includes languages views build templates vendor "$STAGE/"
[ -d assets ] && cp -r assets "$STAGE/"

# The WP-CLI "artisan" generators are development tools: they write PHP into
# the plugin folder and need resources/stubs, which is not shipped. The command
# registry skips classes that are missing, and composer.json keeps them out of
# the optimized classmap.
rm -rf "$STAGE/includes/Commands"

# No hidden files (.DS_Store, .gitkeep …): Plugin Check rejects them.
find "$STAGE" -name '.*' -not -name '.' -exec rm -rf {} + 2>/dev/null || true

# Languages: ship only the template (.pot), which Loco Translate starts from.
# No bundled locale files: translations come from a translation plugin or
# translate.wordpress.org (ADR-019), and the M00 fr_FR scaffold covers only a
# fraction of the strings, so it would show a half-French screen.
find "$STAGE/languages" -type f ! -name '*.pot' ! -name 'index.php' -delete 2>/dev/null || true

# Zip from inside dist/ so the archive root is "$SLUG/", as WordPress expects.
( cd "$DIST" && zip -rq "$SLUG.zip" "$SLUG/" )
rm -rf "$STAGE"

success "Built $DIST/$SLUG.zip"
