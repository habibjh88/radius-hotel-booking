#!/usr/bin/env bash
#
# i18n-build.sh — compile .po files into the artifacts WordPress needs at runtime.
#
# For each locale you pass, this script:
#   1. msgfmt → languages/radius-hotel-booking-{LOCALE}.mo
#   2. wp i18n make-json → languages/radius-hotel-booking-{LOCALE}-{hash}.json (per-bundle)
#   3. wp i18n make-php  → languages/radius-hotel-booking-{LOCALE}.l10n.php
#
# Usage:
#   bin/i18n-build.sh es_ES                # one locale
#   bin/i18n-build.sh es_ES fr_FR de_DE    # several locales
#   bin/i18n-build.sh --all                # every PO file in languages/
#
# Designed to be called by the Claude `translator` subagent after it writes a .po file.

set -euo pipefail

PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LANG_DIR="${PLUGIN_ROOT}/languages"
TEXT_DOMAIN="radius-hotel-booking"

# log_info prints a labelled informational line to stdout.
log_info() {
	printf '\033[1;34m[i18n]\033[0m %s\n' "$*"
}

# log_warn prints a labelled warning line to stderr (non-fatal).
log_warn() {
	printf '\033[1;33m[i18n warn]\033[0m %s\n' "$*" >&2
}

# log_err prints a labelled error line to stderr.
log_err() {
	printf '\033[1;31m[i18n err]\033[0m %s\n' "$*" >&2
}

# require_msgfmt checks that the gettext `msgfmt` binary is on PATH and exits if not.
require_msgfmt() {
	if ! command -v msgfmt >/dev/null 2>&1; then
		log_err "msgfmt not found. Install gettext (macOS: brew install gettext; Debian: apt-get install gettext)."
		exit 1
	fi
}

# has_wp_cli returns 0 when WP-CLI is available, 1 otherwise. Used to decide whether to skip JSON/PHP steps.
has_wp_cli() {
	command -v wp >/dev/null 2>&1
}

# build_mo compiles a single locale's .po into a .mo via msgfmt.
build_mo() {
	local locale="$1"
	local po="${LANG_DIR}/${TEXT_DOMAIN}-${locale}.po"
	local mo="${LANG_DIR}/${TEXT_DOMAIN}-${locale}.mo"

	if [ ! -f "$po" ]; then
		log_err "Missing PO file: $po"
		return 1
	fi

	msgfmt --check --statistics --output-file="$mo" "$po"
	log_info "Wrote $(basename "$mo")"
}

# build_json invokes `wp i18n make-json` to produce JS-side translation sidecars next to the .po.
# The --no-purge flag keeps strings inside the .po (we use the PHP filter to merge JSON, see CLAUDE.md).
build_json() {
	local locale="$1"
	if ! has_wp_cli; then
		log_warn "wp-cli not available; skipping JSON for ${locale}. Install WP-CLI to regenerate."
		return 0
	fi
	wp i18n make-json "$LANG_DIR" --no-purge --pretty-print 1>/dev/null
	log_info "Wrote JSON sidecars for ${locale}"
}

# build_php invokes `wp i18n make-php` to produce the .l10n.php cache file WordPress 6.5+ loads first.
build_php() {
	local locale="$1"
	if ! has_wp_cli; then
		log_warn "wp-cli not available; skipping .l10n.php for ${locale}."
		return 0
	fi
	wp i18n make-php "$LANG_DIR" 1>/dev/null
	log_info "Wrote .l10n.php cache for ${locale}"
}

# patch_l10n_php_files injects an ABSPATH guard at the top of every generated
# `.l10n.php` file so they satisfy WordPress Plugin Check's
# `missing_direct_file_access_protection` rule. `wp i18n make-php` rewrites
# these files from scratch on every run, so the guard must be re-applied after
# each invocation. The check is idempotent — files that already carry the
# guard are left untouched.
patch_l10n_php_files() {
	local f tmp
	shopt -s nullglob
	for f in "${LANG_DIR}/${TEXT_DOMAIN}"-*.l10n.php; do
		if grep -q "defined( 'ABSPATH' )" "$f"; then
			continue
		fi
		tmp="$(mktemp)"
		awk 'NR==1 { print; print ""; print "if ( ! defined( '"'"'ABSPATH'"'"' ) ) { exit; }"; print ""; next } { print }' "$f" > "$tmp"
		mv "$tmp" "$f"
		log_info "Patched ABSPATH guard into $(basename "$f")"
	done
	shopt -u nullglob
}

# build_locale runs the full mo/json/php pipeline for one locale.
build_locale() {
	local locale="$1"
	log_info "Building ${locale}…"
	build_mo "$locale"
	build_json "$locale"
	build_php "$locale"
}

# discover_locales lists every locale that has a PO file in the languages directory.
discover_locales() {
	find "$LANG_DIR" -maxdepth 1 -type f -name "${TEXT_DOMAIN}-*.po" \
		| sed -E "s|.*/${TEXT_DOMAIN}-([^/]+)\.po$|\1|" \
		| sort -u
}

# main parses CLI arguments and dispatches to build_locale per target.
main() {
	require_msgfmt

	if [ "$#" -eq 0 ]; then
		log_err "Usage: $(basename "$0") <locale> [<locale> …]   or   --all"
		exit 2
	fi

	local locales=()
	if [ "$1" = "--all" ]; then
		while IFS= read -r l; do
			locales+=("$l")
		done < <(discover_locales)
		if [ "${#locales[@]}" -eq 0 ]; then
			log_err "No .po files found in $LANG_DIR"
			exit 1
		fi
	else
		locales=("$@")
	fi

	for locale in "${locales[@]}"; do
		build_locale "$locale"
	done

	patch_l10n_php_files

	log_info "Done."
}

main "$@"
