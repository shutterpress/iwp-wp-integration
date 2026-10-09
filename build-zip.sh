#!/bin/bash
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_NAME="fluentcart-instawp-provisioning"
OUTPUT="${1:-${PLUGIN_DIR}/${PLUGIN_NAME}.zip}"
OUTPUT="$(realpath -m "$OUTPUT")"
BUILD_DIR="$(mktemp -d)"
trap 'rm -rf "$BUILD_DIR"' EXIT

mkdir -p "$BUILD_DIR/$PLUGIN_NAME"
rsync -a "$PLUGIN_DIR/" "$BUILD_DIR/$PLUGIN_NAME/" \
  --exclude='.git' --exclude='.github' --exclude='node_modules' \
  --exclude='vendor' --exclude='tests' --exclude='build' --exclude='*.zip' \
  --exclude='*.sh' --exclude='CLAUDE.md' --exclude='TESTING-PLAN.md' \
  --exclude='.gitattributes' --exclude='.DS_Store'
rm -f "$OUTPUT"
cd "$BUILD_DIR"
zip -qr "$OUTPUT" "$PLUGIN_NAME/"
echo "Created: $OUTPUT"
