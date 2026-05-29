#!/usr/bin/env bash
set -e

PLUGIN_SLUG="winc-admin"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="$ROOT_DIR/releases"
ZIP_PATH="$OUT_DIR/$PLUGIN_SLUG.zip"

mkdir -p "$OUT_DIR"
rm -f "$ZIP_PATH"

cd "$ROOT_DIR"

zip -r "$ZIP_PATH" . \
  --exclude "*.git*" \
  --exclude "node_modules/*" \
  --exclude "src/*" \
  --exclude "releases/*" \
  --exclude "*.sh" \
  --exclude "vite.config.js" \
  --exclude "package.json" \
  --exclude "package-lock.json" \
  --exclude "bun.lockb"

echo "✓ Zipped to $ZIP_PATH"
