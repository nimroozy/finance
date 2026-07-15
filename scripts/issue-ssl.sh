#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOMAIN="${DOMAIN:-finance.mns.af}"
EMAIL="${SSL_EMAIL:-admin@${DOMAIN}}"

cd "${ROOT_DIR}"

# Temporarily use HTTP-only for challenge; certbot standalone would conflict with :80.
# Prefer webroot via nginx certbot volume.

mkdir -p docker/nginx/certbot docker/nginx/certs

if ! dig +short "${DOMAIN}" A | grep -q .; then
  echo "DNS for ${DOMAIN} does not resolve yet. Aborting SSL until A record points to this server." >&2
  exit 1
fi

# Stop outer nginx briefly only if needed — use webroot while nginx stays up.
certbot certonly --webroot \
  -w "${ROOT_DIR}/docker/nginx/certbot" \
  -d "${DOMAIN}" \
  --email "${EMAIL}" \
  --agree-tos \
  --non-interactive \
  --deploy-hook "cp -L /etc/letsencrypt/live/${DOMAIN}/fullchain.pem ${ROOT_DIR}/docker/nginx/certs/fullchain.pem && cp -L /etc/letsencrypt/live/${DOMAIN}/privkey.pem ${ROOT_DIR}/docker/nginx/certs/privkey.pem && docker compose -f ${ROOT_DIR}/docker-compose.yml exec -T nginx nginx -s reload || true"

cp -L "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" "${ROOT_DIR}/docker/nginx/certs/fullchain.pem"
cp -L "/etc/letsencrypt/live/${DOMAIN}/privkey.pem" "${ROOT_DIR}/docker/nginx/certs/privkey.pem"

# Install HTTPS server block if not present
if [ ! -f docker/nginx/conf.d/ssl.conf ]; then
  cat > docker/nginx/conf.d/ssl.conf <<EOF
server {
    listen 443 ssl http2;
    server_name ${DOMAIN};

    ssl_certificate     /etc/nginx/certs/fullchain.pem;
    ssl_certificate_key /etc/nginx/certs/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;

    location /api/ {
        proxy_pass http://backend_upstream;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 120s;
    }

    location /sanctum/ {
        proxy_pass http://backend_upstream;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location /up {
        proxy_pass http://backend_upstream;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location / {
        proxy_pass http://frontend_upstream;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF
fi

docker compose exec -T nginx nginx -s reload || docker compose up -d nginx
echo "SSL issued for ${DOMAIN}"
