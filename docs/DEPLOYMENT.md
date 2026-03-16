# Production Deployment

Deploy the CSMS Sandbox on a VPS with Docker Compose.

## Prerequisites

- Ubuntu 22.04+ or Debian 12+
- Docker Engine 24+ and Docker Compose v2
- Domain name with DNS pointed to server
- TLS certificate (Let's Encrypt recommended)
- 2 CPU cores, 4 GB RAM minimum

## 1. Server Setup

```bash
# Install Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Install Certbot for TLS
sudo apt install certbot
sudo certbot certonly --standalone -d csms-sandbox.example.com
```

## 2. Clone and Configure

```bash
git clone https://github.com/ospp-org/csms-sandbox.git
cd csms-sandbox
cp .env.example .env
```

Edit `.env` — change ALL `change-me` values:

```bash
# Required changes
APP_ENV=production
APP_DEBUG=false
APP_URL=https://csms-sandbox.example.com

DB_PASSWORD=<strong-password>
REDIS_PASSWORD=<strong-password>

EMQX_API_USERNAME=<emqx-admin-user>
EMQX_API_PASSWORD=<strong-password>
EMQX_WEBHOOK_SECRET=<random-secret>
EMQX_DASHBOARD_PASSWORD=<strong-password>

REVERB_APP_KEY=<random-key>
REVERB_APP_SECRET=<random-secret>
REVERB_SCHEME=https

SESSION_SECURE_COOKIE=true
```

Generate application key:
```bash
docker compose run --rm app php artisan key:generate
```

## 3. TLS Certificates

Copy Let's Encrypt certs for EMQX MQTT TLS:

```bash
mkdir -p certs
sudo cp /etc/letsencrypt/live/csms-sandbox.example.com/fullchain.pem certs/
sudo cp /etc/letsencrypt/live/csms-sandbox.example.com/privkey.pem certs/
sudo chown $USER:$USER certs/*.pem
```

## 4. Build and Start

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml build
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

Run migrations:
```bash
docker compose exec app php artisan migrate --force
```

## 5. Nginx Reverse Proxy

Install nginx on the host (outside Docker) for TLS termination:

```bash
sudo apt install nginx
```

Create `/etc/nginx/sites-available/csms-sandbox`:

```nginx
server {
    listen 443 ssl http2;
    server_name csms-sandbox.example.com;

    ssl_certificate /etc/letsencrypt/live/csms-sandbox.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/csms-sandbox.example.com/privkey.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Laravel app
    location / {
        proxy_pass http://127.0.0.1:8100;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket (Reverb)
    location /app {
        proxy_pass http://127.0.0.1:8100;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 60s;
    }
}

server {
    listen 80;
    server_name csms-sandbox.example.com;
    return 301 https://$host$request_uri;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/csms-sandbox /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

## 6. Docker Compose Files

| File | Purpose |
|------|---------|
| `docker-compose.yml` | Base services (app, nginx, queue, reverb, scheduler, postgres, redis, emqx) |
| `docker-compose.prod.yml` | Production overrides (production build target, no EMQX dashboard port) |
| `docker-compose.override.yml` | Dev overrides (gitignored — source mounts, dev PHP config, EMQX dashboard) |

Production command always includes both files:
```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml <command>
```

## 7. MQTT Ports

| Port | Protocol | Purpose |
|------|----------|---------|
| 1883 | MQTT (plain) | Development/testing |
| 8883 | MQTTS (TLS) | Production — Let's Encrypt certs |

EMQX dashboard port (18083) is NOT exposed in production (`docker-compose.prod.yml` omits it).

## 8. Monitoring

Health check endpoint (public, no auth):
```bash
curl https://csms-sandbox.example.com/api/v1/status
```

Returns service status for database, redis, emqx, queue.

## 9. Database Backup

```bash
# Manual backup
docker compose exec postgres pg_dump -U sandbox sandbox > backup_$(date +%Y%m%d).sql

# Restore
cat backup.sql | docker compose exec -T postgres psql -U sandbox sandbox
```

## 10. Log Management

```bash
# Application logs (daily rotation)
docker compose exec app tail -f storage/logs/laravel-$(date +%Y-%m-%d).log

# Queue worker logs
docker compose logs -f queue-worker

# EMQX logs
docker compose logs -f emqx
```

## 11. Certificate Renewal

Automate Let's Encrypt renewal:

```bash
# /etc/cron.d/certbot-renew
0 3 * * * root certbot renew --deploy-hook "cp /etc/letsencrypt/live/csms-sandbox.example.com/*.pem /path/to/csms-sandbox/certs/ && docker compose -f /path/to/csms-sandbox/docker-compose.yml restart emqx && systemctl reload nginx"
```

## 12. Updating

```bash
cd csms-sandbox
git pull origin main
docker compose -f docker-compose.yml -f docker-compose.prod.yml build
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
docker compose exec app php artisan migrate --force
```
