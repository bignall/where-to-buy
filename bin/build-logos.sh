#!/usr/bin/env bash
# Build the separate store logos download: store-logos/ (PNG, SVG, README) as one zip.
# Usage: bin/build-logos.sh [output-dir]   (default: dist/)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$(cd "${1:-$ROOT}" && pwd)"; [ -z "${1:-}" ] && OUT="$ROOT/dist"
ZIP="$OUT/where-to-buy-store-logos.zip"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT

mkdir -p "$OUT"
cp -r "$ROOT/store-logos" "$WORK/where-to-buy-store-logos"
find "$WORK" -name '.DS_Store' -delete
rm -f "$ZIP"
(cd "$WORK" && zip -qr "$ZIP" where-to-buy-store-logos)
echo "$ZIP"
