#!/usr/bin/env bash
set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

CERT_DIR="./certs/dev"
mkdir -p "$CERT_DIR"

echo -e "${YELLOW}🔒 Generating self-signed certificates for local development...${NC}"

if [ ! -f "$CERT_DIR/ca.key" ]; then
    echo "Generating CA private key..."
    openssl genrsa -out "$CERT_DIR/ca.key" 4096
    
    echo "Generating CA certificate..."
    openssl req -x509 -new -nodes -key "$CERT_DIR/ca.key" -sha256 -days 3650 \
        -out "$CERT_DIR/ca.crt" \
        -subj "/C=US/ST=State/L=City/O=Travian Dev/CN=Travian Development CA"
    
    echo -e "${GREEN}✅ CA certificate generated${NC}"
fi

echo "Generating server private key..."
openssl genrsa -out "$CERT_DIR/server.key" 2048

echo "Generating certificate signing request..."
openssl req -new -key "$CERT_DIR/server.key" \
    -out "$CERT_DIR/server.csr" \
    -subj "/C=US/ST=State/L=City/O=Travian Dev/CN=localhost"

cat > "$CERT_DIR/server.ext" <<EOF
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment
subjectAltName = @alt_names

[alt_names]
DNS.1 = localhost
DNS.2 = *.localhost
IP.1 = 127.0.0.1
IP.2 = ::1
EOF

echo "Signing server certificate with CA..."
openssl x509 -req -in "$CERT_DIR/server.csr" \
    -CA "$CERT_DIR/ca.crt" -CAkey "$CERT_DIR/ca.key" -CAcreateserial \
    -out "$CERT_DIR/server.crt" -days 365 -sha256 \
    -extfile "$CERT_DIR/server.ext"

chmod 600 "$CERT_DIR"/*.key
chmod 644 "$CERT_DIR"/*.crt

echo -e "${GREEN}✅ Development certificates generated in $CERT_DIR${NC}"
echo ""
echo "To trust the CA certificate (optional):"
echo "  - Linux: sudo cp $CERT_DIR/ca.crt /usr/local/share/ca-certificates/ && sudo update-ca-certificates"
echo "  - macOS: sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain $CERT_DIR/ca.crt"
