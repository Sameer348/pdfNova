#!/usr/bin/env bash
# Builds pdfnova-hostinger.zip — the exact contents of public_html to upload.
#
#   cd pdfnova && bash build/package.sh
#
# What it does:
#   1. Copies public_html/ to a temporary folder.
#   2. Installs PHP libraries there with "composer install --no-dev" (fresh vendor/).
#   3. Leaves out: includes/config.php (your secrets), storage contents
#      (keeps the folders, .htaccess and .gitkeep), logs, .DS_Store files.
#   4. Zips it to pdfnova-hostinger.zip next to this project and lists checks.
#
# Needs: php 8.1+, composer, zip, rsync (all standard on macOS/Linux).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/pdfnova-hostinger.zip"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

for bin in php composer zip rsync; do
    command -v "$bin" >/dev/null || { echo "Missing tool: $bin" >&2; exit 1; }
done

echo "→ Copying files"
mkdir -p "$WORK/pkg/public_html"
rsync -a \
    --exclude '.DS_Store' \
    --exclude 'includes/config.php' \
    --exclude 'vendor/' \
    --exclude 'storage/uploads/*' --exclude 'storage/processed/*' \
    --exclude 'storage/temp/*' --exclude 'storage/logs/*' \
    "$ROOT/public_html/" "$WORK/pkg/public_html/"
# Keep the empty storage folders (and their protection files).
for d in uploads processed temp logs; do
    mkdir -p "$WORK/pkg/public_html/storage/$d"
    touch "$WORK/pkg/public_html/storage/$d/.gitkeep"
done
cp "$ROOT/composer.json" "$ROOT/composer.lock" "$WORK/pkg/"
# vendor/.htaccess denies web access to the libraries; keep it.
mkdir -p "$WORK/pkg/public_html/vendor"
[ -f "$ROOT/public_html/vendor/.htaccess" ] && cp "$ROOT/public_html/vendor/.htaccess" "$WORK/pkg/public_html/vendor/"

echo "→ Installing PHP libraries (no dev packages)"
composer install --working-dir="$WORK/pkg" --no-dev --optimize-autoloader --no-interaction --no-progress --quiet

echo "→ Checking the package"
fail=0
[ -f "$WORK/pkg/public_html/includes/config.php" ] && { echo "  ✗ config.php must not be in the package"; fail=1; }
[ -f "$WORK/pkg/public_html/includes/config.example.php" ] || { echo "  ✗ config.example.php missing"; fail=1; }
[ -f "$WORK/pkg/public_html/vendor/autoload.php" ] || { echo "  ✗ vendor/autoload.php missing"; fail=1; }
[ -f "$WORK/pkg/public_html/vendor/.htaccess" ] || { echo "  ✗ vendor/.htaccess missing"; fail=1; }
for f in .htaccess storage/.htaccess includes/.htaccess database/.htaccess cron/.htaccess; do
    [ -f "$WORK/pkg/public_html/$f" ] || { echo "  ✗ $f missing"; fail=1; }
done
left=$(find "$WORK/pkg/public_html/storage" -type f ! -name '.htaccess' ! -name '.gitkeep' | wc -l | tr -d ' ')
[ "$left" = 0 ] || { echo "  ✗ $left leftover files in storage/"; fail=1; }
while IFS= read -r f; do
    php -l "$f" >/dev/null 2>&1 || { echo "  ✗ PHP syntax error: ${f#$WORK/pkg/}"; fail=1; }
done < <(find "$WORK/pkg/public_html" -name '*.php' -not -path '*/vendor/*')
[ "$fail" = 0 ] || { echo "Package NOT created." >&2; exit 1; }

echo "→ Zipping"
rm -f "$OUT"
(cd "$WORK/pkg/public_html" && zip -qr -X "$OUT" . )
echo "✓ $OUT ($(du -h "$OUT" | cut -f1), $(unzip -l "$OUT" | tail -1 | awk '{print $2}') files)"
echo "  Upload it to public_html and extract it there. Then create includes/config.php (see HOSTINGER_DEPLOYMENT.md)."
