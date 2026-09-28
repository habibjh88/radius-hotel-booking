#!/usr/bin/env bash
#
# check-woocommerce-free.sh — fail if the plugin depends on WooCommerce
# (feature 18.6: the hotel stack runs with WooCommerce deactivated or absent).
#
#   npm run check:woocommerce
#
# Rejected: calling WooCommerce (wc_*() functions, WC(), WC_* classes),
# firing its hooks, importing its JS packages or globals, and requiring it in
# the plugin header.
#
# Allowed: listening to a WooCommerce hook with add_filter()/add_action().
# Such a callback only runs when WooCommerce is installed next to the plugin
# (e.g. PermissionsManager keeps WooCommerce from locking staff out of
# wp-admin), so it is coexistence, not a dependency.
#
# Also run by bin/build-plugin-zip.sh, so a release cannot ship a dependency.

set -uo pipefail

cd "$(dirname "$0")/.."

PATHS=(includes src templates views radius-hotel-booking.php uninstall.php)

# pattern|reason
RULES=(
	'\bwc_[a-z0-9_]+[[:space:]]*\(|calls a wc_*() function'
	'\bWC[[:space:]]*\([[:space:]]*\)|calls WC()'
	'\bWC_[A-Za-z0-9_]+|uses a WC_* class'
	'class_exists[[:space:]]*\([[:space:]]*['"'"'"]\\?WooCommerce|gates on the WooCommerce class'
	'(do_action|apply_filters)[[:space:]]*\([[:space:]]*['"'"'"]woocommerce_|fires a WooCommerce hook'
	'@woocommerce/|imports a WooCommerce JS package'
	'\bwindow\.wc\b|\bwcSettings\b|reads a WooCommerce JS global'
	'Requires Plugins:.*woocommerce|requires WooCommerce in the plugin header'
)

found=0
for rule in "${RULES[@]}"; do
	pattern="${rule%|*}"
	reason="${rule##*|}"
	hits="$(grep -rnIE --include='*.php' --include='*.js' --include='*.jsx' "$pattern" "${PATHS[@]}" 2>/dev/null)"
	if [ -n "$hits" ]; then
		found=1
		printf '\033[1;31m[woocommerce]\033[0m %s:\n%s\n\n' "$reason" "$hits" >&2
	fi
done

if [ "$found" -ne 0 ]; then
	printf '\033[1;31m[woocommerce]\033[0m The plugin must work without WooCommerce (feature 18.6).\n' >&2
	exit 1
fi

printf '\033[1;32m[woocommerce]\033[0m No WooCommerce dependency found.\n'
