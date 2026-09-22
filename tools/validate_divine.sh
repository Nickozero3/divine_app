#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "== PHP LINT =="
find src -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
echo "OK: PHP"

if command -v node >/dev/null 2>&1; then
  echo "== JAVASCRIPT SYNTAX =="
  find src -name '*.js' -print0 | while IFS= read -r -d '' f; do node --check "$f" >/dev/null; done
  echo "OK: JavaScript"
else
  echo "WARN: Node.js no disponible; se omite JS lint"
fi

echo "== REQUIRED SCHEMA MARKERS =="
grep -q "ENUM('admin', 'usuario', 'puerta', 'cajera', 'kioskito')" db/init.sql
grep -q "CREATE TABLE IF NOT EXISTS sync_operations" db/init.sql
grep -q "CREATE TABLE IF NOT EXISTS vip_sales" db/init.sql
grep -q "CREATE TABLE IF NOT EXISTS container_stock_items" db/init.sql
echo "OK: schema markers"

echo "== FORBIDDEN LEGACY USER ROLE =="
if grep -Eq "in_array\(\$role, \['admin'.*'kiosko'" src/api.php README.md; then
  echo "ERROR: legacy role still exposed in validation/docs"
  exit 1
fi
echo "OK: legacy role only supported for migration"
