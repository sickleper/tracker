#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SHARED_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

if [ "$#" -lt 4 ]; then
  echo "Usage: $0 <app-name> <app-url> <api-url> <tenant-slug> [primary|tenant]"
  exit 1
fi

APP_NAME="$1"
APP_URL="${2%/}"
API_URL="${3%/}"
TENANT_SLUG="$4"
APP_MODE="${5:-tenant}"

case "$APP_MODE" in
  primary)
    IS_PRIMARY_APP="1"
    ;;
  tenant)
    IS_PRIMARY_APP="0"
    ;;
  *)
    echo "ERROR: app mode must be 'primary' or 'tenant'"
    exit 1
    ;;
esac

mkdir -p "$SCRIPT_DIR/storage"

cat > "$SCRIPT_DIR/storage/app_bootstrap.local.json" <<EOF
{
    "app_name": "$APP_NAME",
    "app_url": "$APP_URL",
    "laravel_api_url": "$API_URL",
    "default_tenant": "$TENANT_SLUG",
    "is_primary_app": "$IS_PRIMARY_APP"
}
EOF

if [ ! -L "$SCRIPT_DIR/dist" ] && [ ! -e "$SCRIPT_DIR/dist" ]; then
  ln -s "$SHARED_ROOT/dist" "$SCRIPT_DIR/dist"
fi

echo "Wrote $SCRIPT_DIR/storage/app_bootstrap.local.json"
echo "Tenant app bootstrap complete."
