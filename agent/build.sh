#!/bin/sh
# Builds both Windows versions into resources/agent/dist. Sign them afterwards with
# `php artisan sync:agent-sign <key>`. The public key must exist first (see docs/SYNC.md).
set -eu
cd "$(dirname "$0")"

public_key=$(base64 -w0 ../resources/agent/signing.pub)
mkdir -p ../resources/agent/dist

for arch in amd64 386; do
    CGO_ENABLED=0 GOOS=windows GOARCH=$arch go build -trimpath -buildvcs=false \
        -ldflags "-X main.publicKeyBase64=$public_key" \
        -o "../resources/agent/dist/ecomvision-agent-$arch.exe" .
    echo "Built ecomvision-agent-$arch.exe"
done
