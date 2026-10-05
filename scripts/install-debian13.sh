#!/usr/bin/env bash
# =============================================================================
# AgentHub – instalator natywny dla Debian 13 (trixie)
# Instaluje: Apache + PHP-FPM, PostgreSQL lub MariaDB, Redis, Qdrant (lub pgvector),
#            Node.js, Composer, usługi systemd (Horizon, Reverb), cron (scheduler).
#
# Użycie:   sudo ./scripts/install-debian13.sh [opcje]
# Opcje:
#   --db=pgsql|mysql        silnik bazy danych (domyślnie pgsql; mysql = MariaDB)
#   --vector=qdrant|pgvector magazyn wektorów (domyślnie qdrant; pgvector tylko z pgsql)
#   --domain=NAZWA          ServerName Apache (domyślnie localhost)
#   --app-dir=ŚCIEŻKA       katalog aplikacji (domyślnie /var/www/agenthub)
#   --with-docker           doinstaluj Docker (dla integracji Hermes/OpenClaw w trybie docker)
#   --force                 pomiń kontrolę wersji systemu
#   --help, -h
# Skrypt jest idempotentny – można go uruchomić ponownie (zachowuje .env i hasła).
# =============================================================================
set -Eeuo pipefail

DB_ENGINE="pgsql"
VECTOR="qdrant"
DOMAIN="localhost"
APP_DIR="/var/www/agenthub"
WITH_DOCKER=0
FORCE=0
QDRANT_VERSION="${QDRANT_VERSION:-}"   # np. v1.15.0; puste = najnowsza
CRED_FILE="/root/agenthub-install.txt"

log()  { echo -e "\e[1;34m[AgentHub]\e[0m $*"; }
warn() { echo -e "\e[1;33m[UWAGA]\e[0m $*"; }
die()  { echo -e "\e[1;31m[BŁĄD]\e[0m $*" >&2; exit 1; }
trap 'die "Instalacja przerwana w linii $LINENO"' ERR

for arg in "$@"; do
  case "$arg" in
    --db=*)        DB_ENGINE="${arg#*=}" ;;
    --vector=*)    VECTOR="${arg#*=}" ;;
    --domain=*)    DOMAIN="${arg#*=}" ;;
    --app-dir=*)   APP_DIR="${arg#*=}" ;;
    --with-docker) WITH_DOCKER=1 ;;
    --force)       FORCE=1 ;;
    --help|-h)     sed -n '2,17p' "$0"; exit 0 ;;
    *) die "Nieznana opcja: $arg" ;;
  esac
done

# ------------------------------- Kontrole wstępne ----------------------------
[[ $EUID -eq 0 ]] || die "Uruchom jako root (sudo)."
[[ "$DB_ENGINE" =~ ^(pgsql|mysql)$ ]] || die "--db musi być pgsql lub mysql"
[[ "$VECTOR" =~ ^(qdrant|pgvector)$ ]] || die "--vector musi być qdrant lub pgvector"
[[ "$VECTOR" == "pgvector" && "$DB_ENGINE" != "pgsql" ]] && die "pgvector wymaga --db=pgsql"

if [[ -f /etc/os-release ]]; then
  . /etc/os-release
  if [[ "${ID:-}" != "debian" || "${VERSION_ID:-}" != "13" ]]; then
    [[ $FORCE -eq 1 ]] || die "Skrypt przeznaczony dla Debian 13 (wykryto: ${PRETTY_NAME:-?}). Użyj --force, aby pominąć."
    warn "Wykryto inny system – kontynuuję na własne ryzyko (--force)."
  fi
else
  [[ $FORCE -eq 1 ]] || die "Brak /etc/os-release. Użyj --force, aby pominąć."
fi

ARCH="$(uname -m)"
case "$ARCH" in
  x86_64)  QDRANT_ARCH="x86_64-unknown-linux-gnu" ;;
  aarch64) QDRANT_ARCH="aarch64-unknown-linux-musl" ;;
  *) die "Nieobsługiwana architektura: $ARCH" ;;
esac

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
[[ -f "$REPO_ROOT/artisan" ]] || die "Nie znaleziono pliku artisan w $REPO_ROOT – uruchom skrypt z repozytorium AgentHub (scripts/)."

export DEBIAN_FRONTEND=noninteractive

# ------------------------------- Pakiety systemowe ---------------------------
log "Aktualizacja listy pakietów i instalacja zależności bazowych..."
apt-get update -y
apt-get install -y ca-certificates curl gnupg openssl unzip git rsync cron

log "Instalacja Apache, PHP-FPM i rozszerzeń PHP..."
apt-get install -y apache2 php-fpm php-cli php-common php-mbstring php-xml php-curl \
  php-zip php-bcmath php-intl php-gd php-redis

log "Instalacja Redis, Node.js, npm i Composer..."
apt-get install -y redis-server nodejs npm composer
systemctl enable --now redis-server

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
log "Wykryta wersja PHP: $PHP_VER"

if [[ "$DB_ENGINE" == "pgsql" ]]; then
  log "Instalacja PostgreSQL..."
  apt-get install -y postgresql php-pgsql
  systemctl enable --now postgresql
  PG_MAJOR="$(pg_lsclusters -h | awk 'NR==1{print $1}')"
  if [[ "$VECTOR" == "pgvector" ]]; then
    apt-get install -y "postgresql-${PG_MAJOR}-pgvector"
  fi
  DB_PORT=5432
else
  log "Instalacja MariaDB (zamiennik MySQL w Debianie)..."
  apt-get install -y mariadb-server php-mysql
  systemctl enable --now mariadb
  DB_PORT=3306
fi

if [[ $WITH_DOCKER -eq 1 ]]; then
  log "Instalacja Docker..."
  apt-get install -y docker.io
  apt-get install -y docker-compose-v2 || apt-get install -y docker-compose || warn "Nie udało się zainstalować docker compose."
  systemctl enable --now docker
  id agenthub-runner &>/dev/null || useradd -r -m -s /usr/sbin/nologin -G docker agenthub-runner
  log "Utworzono użytkownika agenthub-runner (grupa docker) dla integration-manager."
fi

# ------------------------------- Konfiguracja PHP ----------------------------
log "Konfiguracja PHP-FPM..."
cat > "/etc/php/${PHP_VER}/fpm/conf.d/99-agenthub.ini" <<INI
memory_limit = 512M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120
opcache.enable = 1
opcache.memory_consumption = 256
INI
systemctl enable --now "php${PHP_VER}-fpm"
systemctl restart "php${PHP_VER}-fpm"

# ------------------------------- Pliki aplikacji -----------------------------
if [[ "$REPO_ROOT" != "$APP_DIR" ]]; then
  log "Kopiowanie aplikacji do $APP_DIR..."
  mkdir -p "$APP_DIR"
  if [[ -f "$APP_DIR/artisan" ]]; then
    rsync -a --exclude .git --exclude node_modules --exclude vendor --exclude .env --exclude storage/ "$REPO_ROOT/" "$APP_DIR/"
  else
    rsync -a --exclude .git --exclude node_modules --exclude vendor "$REPO_ROOT/" "$APP_DIR/"
  fi
fi
cd "$APP_DIR"
[[ -f .env ]] || { [[ -f .env.example ]] && cp .env.example .env || die "Brak .env.example"; }

set_env() {
  local k="$1" v="$2"
  if grep -q "^${k}=" .env; then sed -i "s|^${k}=.*|${k}=${v}|" .env; else echo "${k}=${v}" >> .env; fi
}
get_env() { grep -m1 "^$1=" .env 2>/dev/null | cut -d= -f2- || true; }

# ------------------------------- Baza danych ---------------------------------
DB_NAME="agenthub"; DB_USER="agenthub"
DB_PASS="$(get_env DB_PASSWORD)"
if [[ -z "$DB_PASS" || "$DB_PASS" == "change-me" ]]; then DB_PASS="$(openssl rand -hex 16)"; fi

log "Tworzenie bazy danych i użytkownika ($DB_ENGINE)..."
if [[ "$DB_ENGINE" == "pgsql" ]]; then
  runuser -u postgres -- psql -v ON_ERROR_STOP=1 <<SQL
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${DB_USER}') THEN
    CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASS}';
  ELSE
    ALTER ROLE ${DB_USER} PASSWORD '${DB_PASS}';
  END IF;
END \$\$;
SQL
  runuser -u postgres -- psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 \
    || runuser -u postgres -- createdb -O "$DB_USER" "$DB_NAME"
  if [[ "$VECTOR" == "pgvector" ]]; then
    runuser -u postgres -- psql -d "$DB_NAME" -c "CREATE EXTENSION IF NOT EXISTS vector;"
  fi
else
  mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
fi

# ------------------------------- Qdrant (baza wektorowa) ---------------------
QDRANT_KEY=""
if [[ "$VECTOR" == "qdrant" ]]; then
  log "Instalacja Qdrant (open source, Apache 2.0)..."
  if [[ -z "$QDRANT_VERSION" ]]; then
    QDRANT_VERSION="$(curl -fsSL https://api.github.com/repos/qdrant/qdrant/releases/latest \
      | grep -m1 '"tag_name"' | sed -E 's/.*"(v[^"]+)".*/\1/' || echo 'v1.13.0')"
  fi
  [[ -n "$QDRANT_VERSION" ]] || die "Nie udało się ustalić wersji Qdrant."
  id qdrant &>/dev/null || useradd -r -m -d /var/lib/qdrant -s /usr/sbin/nologin qdrant
  mkdir -p /opt/qdrant /etc/qdrant /var/lib/qdrant/storage /var/lib/qdrant/snapshots
  TMP="$(mktemp -d)"
  curl -fsSL -o "$TMP/qdrant.tar.gz" \
    "https://github.com/qdrant/qdrant/releases/download/${QDRANT_VERSION}/qdrant-${QDRANT_ARCH}.tar.gz"
  tar -xzf "$TMP/qdrant.tar.gz" -C /opt/qdrant qdrant
  rm -rf "$TMP"
  if [[ -f /etc/qdrant/config.yaml ]]; then
    QDRANT_KEY="$(grep -m1 'api_key:' /etc/qdrant/config.yaml | awk '{print $2}')"
  fi
  [[ -n "$QDRANT_KEY" ]] || QDRANT_KEY="$(openssl rand -hex 24)"
  cat > /etc/qdrant/config.yaml <<YAML
storage:
  storage_path: /var/lib/qdrant/storage
  snapshots_path: /var/lib/qdrant/snapshots
service:
  host: 127.0.0.1
  http_port: 6333
  grpc_port: 6334
  api_key: ${QDRANT_KEY}
telemetry_disabled: true
YAML
  chown -R qdrant:qdrant /var/lib/qdrant /opt/qdrant
  chmod 640 /etc/qdrant/config.yaml && chown root:qdrant /etc/qdrant/config.yaml
  cat > /etc/systemd/system/qdrant.service <<UNIT
[Unit]
Description=Qdrant vector database
After=network.target

[Service]
User=qdrant
Group=qdrant
WorkingDirectory=/var/lib/qdrant
ExecStart=/opt/qdrant/qdrant --config-path /etc/qdrant/config.yaml
Restart=always
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
UNIT
  systemctl daemon-reload
  systemctl enable --now qdrant
fi

# ------------------------------- Plik .env -----------------------------------
log "Konfiguracja pliku .env..."
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "http://${DOMAIN}"
set_env APP_LOCALE pl
set_env DB_CONNECTION "$DB_ENGINE"
set_env DB_HOST 127.0.0.1
set_env DB_PORT "$DB_PORT"
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$DB_PASS"
set_env REDIS_HOST 127.0.0.1
set_env QUEUE_CONNECTION redis
set_env CACHE_STORE redis
set_env SESSION_DRIVER redis
set_env SERVICE_MODE local
set_env VECTOR_DRIVER "$VECTOR"
if [[ "$VECTOR" == "qdrant" ]]; then
  set_env QDRANT_URL "http://127.0.0.1:6333"
  set_env QDRANT_API_KEY "$QDRANT_KEY"
fi
grep -q '^SERVICE_TOKEN_SECRET=.\+' .env || set_env SERVICE_TOKEN_SECRET "$(openssl rand -hex 32)"

# ------------------------------- Zależności i build --------------------------
log "Ustawianie uprawnień..."
chown -R www-data:www-data "$APP_DIR"
as_www() { runuser -u www-data -- env HOME=/var/www COMPOSER_HOME=/var/www/.composer "$@"; }

log "Composer install..."
as_www composer install --no-dev --optimize-autoloader --no-interaction
if [[ -f package.json ]]; then
  log "Budowanie frontendu (npm)..."
  as_www npm ci --no-audit --no-fund || as_www npm install --no-audit --no-fund
  as_www npm run build || true
fi

grep -q '^APP_KEY=.\+' .env || as_www php artisan key:generate --force
log "Migracje i seedery..."
as_www php artisan migrate --force --seed
as_www php artisan storage:link || true
as_www php artisan optimize || warn "artisan optimize nie powiodło się – pomijam cache."

chown -R www-data:www-data "$APP_DIR"
chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# ------------------------------- Apache --------------------------------------
log "Konfiguracja Apache..."
a2enmod proxy proxy_fcgi proxy_http proxy_wstunnel rewrite headers setenvif >/dev/null
cat > /etc/apache2/sites-available/agenthub.conf <<VHOST
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    <FilesMatch "\.php$">
        SetHandler "proxy:unix:/run/php/php${PHP_VER}-fpm.sock|fcgi://localhost"
    </FilesMatch>

    # WebSocket (Laravel Reverb)
    ProxyPass        "/app"  "ws://127.0.0.1:8080/app"
    ProxyPassReverse "/app"  "ws://127.0.0.1:8080/app"
    ProxyPass        "/apps" "http://127.0.0.1:8080/apps"
    ProxyPassReverse "/apps" "http://127.0.0.1:8080/apps"

    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    ErrorLog \${APACHE_LOG_DIR}/agenthub-error.log
    CustomLog \${APACHE_LOG_DIR}/agenthub-access.log combined
</VirtualHost>
VHOST
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite agenthub >/dev/null
apache2ctl configtest
systemctl enable --now apache2
systemctl reload apache2

# ------------------------------- Usługi systemd + cron -----------------------
has_cmd() { as_www php artisan list --raw 2>/dev/null | grep -q "^$1"; }

make_unit() {
  cat > "/etc/systemd/system/agenthub-$1.service" <<UNIT
[Unit]
Description=AgentHub – $2
After=network.target redis-server.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan $3
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
UNIT
}

if has_cmd horizon; then
  make_unit horizon "Horizon (kolejki)" "horizon"
else
  make_unit queue "worker kolejki" "queue:work --sleep=3 --tries=3"
fi

if has_cmd reverb:start; then
  make_unit reverb "Reverb (WebSocket)" "reverb:start --host=127.0.0.1 --port=8080"
fi

# Szablony jednostek dla automatycznego provisioningu instancji (Sekcja 13.5)
mkdir -p /var/lib/agenthub/instances /etc/agenthub/instances
chown -R www-data:www-data /var/lib/agenthub/instances /etc/agenthub/instances
chmod 750 /var/lib/agenthub/instances /etc/agenthub/instances

cat > /etc/systemd/system/agenthub-hermes@.service <<HERMES_UNIT
[Unit]
Description=AgentHub Hermes Instance %i
After=network.target

[Service]
User=www-data
Group=www-data
EnvironmentFile=-/etc/agenthub/instances/%i.env
WorkingDirectory=/var/lib/agenthub/instances/%i
ExecStart=/usr/local/bin/hermes start --port=\${INSTANCE_PORT}
Restart=always
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
HERMES_UNIT

cat > /etc/systemd/system/agenthub-openclaw@.service <<OPENCLAW_UNIT
[Unit]
Description=AgentHub OpenClaw Instance %i
After=network.target

[Service]
User=www-data
Group=www-data
EnvironmentFile=-/etc/agenthub/instances/%i.env
WorkingDirectory=/var/lib/agenthub/instances/%i
ExecStart=/usr/local/bin/openclaw serve --port=\${INSTANCE_PORT}
Restart=always
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
OPENCLAW_UNIT

systemctl daemon-reload
for u in /etc/systemd/system/agenthub-*.service; do
  [[ "$u" == *"@"* ]] && continue
  systemctl enable --now "$(basename "$u")" || true
done

echo "* * * * * www-data cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/agenthub
chmod 644 /etc/cron.d/agenthub

# ------------------------------- Podsumowanie --------------------------------
umask 077
cat > "$CRED_FILE" <<TXT
AgentHub – dane instalacji ($(date '+%F %T'))
URL:            http://${DOMAIN}
Katalog:        ${APP_DIR}
Admin:          admin@admin.lan / admin   (zalecana zmiana hasła w Ustawieniach użytkownika)
Baza (${DB_ENGINE}): ${DB_NAME} / ${DB_USER} / ${DB_PASS}
Magazyn wektorów: ${VECTOR}
$( [[ "$VECTOR" == "qdrant" ]] && echo "Qdrant:         http://127.0.0.1:6333  api_key=${QDRANT_KEY}" )
TXT

log "Instalacja zakończona."
echo
echo "  Adres:   http://${DOMAIN}"
echo "  Login:   admin@admin.lan"
echo "  Hasło:   admin"
echo "  Dane techniczne zapisano w: $CRED_FILE"
echo
warn "Aplikacja działa po HTTP. Dla dostępu z sieci skonfiguruj HTTPS (np. certbot --apache) i zaporę sieciową."
