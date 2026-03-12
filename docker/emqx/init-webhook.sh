#!/bin/sh
set -e

EMQX_URL="http://emqx:18083/api/v5"
EMQX_USER="admin"
EMQX_PASS="${EMQX_DASHBOARD_PASSWORD:-public}"
WEBHOOK_SECRET="${EMQX_WEBHOOK_SECRET:-sandbox-webhook-secret}"

echo "[emqx-init] Waiting for EMQX API..."
until curl -sf "${EMQX_URL}/status" > /dev/null 2>&1; do
  sleep 2
done
echo "[emqx-init] EMQX API ready"

# Authenticate with JWT
echo "[emqx-init] Authenticating..."
TOKEN=$(curl -sf -X POST "${EMQX_URL}/login" \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"${EMQX_USER}\",\"password\":\"${EMQX_PASS}\"}" \
  | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
  echo "[emqx-init] ERROR: Failed to authenticate with EMQX API"
  exit 1
fi
echo "[emqx-init] Authenticated"

AUTH_HEADER="Authorization: Bearer ${TOKEN}"
HEADERS="{\"Content-Type\":\"application/json\",\"X-Webhook-Secret\":\"${WEBHOOK_SECRET}\"}"

# Create webhook connector (if not exists)
echo "[emqx-init] Creating webhook connector..."
curl -sf -H "${AUTH_HEADER}" \
  "${EMQX_URL}/connectors/http:mqtt_webhook_connector" > /dev/null 2>&1 || \
curl -sf -X POST \
  -H "${AUTH_HEADER}" \
  -H "Content-Type: application/json" \
  "${EMQX_URL}/connectors" \
  -d "{
    \"type\": \"http\",
    \"name\": \"mqtt_webhook_connector\",
    \"url\": \"http://nginx:80\",
    \"connect_timeout\": \"5s\",
    \"pool_size\": 8,
    \"enable_pipelining\": 100,
    \"headers\": {
      \"Content-Type\": \"application/json\"
    }
  }"
echo "[emqx-init] Connector ready"

# Create webhook action (if not exists)
echo "[emqx-init] Creating webhook action..."
curl -sf -H "${AUTH_HEADER}" \
  "${EMQX_URL}/actions/http:mqtt_webhook" > /dev/null 2>&1 || \
curl -sf -X POST \
  -H "${AUTH_HEADER}" \
  -H "Content-Type: application/json" \
  "${EMQX_URL}/actions" \
  -d "{
    \"type\": \"http\",
    \"name\": \"mqtt_webhook\",
    \"connector\": \"mqtt_webhook_connector\",
    \"parameters\": {
      \"path\": \"/internal/mqtt/webhook\",
      \"method\": \"post\",
      \"headers\": ${HEADERS}
    },
    \"resource_opts\": {
      \"query_mode\": \"async\",
      \"inflight_window\": 100,
      \"request_ttl\": \"10s\",
      \"worker_pool_size\": 8
    }
  }"
echo "[emqx-init] Action ready"

# Create rule for to-server messages (if not exists)
echo "[emqx-init] Creating rule..."
curl -sf -H "${AUTH_HEADER}" \
  "${EMQX_URL}/rules/forward_to_server" > /dev/null 2>&1 || \
curl -sf -X POST \
  -H "${AUTH_HEADER}" \
  -H "Content-Type: application/json" \
  "${EMQX_URL}/rules" \
  -d "{
    \"id\": \"forward_to_server\",
    \"sql\": \"SELECT topic, payload FROM 'ospp/v1/stations/+/to-server'\",
    \"actions\": [\"http:mqtt_webhook\"],
    \"enable\": true,
    \"description\": \"Forward station messages to Laravel webhook\"
  }"
echo "[emqx-init] Rule ready"

echo "[emqx-init] Done!"
