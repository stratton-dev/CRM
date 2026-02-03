#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-${ROOT_DIR}/tmp}"
OUT_PDF="${OUT_DIR}/oferta-nowa.pdf"
OUT_HTML="${OUT_DIR}/offer-render.html"
TMP_BUILD="${OUT_DIR}/_offer_build"

mkdir -p "${OUT_DIR}" "${TMP_BUILD}"

"${ROOT_DIR}/node_modules/.bin/tsc" \
  --module commonjs \
  --target es2020 \
  --moduleResolution node \
  --esModuleInterop \
  --rootDir "${ROOT_DIR}" \
  --outDir "${TMP_BUILD}" \
  "${ROOT_DIR}/scripts/render-offer.ts"

echo '{"type":"commonjs"}' > "${TMP_BUILD}/package.json"

node "${TMP_BUILD}/scripts/render-offer.js" "${OUT_DIR}" >/dev/null

CHROME_BIN="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"

"${CHROME_BIN}" \
  --headless \
  --disable-gpu \
  --no-sandbox \
  --disable-dev-shm-usage \
  --no-margins \
  --print-to-pdf="${OUT_PDF}" \
  "file://${OUT_HTML}"

echo "Generated: ${OUT_PDF}"
