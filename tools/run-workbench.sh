#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [ ! -f "vendor/autoload.php" ]; then
	echo "vendor/autoload.php missing. Running composer install..."
	composer install --no-interaction --no-progress
fi

echo "Refreshing Composer autoload..."
composer dump-autoload --no-interaction --quiet

URL="http://localhost:8000"

if command -v xdg-open >/dev/null 2>&1; then
	(xdg-open "$URL" >/dev/null 2>&1 &) || true
elif command -v open >/dev/null 2>&1; then
	(open "$URL" >/dev/null 2>&1 &) || true
fi

echo "Starting Hicurl workbench at $URL"
php -S localhost:8000 -t tools
