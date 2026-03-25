#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SHARED_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

usage() {
  echo "Usage: $0 <target-dir-name>"
  echo "Example: $0 trackers-acme"
}

if [ "$#" -lt 1 ]; then
  usage
  exit 1
fi

TARGET_INPUT="$1"

# Resolve absolute path
if [[ "$TARGET_INPUT" = /* ]]; then
  TARGET_DIR="$TARGET_INPUT"
else
  TARGET_DIR="$SHARED_ROOT/$TARGET_INPUT"
fi

TARGET_DIR="${TARGET_DIR%/}"
TARGET_PARENT="$(dirname "$TARGET_DIR")"

# Safety checks
if [ "$TARGET_PARENT" != "$SHARED_ROOT" ]; then
  echo "ERROR: Target must live directly under $SHARED_ROOT"
  exit 1
fi

if [ "$TARGET_DIR" == "$SCRIPT_DIR" ]; then
  echo "ERROR: Cannot delete the source/primary app directory!"
  exit 1
fi

if [ ! -d "$TARGET_DIR" ]; then
  echo "ERROR: Target directory does not exist: $TARGET_DIR"
  exit 1
fi

# Final confirmation check (can be skipped if called from a script that already confirmed)
echo "Deleting tenant clone at: $TARGET_DIR"
rm -rf "$TARGET_DIR"

echo "Directory deleted successfully."
