#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SHARED_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

git_in_repo() {
  git -c safe.directory="$SCRIPT_DIR" -C "$SCRIPT_DIR" "$@"
}

usage() {
  echo "Usage: $0 <target-dir> <app-name> <app-url> <api-url> <tenant-slug> [primary|tenant] [branch]"
  echo "Example: $0 trackers-acme \"Acme Tracker\" \"https://acme.example.com\" \"https://api.example.com\" acme tenant main"
}

reset_runtime_dirs() {
  local target_dir="$1"

  mkdir -p \
    "$target_dir/backup/backups" \
    "$target_dir/fuel/uploads" \
    "$target_dir/local_sheets" \
    "$target_dir/storage/deploy_logs"

  find "$target_dir/backup/backups" -mindepth 1 -delete
  find "$target_dir/fuel/uploads" -mindepth 1 -delete
  find "$target_dir/local_sheets" -mindepth 1 -delete
  find "$target_dir/storage/deploy_logs" -mindepth 1 -delete
}

if [ "$#" -lt 5 ]; then
  usage
  exit 1
fi

TARGET_INPUT="$1"
APP_NAME="$2"
APP_URL="${3%/}"
API_URL="${4%/}"
TENANT_SLUG="$5"
APP_MODE="${6:-tenant}"
BRANCH="${7:-}"

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

if ! git_in_repo rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "ERROR: $SCRIPT_DIR is not a Git checkout"
  exit 1
fi

if [ -n "$(git_in_repo status --porcelain)" ]; then
  echo "ERROR: Source checkout is dirty."
  echo "Commit or stash local changes before provisioning a tenant clone."
  exit 1
fi

SOURCE_BRANCH="$(git_in_repo rev-parse --abbrev-ref HEAD)"
if [ -z "$BRANCH" ]; then
  BRANCH="$SOURCE_BRANCH"
fi

if [ "$BRANCH" = "HEAD" ]; then
  echo "ERROR: Source checkout is detached. Pass an explicit branch name."
  exit 1
fi

if [[ "$TARGET_INPUT" = /* ]]; then
  TARGET_DIR="$TARGET_INPUT"
else
  TARGET_DIR="$SHARED_ROOT/$TARGET_INPUT"
fi

TARGET_DIR="${TARGET_DIR%/}"
TARGET_PARENT="$(dirname "$TARGET_DIR")"

if [ "$TARGET_PARENT" != "$SHARED_ROOT" ]; then
  echo "ERROR: Tenant clones must live directly under $SHARED_ROOT"
  exit 1
fi

if [ -e "$TARGET_DIR" ]; then
  echo "ERROR: Target already exists: $TARGET_DIR"
  exit 1
fi

ORIGIN_URL="$(git_in_repo remote get-url origin 2>/dev/null || true)"

echo "Cloning $SCRIPT_DIR into $TARGET_DIR on branch $BRANCH..."
git -c safe.directory="$SCRIPT_DIR" clone --branch "$BRANCH" "$SCRIPT_DIR" "$TARGET_DIR"

if [ -n "$ORIGIN_URL" ]; then
  git -C "$TARGET_DIR" remote set-url origin "$ORIGIN_URL"
fi

mkdir -p "$TARGET_DIR/storage"
reset_runtime_dirs "$TARGET_DIR"

cat > "$TARGET_DIR/storage/app_bootstrap.local.json" <<EOF
{
    "app_name": "$APP_NAME",
    "app_url": "$APP_URL",
    "laravel_api_url": "$API_URL",
    "default_tenant": "$TENANT_SLUG",
    "is_primary_app": "$IS_PRIMARY_APP"
}
EOF

if [ ! -L "$TARGET_DIR/dist" ] && [ ! -e "$TARGET_DIR/dist" ]; then
  ln -s "$SHARED_ROOT/dist" "$TARGET_DIR/dist"
fi

echo
echo "Provisioned tenant clone:"
echo "  Path: $TARGET_DIR"
echo "  App URL: $APP_URL"
echo "  API URL: $API_URL"
echo "  Tenant Slug: $TENANT_SLUG"
echo "  App Mode: $APP_MODE"
echo
echo "Next steps:"
echo "  1. Point your vhost/docroot at $TARGET_DIR"
echo "  2. Ensure tenant '$TENANT_SLUG' exists in the shared API database"
echo "  3. Open $APP_URL and log in"
