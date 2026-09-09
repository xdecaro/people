#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; VERSION="$(tr -d '\r\n' < "$ROOT/VERSION")"; DIST="$ROOT/dist"; WORK="$ROOT/build/.work"
rm -rf "$DIST" "$WORK"; mkdir -p "$DIST" "$WORK/component" "$WORK/package"; cp -R "$ROOT/component/." "$WORK/component/"; find "$WORK" -type f -exec touch -t 198001010000 {} +
(cd "$WORK/component" && find . -type f -print0|sort -z|xargs -0 zip -X -q "$DIST/com_xdecaropeople_${VERSION}.zip")
cp "$ROOT/package/pkg_xdecaropeople.xml" "$WORK/package/pkg_xdecaropeople.xml"; cp "$ROOT/package/script.php" "$WORK/package/script.php"; cp "$DIST/com_xdecaropeople_${VERSION}.zip" "$WORK/package/com_xdecaropeople.zip"; find "$WORK/package" -type f -exec touch -t 198001010000 {} +
(cd "$WORK/package" && find . -type f -print0|sort -z|xargs -0 zip -X -q "$DIST/pkg_xdecaropeople_${VERSION}.zip")
(cd "$DIST" && sha256sum "com_xdecaropeople_${VERSION}.zip" "pkg_xdecaropeople_${VERSION}.zip">SHA256SUMS.txt)
