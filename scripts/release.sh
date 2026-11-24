#!/usr/bin/env bash
# One-step release: bumps the version, commits, and pushes to main.
# GitHub Actions then publishes the release and client sites pick it up.
#
#   bun run release            # patch  1.8.0 -> 1.8.1
#   bun run release minor      # minor  1.8.0 -> 1.9.0
#   bun run release major      # major  1.8.0 -> 2.0.0
#   bun run release 1.9.0      # explicit version
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

FILE="winc-admin-dashboard.php"
ARG="${1:-patch}"

[[ "$(git rev-parse --abbrev-ref HEAD)" == "main" ]] || { echo "Switch to main first." >&2; exit 1; }
[[ -z "$(git status --porcelain)" ]] || { echo "You have uncommitted changes - commit them first (then run this)." >&2; exit 1; }
git fetch -q origin main
[[ "$(git rev-parse HEAD)" == "$(git rev-parse origin/main)" ]] || { echo "main is not in sync with origin/main - pull/push first." >&2; exit 1; }

CURRENT=$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$FILE" | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
IFS=. read -r MA MI PA <<<"$CURRENT"

case "$ARG" in
  patch) NEW="$MA.$MI.$((PA + 1))" ;;
  minor) NEW="$MA.$((MI + 1)).0" ;;
  major) NEW="$((MA + 1)).0.0" ;;
  [0-9]*.[0-9]*.[0-9]*) NEW="$ARG" ;;
  *) echo "Usage: release.sh [patch|minor|major|x.y.z]" >&2; exit 1 ;;
esac

if git rev-parse -q --verify "refs/tags/v$NEW" >/dev/null; then
  echo "Tag v$NEW already exists." >&2; exit 1
fi

echo "==> $CURRENT -> $NEW"

# Rebuild assets so dist/ matches src/ (client sites install straight from git).
if command -v bunx >/dev/null 2>&1; then
  bunx vite build
else
  echo "(bun not found - skipping asset build)"
fi

sed -i.bak -E "s/^([[:space:]]*\*[[:space:]]*Version:)[[:space:]]*.*/\1     $NEW/" "$FILE" && rm -f "$FILE.bak"
node -e "const f='package.json',p=require('./'+f);p.version='$NEW';require('fs').writeFileSync(f,JSON.stringify(p,null,2)+'\n')"

git add -A
git commit -q -m "Release $NEW"
git push origin main

echo "==> Pushed $NEW. GitHub Actions will publish the release in about a minute."
echo "    https://github.com/paddywinc/wincAdminWPPlugin/actions"
