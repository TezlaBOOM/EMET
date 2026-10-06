#!/usr/bin/env bash
# =============================================================================
# Projekt-Emet (AgentHub) – Instalator dla kontenera Proxmox LXC
#
# Obsługuje czyste kontenery LXC z systemem:
#   - Debian 12 (Bookworm) - domyślny szablon Proxmox VE 8.x
#   - Debian 13 (Trixie)
#   - Ubuntu 24.04 LTS (Noble) / Ubuntu 22.04 LTS (Jammy)
#
# Instaluje i konfiguruje:
#   - Nginx (lub Apache) z obsługą WebSockets (Reverb) i PHP-FPM
#   - PHP 8.3 (lub 8.4) ze wszystkimi wymaganymi rozszerzeniami
#   - PostgreSQL (opcjonalnie pgvector) lub MariaDB/MySQL
#   - Redis Server
#   - Qdrant Vector Database (jako usługa systemd)
#   - Node.js LTS (20.x) + npm + Composer
#   - Usługi systemd: agenthub-queue, agenthub-reverb, instancje runnerów
#   - Cron scheduler dla zadań Laravel
#   - Migracje bazy i seed konta administratora
#
# Użycie wewnątrz kontenera LXC:
#   chmod +x scripts/install-lxc.sh
#   sudo ./scripts/install-lxc.sh [opcje]
#
# Opcje:
#   --repo=<git_url>        URL repozytorium do sklonowania (jeśli brak plików w app-dir)
#   --branch=<nazwa>        gałąź gita (domyślnie main / master)
#   --app-dir=<ścieżka>     katalog instalacji (domyślnie /var/www/agenthub)
#   --domain=<domena|IP>    nazwa domeny lub IP (domyślnie auto-detekcja IP kontenera)
#   --webserver=nginx|apache serwer www (domyślnie nginx)
#   --db=pgsql|mysql        silnik bazy danych (domyślnie pgsql)
#   --vector=qdrant|pgvector silnik wektorowy (domyślnie qdrant)
#   --php=8.3|8.4           wersja PHP (domyślnie 8.3)
#   --with-docker           instaluj silnik Docker (wymaga nesting=1 na hoście Proxmox)
#   --force                 pomiń sprawdzanie wersji systemu
#   --help, -h              wyświetl tę pomoc
# =============================================================================
set -Eeuo pipefail

# ---------------- Kolory i funkcje logowania ----------------
CLR_RESET="\033[0m"
CLR_INFO="\033[1;34m"
CLR_SUCCESS="\033[1;32m"
CLR_WARN="\033[1;33m"
CLR_ERROR="\033[1;31m"
CLR_TITLE="\033[1;35m"

log_info()    { echo -e "${CLR_INFO}[EMET-LXC]${CLR_RESET} $*"; }
log_success() { echo -e "${CLR_SUCCESS}[SUKCES]${CLR_RESET} $*"; }
log_warn()    { echo -e "${CLR_WARN}[UWAGA]${CLR_RESET} $*"; }
log_error()   { echo -e "${CLR_ERROR}[BŁĄD]${CLR_RESET} $*" >&2; }
die()         { log_error "$*"; exit 1; }
trap 'die "Wystąpił błąd w linii $LINENO. Sprawdź logi powyżej."' ERR

# ---------------- Domyślne parametry ----------------
APP_DIR="/var/www/agenthub"
REPO_URL="https://github.com/TezlaBOOM/EMET.git"
BRANCH="main"
DOMAIN=""
WEBSERVER="nginx"
DB_ENGINE="pgsql"
VECTOR="qdrant"
PHP_VER="8.4"
WITH_DOCKER=0
FORCE=0
QDRANT_VERSION=""
CRED_FILE="/root/agenthub-install.txt"

# ---------------- Parsowanie argumentów ----------------
for arg in "$@"; do
  case "$arg" in
    --repo=*)        REPO_URL="${arg#*=}" ;;
    --branch=*)      BRANCH="${arg#*=}" ;;
    --app-dir=*)     APP_DIR="${arg#*=}" ;;
    --domain=*)      DOMAIN="${arg#*=}" ;;
    --webserver=*)   WEBSERVER="${arg#*=}" ;;
    --db=*)          DB_ENGINE="${arg#*=}" ;;
    --vector=*)      VECTOR="${arg#*=}" ;;
    --php=*)         PHP_VER="${arg#*=}" ;;
    --with-docker)   WITH_DOCKER=1 ;;
    --force)         FORCE=1 ;;
    --help|-h)
      sed -n '2,32p' "$0"
      exit 0
      ;;
    *) die "Nieznany parametr: $arg (użyj --help)" ;;
  esac
done

# ---------------- Weryfikacja uprawnień i systemu ----------------
[[ $EUID -eq 0 ]] || die "Skrypt musi być uruchomiony z uprawnieniami roota (sudo)."
[[ "$DB_ENGINE" =~ ^(pgsql|mysql)$ ]] || die "Parametr --db musi mieć wartość pgsql lub mysql"
[[ "$VECTOR" =~ ^(qdrant|pgvector)$ ]] || die "Parametr --vector musi mieć wartość qdrant lub pgvector"
[[ "$WEBSERVER" =~ ^(nginx|apache)$ ]] || die "Parametr --webserver musi mieć wartość nginx lub apache"
[[ "$PHP_VER" =~ ^(8\.3|8\.4|8\.5|latest)$ ]] || die "Parametr --php musi mieć wartość 8.4, 8.3 lub latest (domyślnie: 8.4)"
[[ "$VECTOR" == "pgvector" && "$DB_ENGINE" != "pgsql" ]] && die "pgvector wymaga bazy PostgreSQL (--db=pgsql)"

if [[ ! -f /etc/os-release ]]; then
  [[ $FORCE -eq 1 ]] || die "Nie znaleziono pliku /etc/os-release."
fi

. /etc/os-release
OS_ID="${ID:-unknown}"
OS_VERSION_ID="${VERSION_ID:-}"
OS_CODENAME="${VERSION_CODENAME:-}"

log_info "Wykryto system operacyjny: ${PRETTY_NAME:-$OS_ID} ($OS_ID $OS_VERSION_ID)"

if [[ "$OS_ID" != "debian" && "$OS_ID" != "ubuntu" ]]; then
  if [[ $FORCE -eq 0 ]]; then
    die "Ten instalator jest przeznaczony dla systemów Debian lub Ubuntu. Użyj --force, aby kontynuować mimo to."
  fi
  log_warn "Kontynuacja na nieobsługiwanym systemie (--force)."
fi

# Wykrywanie architektury procesora
ARCH="$(uname -m)"
case "$ARCH" in
  x86_64)  QDRANT_ARCH="x86_64-unknown-linux-gnu" ;;
  aarch64) QDRANT_ARCH="aarch64-unknown-linux-musl" ;;
  *) die "Nieobsługiwana architektura procesora: $ARCH" ;;
esac

# Auto-detekcja IP kontenera LXC
DETECTED_IP="$(ip -4 addr show scope global 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -n1 || true)"
if [[ -z "$DETECTED_IP" ]]; then
  DETECTED_IP="$(hostname -I 2>/dev/null | awk '{print $1}' || echo '127.0.0.1')"
fi

if [[ -z "$DOMAIN" ]]; then
  DOMAIN="$DETECTED_IP"
fi
log_info "Docelowy adres dostępowy: http://${DOMAIN} (IP: ${DETECTED_IP})"

export DEBIAN_FRONTEND=noninteractive

# Upewnienie się co do nazwy kodowej dystrybucji (fallback dla minimalnych kontenerów LXC)
if [[ -z "$OS_CODENAME" ]]; then
  if [[ "$OS_ID" == "debian" ]]; then
    case "${OS_VERSION_ID:-}" in
      11*) OS_CODENAME="bullseye" ;;
      12*) OS_CODENAME="bookworm" ;;
      13*) OS_CODENAME="trixie" ;;
      *)   OS_CODENAME="bookworm" ;;
    esac
  elif [[ "$OS_ID" == "ubuntu" ]]; then
    case "${OS_VERSION_ID:-}" in
      20.04*) OS_CODENAME="focal" ;;
      22.04*) OS_CODENAME="jammy" ;;
      24.04*) OS_CODENAME="noble" ;;
      *)      OS_CODENAME="noble" ;;
    esac
  fi
fi

# Funkcja czekająca na zwolnienie blokady dpkg/apt (częsty przypadek w świeżym LXC przez apt-daily)
wait_for_apt_lock() {
  systemctl stop apt-daily.service apt-daily.timer apt-daily-upgrade.service apt-daily-upgrade.timer 2>/dev/null || true
  local max_wait=60
  local waited=0
  while fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 || \
        fuser /var/lib/dpkg/lock >/dev/null 2>&1 || \
        fuser /var/lib/apt/lists/lock >/dev/null 2>&1; do
    if [[ $waited -ge $max_wait ]]; then
      log_warn "Przekroczono czas oczekiwania na blokadę apt. Zwalnianie procesów..."
      killall -9 apt apt-get dpkg 2>/dev/null || true
      rm -f /var/lib/dpkg/lock-frontend /var/lib/dpkg/lock /var/lib/apt/lists/lock 2>/dev/null || true
      dpkg --configure -a 2>/dev/null || true
      break
    fi
    log_info "Oczekiwanie na zwolnienie blokady menedżera pakietów (apt-daily)... (${waited}s)"
    sleep 3
    waited=$((waited + 3))
  done
  dpkg --configure -a 2>/dev/null || true
}

# ---------------- Krok 1: Pakiety podstawowe i repozytoria ----------------
echo -e "\n${CLR_TITLE}>>> KROK 1/7: Instalacja narzędzi bazowych i konfiguracja repozytoriów...${CLR_RESET}"

wait_for_apt_lock

log_info "Aktualizacja indeksu pakietów apt..."
apt-get update -y --allow-releaseinfo-change || apt-get update -y

log_info "Instalacja pakietów podstawowych..."
# Podstawowe pakiety wymagane przez instalator
CORE_DEPS=(
  ca-certificates
  curl
  wget
  gnupg
  git
  rsync
  unzip
  zip
  tar
  cron
  openssl
  jq
  iproute2
)
apt-get install -y "${CORE_DEPS[@]}"

# Pakiety opcjonalne (instalowane bez przerywania skryptu, jeśli niedostępne w minimalnym szablonie)
for opt_pkg in software-properties-common lsb-release net-tools apt-transport-https; do
  apt-get install -y "$opt_pkg" 2>/dev/null || true
done

# Repozytorium PHP (Ondrej Sury dla Debiana / Ubuntu)
log_info "Konfiguracja repozytorium PHP ($PHP_VER dla $OS_CODENAME)..."
mkdir -p /etc/apt/keyrings

if [[ "$OS_ID" == "debian" ]]; then
  curl -sSLo /etc/apt/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
  echo "deb [signed-by=/etc/apt/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ ${OS_CODENAME} main" \
    > /etc/apt/sources.list.d/php.list
elif [[ "$OS_ID" == "ubuntu" ]]; then
  if command -v add-apt-repository >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php
  else
    curl -sSLo /etc/apt/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
    echo "deb [signed-by=/etc/apt/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ ${OS_CODENAME} main" \
      > /etc/apt/sources.list.d/php.list
  fi
fi

# Repozytorium Node.js 20 LTS (NodeSource)
log_info "Konfiguracja repozytorium Node.js 20 LTS..."
if ! command -v node >/dev/null 2>&1 || [[ "$(node -v | cut -d'.' -f1 | tr -d 'v')" -lt 18 ]]; then
  curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
    | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg --yes
  echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" \
    > /etc/apt/sources.list.d/nodesource.list
fi

wait_for_apt_lock
apt-get update -y --allow-releaseinfo-change || apt-get update -y

# ---------------- Krok 2: Instalacja PHP, serwera WWW, Node i Composera ----------------
if [[ "$PHP_VER" == "latest" ]]; then
  DETECTED_PHP="$(apt-cache search '^php[0-9]\.[0-9]-cli$' 2>/dev/null | awk '{print $1}' | sed 's/php//;s/-cli//' | sort -V | tail -n1 || true)"
  if [[ -n "$DETECTED_PHP" && "$DETECTED_PHP" =~ ^8\.[3-5]$ ]]; then
    PHP_VER="$DETECTED_PHP"
  else
    PHP_VER="8.4"
  fi
fi

echo -e "\n${CLR_TITLE}>>> KROK 2/7: Wymuszanie instalacji najnowszego PHP ${PHP_VER}, serwera ${WEBSERVER}, Node.js i Composera...${CLR_RESET}"

apt-get install -y \
  "php${PHP_VER}-fpm" \
  "php${PHP_VER}-cli" \
  "php${PHP_VER}-common" \
  "php${PHP_VER}-mbstring" \
  "php${PHP_VER}-xml" \
  "php${PHP_VER}-curl" \
  "php${PHP_VER}-zip" \
  "php${PHP_VER}-bcmath" \
  "php${PHP_VER}-intl" \
  "php${PHP_VER}-gd" \
  "php${PHP_VER}-redis" \
  "php${PHP_VER}-opcache" \
  "php${PHP_VER}-readline"

# Wymuszenie najnowszej wersji PHP w CLI jako domyślnej w systemie
log_info "Wymuszanie PHP ${PHP_VER} jako domyślnej wersji systemowej (update-alternatives)..."
update-alternatives --set php "/usr/bin/php${PHP_VER}" 2>/dev/null || true
update-alternatives --set phar "/usr/bin/phar${PHP_VER}" 2>/dev/null || true
update-alternatives --set phpize "/usr/bin/phpize${PHP_VER}" 2>/dev/null || true
update-alternatives --set php-config "/usr/bin/php-config${PHP_VER}" 2>/dev/null || true

INSTALLED_PHP_VER="$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "$PHP_VER")"
log_success "Zainstalowana i aktywna wersja PHP w systemie: ${INSTALLED_PHP_VER}"

# Zatrzymanie i wyłączenie starszych wersji PHP-FPM, jeśli były obecne w systemie
for old_fpm in $(systemctl list-unit-files 'php*-fpm.service' 2>/dev/null | awk '{print $1}' | grep -E '^php[0-9]' || true); do
  if [[ "$old_fpm" != "php${PHP_VER}-fpm.service" ]]; then
    log_info "Zatrzymywanie starszej usługi PHP-FPM: $old_fpm"
    systemctl stop "$old_fpm" 2>/dev/null || true
    systemctl disable "$old_fpm" 2>/dev/null || true
  fi
done

# Composer
if ! command -v composer >/dev/null 2>&1; then
  log_info "Pobieranie i instalacja Composer..."
  curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
  rm -f /tmp/composer-setup.php
fi

# Node.js i npm
apt-get install -y nodejs
log_info "Wersja Node.js: $(node -v), npm: $(npm -v), Composer: $(composer --version | head -n1)"

# Redis Server
log_info "Instalacja i konfiguracja Redis..."
apt-get install -y redis-server
systemctl enable --now redis-server

# Konfiguracja PHP-FPM dla wysokiej wydajności
cat > "/etc/php/${PHP_VER}/fpm/conf.d/99-agenthub.ini" <<INI
memory_limit = 512M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120
max_input_time = 120
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 0
INI

systemctl enable --now "php${PHP_VER}-fpm"
systemctl restart "php${PHP_VER}-fpm"

# ---------------- Krok 3: Baza Danych i Magazyn Wektorowy ----------------
echo -e "\n${CLR_TITLE}>>> KROK 3/7: Konfiguracja bazy danych (${DB_ENGINE}) oraz magazynu wektorowego (${VECTOR})...${CLR_RESET}"

wait_for_apt_lock

DB_NAME="agenthub"
DB_USER="agenthub"
DB_PASS="$(openssl rand -hex 16)"

if [[ "$DB_ENGINE" == "pgsql" ]]; then
  log_info "Instalacja PostgreSQL..."
  apt-get install -y postgresql postgresql-contrib "php${PHP_VER}-pgsql"
  systemctl enable --now postgresql
  DB_PORT=5432

  PG_MAJOR="$(pg_lsclusters -h 2>/dev/null | awk 'NR==1{print $1}')"
  if [[ -z "$PG_MAJOR" ]]; then PG_MAJOR="16"; fi

  if [[ "$VECTOR" == "pgvector" ]]; then
    log_info "Instalacja rozszerzenia pgvector dla PostgreSQL ${PG_MAJOR}..."
    apt-get install -y "postgresql-${PG_MAJOR}-pgvector" || log_warn "Pakiet pgvector nie był dostępny bezpośrednio z repo."
  fi

  # Utworzenie roli i bazy PostgreSQL
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
    runuser -u postgres -- psql -d "$DB_NAME" -c "CREATE EXTENSION IF NOT EXISTS vector;" || true
  fi

else
  log_info "Instalacja MariaDB..."
  apt-get install -y mariadb-server "php${PHP_VER}-mysql"
  systemctl enable --now mariadb
  DB_PORT=3306

  mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
fi

# Qdrant Vector Database
QDRANT_KEY=""
if [[ "$VECTOR" == "qdrant" ]]; then
  log_info "Instalacja bazy wektorowej Qdrant (jako serwis systemd)..."
  if [[ -z "$QDRANT_VERSION" ]]; then
    # 1. Przekierowanie nagłówka Location z GitHub Releases (omija limit API GitHub)
    QDRANT_VERSION="$(curl -sI https://github.com/qdrant/qdrant/releases/latest 2>/dev/null | grep -i '^location:' | sed -E 's|.*/tag/(v[^/ \r\n]+).*|\1|' | tr -d '\r\n' || true)"
  fi
  if [[ -z "$QDRANT_VERSION" ]]; then
    # 2. Rezerwa: GitHub API
    QDRANT_VERSION="$(curl -fsSL https://api.github.com/repos/qdrant/qdrant/releases/latest 2>/dev/null | grep -m1 '"tag_name"' | sed -E 's/.*"(v[^"]+)".*/\1/' | tr -d '\r\n' || true)"
  fi
  # 3. Zabezpieczenie: znana stabilna wersja
  if [[ -z "$QDRANT_VERSION" || ! "$QDRANT_VERSION" =~ ^v[0-9] ]]; then
    QDRANT_VERSION="v1.19.2"
  fi
  log_info "Wersja Qdrant do zainstalowania: ${QDRANT_VERSION} (${QDRANT_ARCH})"

  id qdrant &>/dev/null || useradd -r -m -d /var/lib/qdrant -s /usr/sbin/nologin qdrant 2>/dev/null || useradd -r -m -d /var/lib/qdrant -s /bin/false qdrant
  mkdir -p /opt/qdrant /etc/qdrant /var/lib/qdrant/storage /var/lib/qdrant/snapshots

  TMP_QDRANT="$(mktemp -d)"
  DOWNLOAD_URL="https://github.com/qdrant/qdrant/releases/download/${QDRANT_VERSION}/qdrant-${QDRANT_ARCH}.tar.gz"
  log_info "Pobieranie archiwum Qdrant ($DOWNLOAD_URL)..."

  if ! curl -fSL --connect-timeout 20 --retry 3 -o "$TMP_QDRANT/qdrant.tar.gz" "$DOWNLOAD_URL"; then
    log_warn "Pobieranie wersji ${QDRANT_VERSION} nie powiodło się, próba pobrania sprawdzonej wersji stabilnej v1.13.2..."
    curl -fSL --connect-timeout 20 --retry 3 -o "$TMP_QDRANT/qdrant.tar.gz" \
      "https://github.com/qdrant/qdrant/releases/download/v1.13.2/qdrant-${QDRANT_ARCH}.tar.gz" || die "Nie udało się pobrać binarki Qdrant z serwerów GitHub."
  fi

  tar -xzf "$TMP_QDRANT/qdrant.tar.gz" -C /opt/qdrant qdrant
  chmod +x /opt/qdrant/qdrant
  rm -rf "$TMP_QDRANT"

  QDRANT_KEY="$(openssl rand -hex 24)"
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
Description=Qdrant Vector Database
After=network.target

[Service]
User=qdrant
Group=qdrant
WorkingDirectory=/var/lib/qdrant
ExecStart=/opt/qdrant/qdrant --config-path /etc/qdrant/config.yaml
Restart=always
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
UNIT

  systemctl daemon-reload
  systemctl enable --now qdrant
  sleep 2
  log_success "Qdrant uruchomiony na porcie 6333."
fi

# Opcjonalny Docker (dla runnerów Hermes / OpenClaw)
if [[ $WITH_DOCKER -eq 1 ]]; then
  log_info "Instalacja silnika Docker w kontenerze LXC..."
  apt-get install -y docker.io docker-compose-plugin || apt-get install -y docker.io docker-compose
  systemctl enable --now docker
  id agenthub-runner &>/dev/null || useradd -r -m -s /usr/sbin/nologin -G docker agenthub-runner
  log_success "Docker zainstalowany (upewnij się, że na hoście Proxmox kontener ma opcję 'nesting=1')."
fi

# ---------------- Krok 4: Wdrożenie kodu aplikacji ----------------
echo -e "\n${CLR_TITLE}>>> KROK 4/7: Przygotowanie kodu aplikacji w ${APP_DIR}...${CLR_RESET}"

CURRENT_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOCAL_REPO_DIR="$(cd "$CURRENT_SCRIPT_DIR/.." && pwd)"

mkdir -p "$APP_DIR"

if [[ -f "$LOCAL_REPO_DIR/artisan" && "$LOCAL_REPO_DIR" != "$APP_DIR" ]]; then
  log_info "Kopiowanie plików projektu z bieżącego katalogu ($LOCAL_REPO_DIR) do $APP_DIR..."
  rsync -a --exclude .git --exclude node_modules --exclude vendor --exclude storage/logs/* "$LOCAL_REPO_DIR/" "$APP_DIR/"
elif [[ ! -f "$APP_DIR/artisan" ]]; then
  if [[ -n "$REPO_URL" ]]; then
    log_info "Klonowanie repozytorium $REPO_URL ($BRANCH)..."
    git clone --depth 1 -b "$BRANCH" "$REPO_URL" "$APP_DIR"
  else
    die "Brak plików aplikacji w $APP_DIR i nie podano parametru --repo=<git_url>."
  fi
else
  log_info "Pliki aplikacji są już obecne w $APP_DIR."
fi

cd "$APP_DIR"

# Konfiguracja .env
if [[ ! -f .env ]]; then
  if [[ -f .env.example ]]; then
    cp .env.example .env
  else
    die "Brak pliku .env.example w $APP_DIR"
  fi
fi

set_env_val() {
  local k="$1" v="$2"
  if grep -q "^${k}=" .env; then
    sed -i "s|^${k}=.*|${k}=${v}|" .env
  else
    echo "${k}=${v}" >> .env
  fi
}

set_env_val APP_NAME "Projekt-Emet"
set_env_val APP_ENV production
set_env_val APP_DEBUG false
set_env_val APP_URL "http://${DOMAIN}"
set_env_val APP_LOCALE pl
set_env_val DB_CONNECTION "$DB_ENGINE"
set_env_val DB_HOST 127.0.0.1
set_env_val DB_PORT "$DB_PORT"
set_env_val DB_DATABASE "$DB_NAME"
set_env_val DB_USERNAME "$DB_USER"
set_env_val DB_PASSWORD "$DB_PASS"
set_env_val REDIS_HOST 127.0.0.1
set_env_val REDIS_PORT 6379
set_env_val QUEUE_CONNECTION redis
set_env_val CACHE_STORE redis
set_env_val SESSION_DRIVER redis
set_env_val SERVICE_MODE local
set_env_val VECTOR_DRIVER "$VECTOR"

if [[ "$VECTOR" == "qdrant" ]]; then
  set_env_val QDRANT_URL "http://127.0.0.1:6333"
  set_env_val QDRANT_API_KEY "$QDRANT_KEY"
fi

if ! grep -q '^SERVICE_TOKEN_SECRET=.\+' .env; then
  set_env_val SERVICE_TOKEN_SECRET "$(openssl rand -hex 32)"
fi

# ---------------- Krok 5: Instalacja zależności i migracje ----------------
echo -e "\n${CLR_TITLE}>>> KROK 5/7: Instalacja zależności Composer/npm, migracje i build...${CLR_RESET}"

export COMPOSER_ALLOW_SUPERUSER=1
git config --system --add safe.directory "$APP_DIR" 2>/dev/null || true
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

log_info "Instalacja pakietów PHP (composer install)..."
composer install --no-dev --optimize-autoloader --no-interaction

if [[ -f package.json ]]; then
  log_info "Kompilacja zasobów frontendu (npm install & build)..."
  npm install --no-audit --no-fund
  npm run build
fi

# Generowanie klucza aplikacji, jeśli brak
if ! grep -q '^APP_KEY=base64:.\+' .env; then
  log_info "Generowanie klucza aplikacji (APP_KEY)..."
  php artisan key:generate --force
fi

log_info "Wykonywanie migracji bazy danych i seedowanie danych początkowych..."
php artisan migrate --force --seed

log_info "Tworzenie linku symbolicznego do storage..."
php artisan storage:link || true

log_info "Optymalizacja cache aplikacji (config, route, views)..."
php artisan optimize || log_warn "Nie udało się zoptymalizować cache'u - kontynuuję."

# Pełne uprawnienia dla www-data
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# ---------------- Krok 6: Konfiguracja serwera WWW ----------------
echo -e "\n${CLR_TITLE}>>> KROK 6/7: Konfiguracja serwera WWW (${WEBSERVER})...${CLR_RESET}"

wait_for_apt_lock

if [[ "$WEBSERVER" == "nginx" ]]; then
  apt-get install -y nginx

  # Jeśli Apache był wcześniej zainstalowany, wyłącz go
  if systemctl is-active --quiet apache2 2>/dev/null; then
    systemctl stop apache2
    systemctl disable apache2
  fi

  cat > /etc/nginx/sites-available/agenthub.conf <<NGINX_CONF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} _ localhost ${DETECTED_IP};
    root ${APP_DIR}/public;

    index index.php index.html;
    charset utf-8;

    client_max_body_size 64M;

    # Obsługa Laravel Reverb WebSockets
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 60s;
        proxy_send_timeout 60s;
    }

    location /apps {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 180s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX_CONF

  rm -f /etc/nginx/sites-enabled/default
  ln -sf /etc/nginx/sites-available/agenthub.conf /etc/nginx/sites-enabled/agenthub.conf
  nginx -t
  systemctl enable --now nginx
  systemctl restart nginx

else
  apt-get install -y apache2
  a2enmod proxy proxy_fcgi proxy_http proxy_wstunnel rewrite headers setenvif >/dev/null

  cat > /etc/apache2/sites-available/agenthub.conf <<APACHE_CONF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ServerAlias *
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
APACHE_CONF

  a2dissite 000-default >/dev/null 2>&1 || true
  a2ensite agenthub >/dev/null
  apache2ctl configtest
  systemctl enable --now apache2
  systemctl restart apache2
fi

# ---------------- Krok 7: Usługi systemd i harmonogram zadań ----------------
echo -e "\n${CLR_TITLE}>>> KROK 7/7: Konfiguracja usług systemd (Queue, Reverb) i harmonogramu cron...${CLR_RESET}"

# Usługa kolejki (Worker)
cat > /etc/systemd/system/agenthub-queue.service <<UNIT
[Unit]
Description=AgentHub Queue Worker
After=network.target redis-server.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=120
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
UNIT

# Usługa WebSocket (Reverb)
cat > /etc/systemd/system/agenthub-reverb.service <<UNIT
[Unit]
Description=AgentHub Reverb WebSocket Server
After=network.target redis-server.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
UNIT

# Katalogi dla instancji agentów (Hermes / OpenClaw)
mkdir -p /var/lib/agenthub/instances /etc/agenthub/instances
chown -R www-data:www-data /var/lib/agenthub/instances /etc/agenthub/instances
chmod 750 /var/lib/agenthub/instances /etc/agenthub/instances

# Szablony usług dla dynamicznych instancji
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
systemctl enable --now agenthub-queue.service
systemctl enable --now agenthub-reverb.service

# Harmonogram zadań Laravel (Cron)
echo "* * * * * www-data cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/agenthub
chmod 644 /etc/cron.d/agenthub

# ---------------- Diagnostyka i zapis poświadczeń ----------------
echo -e "\n${CLR_TITLE}>>> Weryfikacja instalacji (Self-Test)...${CLR_RESET}"
php artisan agenthub:selftest || log_warn "Niektóre testy diagnostyczne zgłosiły ostrzeżenia (np. brak skonfigurowanych kluczy zewnętrznych LLM)."

umask 077
cat > "$CRED_FILE" <<TXT
=============================================================================
Projekt-Emet (AgentHub) – Dane Instalacji Proxmox LXC
Data instalacji: $(date '+%Y-%m-%d %H:%M:%S')
=============================================================================

Adres URL:             http://${DOMAIN} (lub http://${DETECTED_IP})
Lokalny katalog:       ${APP_DIR}

Panel Administratora:
  E-mail:              admin@admin.lan
  Hasło:               admin
  (Zmień hasło po pierwszym zalogowaniu w kreatorze lub ustawieniach profilu!)

Baza danych (${DB_ENGINE}):
  Host:                127.0.0.1:${DB_PORT}
  Baza:                ${DB_NAME}
  Użytkownik:          ${DB_USER}
  Hasło:               ${DB_PASS}

Magazyn Wektorowy (${VECTOR}):
$( [[ "$VECTOR" == "qdrant" ]] && echo "  URL:                 http://127.0.0.1:6333" )
$( [[ "$VECTOR" == "qdrant" ]] && echo "  API Key:             ${QDRANT_KEY}" )

Usługi systemd:
  Serwer WWW:          ${WEBSERVER}
  PHP-FPM:             php${PHP_VER}-fpm
  Kolejki zadań:       agenthub-queue
  WebSockets (Reverb): agenthub-reverb
  Baza wektorowa:      qdrant
=============================================================================
TXT

echo
echo -e "${CLR_SUCCESS}======================================================================${CLR_RESET}"
echo -e "${CLR_SUCCESS}     INSTALACJA W KONTENERZE PROXMOX LXC ZAKOŃCZONA SUKCESEM!        ${CLR_RESET}"
echo -e "${CLR_SUCCESS}======================================================================${CLR_RESET}"
echo
echo -e "  ${CLR_TITLE}Adres w przeglądarce:${CLR_RESET} http://${DOMAIN}"
if [[ "$DOMAIN" != "$DETECTED_IP" && -n "$DETECTED_IP" ]]; then
  echo -e "  ${CLR_TITLE}Alternatywny adres:${CLR_RESET}   http://${DETECTED_IP}"
fi
echo -e "  ${CLR_TITLE}Login administratora:${CLR_RESET} admin@admin.lan"
echo -e "  ${CLR_TITLE}Hasło administratora:${CLR_RESET} admin"
echo
echo -e "  Poświadczenia i konfiguracja zapisane w pliku: ${CLR_INFO}${CRED_FILE}${CLR_RESET}"
echo
echo -e "${CLR_WARN}Wskazówka:${CLR_RESET} Po zalogowaniu powita Cię 7-etapowy Setup Wizard,"
echo "gdzie możesz skonfigurować klucze API dla modeli LLM (OpenAI, Anthropic, Gemini itp.)."
echo
