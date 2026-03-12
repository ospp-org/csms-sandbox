# CSMS Sandbox — Deployment

---

## Docker Compose

### docker-compose.yml

```yaml
services:
  # --- Application ---
  app:
    build:
      context: .
      dockerfile: Dockerfile
      target: development
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
      emqx:
        condition: service_healthy
    volumes:
      - .:/var/www/html
      - ./docker/php/php-dev.ini:/usr/local/etc/php/conf.d/99-dev.ini
    networks:
      - sandbox-network
    restart: unless-stopped

  nginx:
    image: nginx:1.25-alpine
    ports:
      - "${APP_PORT:-80}:80"
      - "${APP_SSL_PORT:-443}:443"
    volumes:
      - .:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app
    networks:
      - sandbox-network
    restart: unless-stopped

  # --- Queue Worker ---
  queue-worker:
    build:
      context: .
      dockerfile: Dockerfile
      target: development
    command: sh -c "php /var/www/html/artisan queue:work redis --queue=mqtt-messages --sleep=3 --tries=3 --memory=128 --timeout=60"
    depends_on:
      redis:
        condition: service_healthy
      postgres:
        condition: service_healthy
      emqx:
        condition: service_healthy
      app:
        condition: service_started
    volumes:
      - .:/var/www/html
      - ./docker/php/php-dev.ini:/usr/local/etc/php/conf.d/99-dev.ini
    networks:
      - sandbox-network
    restart: unless-stopped
    stop_grace_period: 35s
    deploy:
      replicas: ${QUEUE_WORKERS:-2}
      resources:
        limits:
          memory: 256M
          cpus: '0.5'
    healthcheck:
      test: ["CMD", "sh", "-c", "[ -f /tmp/mqtt-worker-heartbeat ] && [ $(cat /tmp/mqtt-worker-heartbeat) -gt $(($(date +%s) - 60)) ]"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 30s

  # --- Laravel Reverb (WebSocket) ---
  reverb:
    build:
      context: .
      dockerfile: Dockerfile
      target: development
    command: sh -c "php /var/www/html/artisan reverb:start --host=0.0.0.0 --port=8080"
    depends_on:
      redis:
        condition: service_healthy
    volumes:
      - .:/var/www/html
      - ./docker/php/php-dev.ini:/usr/local/etc/php/conf.d/99-dev.ini
    networks:
      - sandbox-network
    restart: unless-stopped

  # --- Scheduler ---
  scheduler:
    build:
      context: .
      dockerfile: Dockerfile
      target: development
    command: sh -c "php /var/www/html/artisan schedule:work"
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    volumes:
      - .:/var/www/html
      - ./docker/php/php-dev.ini:/usr/local/etc/php/conf.d/99-dev.ini
    networks:
      - sandbox-network
    restart: unless-stopped

  # --- Infrastructure ---
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: ${DB_DATABASE:-sandbox}
      POSTGRES_USER: ${DB_USERNAME:-sandbox}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - postgres-data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-sandbox}"]
      interval: 10s
      timeout: 3s
      retries: 5
    networks:
      - sandbox-network
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    command: redis-server --requirepass ${REDIS_PASSWORD:-secret} --appendonly yes
    volumes:
      - redis-data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD:-secret}", "ping"]
      interval: 10s
      timeout: 3s
      retries: 5
    networks:
      - sandbox-network
    restart: unless-stopped

  emqx:
    image: emqx/emqx:5.8
    environment:
      EMQX_NAME: csms-sandbox
      EMQX_DASHBOARD__DEFAULT_PASSWORD: ${EMQX_DASHBOARD_PASSWORD:-public}
    volumes:
      - emqx-data:/opt/emqx/data
      - ./docker/emqx/emqx.conf:/opt/emqx/etc/emqx.conf:ro
    ports:
      - "${MQTT_PORT:-1883}:1883"
      - "${MQTT_TLS_PORT:-8883}:8883"
      - "${EMQX_DASHBOARD_PORT:-18083}:18083"
    healthcheck:
      test: ["CMD-SHELL", "emqx ctl status | grep started"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    networks:
      - sandbox-network
    restart: unless-stopped

  # --- One-shot Init ---
  emqx-init:
    image: curlimages/curl:8.5.0
    volumes:
      - ./docker/emqx/init-webhook.sh:/scripts/init-webhook.sh:ro
    environment:
      EMQX_WEBHOOK_SECRET: ${EMQX_WEBHOOK_SECRET:-sandbox-webhook-secret}
    depends_on:
      emqx:
        condition: service_healthy
      nginx:
        condition: service_started
    entrypoint: sh /scripts/init-webhook.sh
    networks:
      - sandbox-network
    restart: "no"

volumes:
  postgres-data:
  redis-data:
  emqx-data:

networks:
  sandbox-network:
    driver: bridge
```

---

## Dockerfile

```dockerfile
# Stage 1: Composer dependencies
FROM composer:2 AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Stage 2: Development
FROM php:8.4-fpm-alpine AS development

RUN apk add --no-cache --virtual .build-deps \
        postgresql-dev \
    && apk add --no-cache \
        supervisor \
        curl \
        openssl \
    && docker-php-ext-install pdo_pgsql pcntl \
    && apk del .build-deps

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/99-dev.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN mkdir -p /var/log/supervisor storage/logs storage/keys bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts

COPY . .

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000

CMD ["/entrypoint.sh"]

# Stage 3: Production
FROM development AS production

RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER www-data
EXPOSE 9000

CMD ["/entrypoint.sh"]
```

---

## Entrypoint

### docker/entrypoint.sh

```bash
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
```

---

## Nginx Config

### docker/nginx/default.conf

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Max upload (for firmware URLs, certificates)
    client_max_body_size 10M;

    # Health check (no FPM, instant)
    location /health/live {
        return 200 '{"status":"ok"}';
        add_header Content-Type application/json;
    }

    # Internal endpoints (EMQX only — restrict to Docker network)
    location /internal/ {
        allow 172.16.0.0/12;  # Docker network range
        allow 10.0.0.0/8;
        deny all;

        try_files $uri /index.php$is_args$args;
    }

    # WebSocket proxy (Reverb)
    location /app {
        proxy_pass http://reverb:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 60s;
    }

    # Laravel
    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 60s;
    }

    # Static assets
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Deny hidden files
    location ~ /\. {
        deny all;
    }
}
```

---

## PHP-FPM Config

### docker/php/www.conf

```ini
[www]
user = www-data
group = www-data

listen = 9000

pm = static
pm.max_children = 8
pm.max_requests = 500

clear_env = no
```

---

## Supervisord Config

### docker/supervisor/supervisord.conf

```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid
loglevel=info

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true
priority=5
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:scheduler]
command=php /var/www/html/artisan schedule:work
autostart=false
autorestart=true
priority=15
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

Note: scheduler runs as separate container in docker-compose. The supervisor entry is `autostart=false` — only used if running single-container mode.

---

## Environment Variables

### .env.example

```ini
APP_NAME="OSPP CSMS Sandbox"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
APP_PORT=80

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=sandbox
DB_USERNAME=sandbox
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=secret
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# MQTT / EMQX
EMQX_API_URL=http://emqx:18083/api/v5
EMQX_API_USERNAME=admin
EMQX_API_PASSWORD=public
EMQX_WEBHOOK_SECRET=sandbox-webhook-secret
EMQX_DASHBOARD_PASSWORD=public
MQTT_PORT=1883
MQTT_TLS_PORT=8883

# Reverb (WebSocket)
REVERB_APP_ID=sandbox
REVERB_APP_KEY=sandbox-key
REVERB_APP_SECRET=sandbox-secret
REVERB_HOST=reverb
REVERB_PORT=8080

# Google OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost/auth/google/callback

# Queue Workers
QUEUE_WORKERS=2

# JWT
JWT_ALGORITHM=ES256
```

---

## Production Deployment (VPS)

### Domain + TLS

```bash
# DNS: csms-sandbox.ospp-standard.org → VPS_IP

# Install Certbot
sudo apt install certbot

# Get certificate
sudo certbot certonly --standalone -d csms-sandbox.ospp-standard.org

# Certificates at:
# /etc/letsencrypt/live/csms-sandbox.ospp-standard.org/fullchain.pem
# /etc/letsencrypt/live/csms-sandbox.ospp-standard.org/privkey.pem
```

Mount certificates into Nginx and EMQX containers via volumes.

### Production Overrides

```yaml
# docker-compose.prod.yml
services:
  app:
    build:
      target: production
    environment:
      APP_ENV: production
      APP_DEBUG: "false"

  nginx:
    volumes:
      - ./docker/nginx/production.conf:/etc/nginx/conf.d/default.conf:ro
      - /etc/letsencrypt:/etc/letsencrypt:ro

  queue-worker:
    build:
      target: production
    deploy:
      replicas: 4
```

Deploy: `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d`

---

## Scheduled Jobs

| Job | Schedule | Command |
|-----|----------|---------|
| Message cleanup | Daily 3AM | `php artisan messages:cleanup` |
| Connection check | Every 1min | `php artisan station:check-connection` |
| Inactive tenants | Weekly Sunday | `php artisan tenants:notify-inactive` |

Registered in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('messages:cleanup')->dailyAt('03:00');
    $schedule->command('station:check-connection')->everyMinute();
    $schedule->command('tenants:notify-inactive')->weeklyOn(0, '09:00');
}
```

---

## Quick Start (Development)

```bash
git clone git@github.com:ospp-org/csms-sandbox.git
cd csms-sandbox
cp .env.example .env

# Build and start
docker compose build
docker compose up -d

# Initialize EMQX webhook (after services are healthy)
docker compose up emqx-init

# Seed development data
docker compose exec app php artisan db:seed

# Visit http://localhost
# Login: dev@ospp-standard.org / password
```

---

## Monitoring (Production)

Health endpoint: `GET /health`

Returns:
```json
{
    "status": "healthy",
    "checks": {
        "database": "ok",
        "redis": "ok",
        "emqx": "ok",
        "queue": { "depth": 0, "failed": 0 }
    }
}
```

External monitoring (UptimeRobot, Healthchecks.io) pings `/health` every 60s. Alert on non-200 response.
