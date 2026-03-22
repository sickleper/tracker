#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="/home/workorders/trackers"
BRANCH="${1:-main}"

cd "$REPO_DIR"

if [ ! -d ".git" ]; then
  echo "ERROR: $REPO_DIR is not a git repository"
  exit 1
fi

CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
CURRENT_COMMIT="$(git rev-parse --short HEAD)"

echo "Repository: $REPO_DIR"
echo "Current branch: $CURRENT_BRANCH"
echo "Current commit: $CURRENT_COMMIT"
echo "Requested branch: $BRANCH"
echo

if [ -n "$(git status --porcelain)" ]; then
  echo "ERROR: Working tree is dirty. Commit or stash local changes before deploying."
  git status --short
  exit 1
fi

echo "Fetching remote..."
git fetch --all --prune

if ! git show-ref --verify --quiet "refs/remotes/origin/$BRANCH"; then
  echo "ERROR: Branch origin/$BRANCH not found."
  exit 1
fi

if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
  echo "Checking out branch $BRANCH..."
  git checkout "$BRANCH"
fi

echo "Pulling latest fast-forward changes..."
git pull --ff-only origin "$BRANCH"

echo
echo "Deploy complete."
echo "New branch: $(git rev-parse --abbrev-ref HEAD)"
echo "New commit: $(git rev-parse --short HEAD)"
