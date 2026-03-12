#!/bin/sh
set -e

echo "[entrypoint] Clearing config cache..."
php artisan config:clear 2>/dev/null || true

echo "[entrypoint] Waiting for PostgreSQL..."
until php artisan db:monitor --databases=pgsql 2>/dev/null; do
  sleep 2
done

echo "[entrypoint] Running migrations..."
php artisan migrate --force 2>/dev/null || true

echo "[entrypoint] Checking JWT keys..."
mkdir -p storage/keys
if [ ! -f storage/keys/jwt-private.pem ]; then
  echo "[entrypoint] Generating ECDSA P-256 JWT keys..."
  php artisan jwt:generate-keys 2>/dev/null || {
    openssl ecparam -genkey -name prime256v1 -noout -out storage/keys/jwt-private.pem
    openssl ec -in storage/keys/jwt-private.pem -pubout -out storage/keys/jwt-public.pem
  }
  if [ ! -f storage/keys/jwt-private.pem ]; then
    echo "[entrypoint] FATAL: JWT key generation failed!"
    exit 1
  fi
  echo "[entrypoint] JWT keys generated OK"
else
  echo "[entrypoint] JWT keys exist, skipping"
fi

echo "[entrypoint] Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
chmod 600 storage/keys/jwt-private.pem 2>/dev/null || true
chmod 644 storage/keys/jwt-public.pem 2>/dev/null || true

# Validate JWT key
php -r "openssl_pkey_get_private(file_get_contents('storage/keys/jwt-private.pem')) or exit(1);" || {
  echo "[entrypoint] FATAL: JWT private key invalid!"
  exit 1
}

echo "[entrypoint] Starting supervisord..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
