#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${ROOT_DIR}/dist"

mkdir -p "${OUT_DIR}"
mkdir -p /tmp/gocache

export GOCACHE=/tmp/gocache
export CGO_ENABLED=0

build() {
  local goos="$1"
  local goarch="$2"
  local ext="$3"
  local out="${OUT_DIR}/scannerfs-${goos}-${goarch}${ext}"
  echo "Building ${out}"
  GOOS="${goos}" GOARCH="${goarch}" \
    go build -buildvcs=false -trimpath -ldflags="-s -w" \
      -o "${out}" ./cmd/scannerfs
}

build linux amd64 ""
build linux arm64 ""
build windows amd64 ".exe"
build darwin amd64 ""
build darwin arm64 ""

echo "Done: ${OUT_DIR}"

