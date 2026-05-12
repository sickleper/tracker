#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$SCRIPT_DIR"
BRANCH="${1:-main}"
GIT_SAFE_ENV=(
  "GIT_CONFIG_COUNT=2"
  "GIT_CONFIG_KEY_0=safe.directory"
  "GIT_CONFIG_VALUE_0=$REPO_DIR"
  "GIT_CONFIG_KEY_1=safe.directory"
  "GIT_CONFIG_VALUE_1=$REPO_DIR/.git"
)

git_in_repo() {
  env "${GIT_SAFE_ENV[@]}" git -C "$REPO_DIR" "$@"
}

cd "$REPO_DIR"

if ! git_in_repo rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "ERROR: $REPO_DIR is not a git repository"
  exit 1
fi

CURRENT_BRANCH="$(git_in_repo rev-parse --abbrev-ref HEAD)"
CURRENT_COMMIT="$(git_in_repo rev-parse --short HEAD)"

echo "Repository: $REPO_DIR"
echo "Current branch: $CURRENT_BRANCH"
echo "Current commit: $CURRENT_COMMIT"
echo "Requested branch: $BRANCH"
echo

if [ -n "$(git_in_repo status --porcelain)" ]; then
  echo "ERROR: Working tree is dirty. Commit or stash local changes before deploying."
  git_in_repo status --short
  exit 1
fi

echo "Fetching remote..."
git_in_repo fetch --all --prune

if ! git_in_repo show-ref --verify --quiet "refs/remotes/origin/$BRANCH"; then
  echo "ERROR: Branch origin/$BRANCH not found."
  exit 1
fi

if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
  echo "Checking out branch $BRANCH..."
  git_in_repo checkout "$BRANCH"
fi

echo "Pulling latest fast-forward changes..."
git_in_repo pull --ff-only origin "$BRANCH"

echo
echo "Deploy complete."
echo "New branch: $(git_in_repo rev-parse --abbrev-ref HEAD)"
echo "New commit: $(git_in_repo rev-parse --short HEAD)"
