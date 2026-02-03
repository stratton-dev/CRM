#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
GEN_PDF="${1:-${ROOT_DIR}/tmp/oferta-nowa.pdf}"
REF_PDF="${ROOT_DIR}/wzor_oferty.pdf"
SYS_PDF="${ROOT_DIR}/oferta-z-systemu.pdf"
OUT_DIR="${ROOT_DIR}/tmp/offer-compare"

mkdir -p "${OUT_DIR}"

pdftotext "${GEN_PDF}" "${OUT_DIR}/generated.txt"
pdftotext "${REF_PDF}" "${OUT_DIR}/reference.txt"
pdftotext "${SYS_PDF}" "${OUT_DIR}/system.txt"

diff -u "${OUT_DIR}/reference.txt" "${OUT_DIR}/generated.txt" > "${OUT_DIR}/text-diff.txt" || true

pdftoppm -png "${REF_PDF}" "${OUT_DIR}/ref"
pdftoppm -png "${GEN_PDF}" "${OUT_DIR}/gen"

for ref in "${OUT_DIR}"/ref-*.png; do
  base="$(basename "${ref}")"
  gen="${OUT_DIR}/gen-${base#ref-}"
  if [[ -f "${gen}" ]]; then
    diff_out="${OUT_DIR}/diff-${base#ref-}"
    compare "${ref}" "${gen}" "${diff_out}" || true
  fi
done

echo "Comparison outputs in ${OUT_DIR}"
