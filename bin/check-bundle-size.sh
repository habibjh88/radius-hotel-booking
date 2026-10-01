#!/usr/bin/env bash
#
# check-bundle-size.sh — fail if the public booking bundle grows past its budget
# (M04, 4.10: the guest pages must stay fast on a phone on 3G).
#
#   bun run check:bundle-size
#
# Budget: build/site.js + build/site.css, gzipped, at most 150 KB together.
# Override for one run with RTBP_SITE_BUDGET_KB=…. Run after a build; also run
# by bin/build-plugin-zip.sh, so a release cannot ship an oversized bundle.

set -uo pipefail

cd "$(dirname "$0")/.."

BUDGET_KB="${RTBP_SITE_BUDGET_KB:-150}"
FILES=(build/site.js build/site.css)

total=0
for file in "${FILES[@]}"; do
	if [ ! -f "$file" ]; then
		printf '\033[1;31m[bundle-size]\033[0m %s is missing — run the build first.\n' "$file" >&2
		exit 1
	fi
	bytes="$(gzip -9 -c "$file" | wc -c | tr -d ' ')"
	total=$((total + bytes))
	printf '  %-16s %6.1f KB gzip\n' "$file" "$(echo "$bytes / 1024" | bc -l)"
done

total_kb="$(echo "$total / 1024" | bc -l)"
if [ "$total" -gt $((BUDGET_KB * 1024)) ]; then
	printf '\033[1;31m[bundle-size]\033[0m Public bundle is %.1f KB gzip, over the %d KB budget.\n' "$total_kb" "$BUDGET_KB" >&2
	exit 1
fi

printf '\033[1;32m[bundle-size]\033[0m Public bundle is %.1f KB gzip (budget %d KB).\n' "$total_kb" "$BUDGET_KB"
