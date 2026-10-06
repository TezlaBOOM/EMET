#!/usr/bin/env bash
# =============================================================================
# Projekt-Emet (AgentHub) – Skrypt tworzenia i provisioningu LXC na hoście Proxmox VE
#
# Uruchamiany w powłoce Proxmox VE (PVE Shell / SSH na węźle Proxmox).
# Automatycznie:
#   1. Weryfikuje środowisko Proxmox VE (pct, pveam).
#   2. Pobiera szablon Debian 12 (jeśli brak).
#   3. Tworzy kontener LXC z odpowiednimi parametrami (nesting=1, keyctl=1, RAM, CPU).
#   4. Uruchamia kontener i czeka na przydział sieci.
#   5. Kopiuje projekt lub klonuje repozytorium do kontenera.
#   6. Uruchamia scripts/install-lxc.sh wewnątrz kontenera.
#
# Użycie na hoście Proxmox VE:
#   chmod +x proxmox-host-create.sh
#   ./proxmox-host-create.sh [opcje]
#
# Opcje:
#   --ctid=<ID>             ID kontenera (domyślnie: następny wolny ID w PVE)
#   --hostname=<nazwa>      Nazwa hosta (domyślnie: emet-agenthub)
#   --cores=<N>             Liczba rdzeni CPU (domyślnie: 2)
#   --memory=<MB>           Pamięć RAM w MB (domyślnie: 2048)
#   --swap=<MB>             Pamięć SWAP w MB (domyślnie: 1024)
#   --disk=<GB>             Rozmiar dysku w GB (domyślnie: 16)
#   --storage=<magazyn>     Magazyn dysku (domyślnie: local-lvm lub local)
#   --bridge=<interfejs>    Bridge sieciowy (domyślnie: vmbr0)
#   --ip=<dhcp|IP/CIDR>     Adres IP (domyślnie: dhcp)
#   --gateway=<IP>          Brama domyślna (wymagana przy statycznym IP)
#   --repo=<git_url>        URL repozytorium gita do pobrania wewnątrz kontenera
#   --help, -h              Pomoc
# =============================================================================
set -Eeuo pipefail

CLR_RESET="\033[0m"
CLR_INFO="\033[1;34m"
CLR_SUCCESS="\033[1;32m"
CLR_WARN="\033[1;33m"
CLR_ERROR="\033[1;31m"
CLR_TITLE="\033[1;35m"

log_info()    { echo -e "${CLR_INFO}[PVE-HOST]${CLR_RESET} $*"; }
log_success() { echo -e "${CLR_SUCCESS}[SUKCES]${CLR_RESET} $*"; }
log_warn()    { echo -e "${CLR_WARN}[UWAGA]${CLR_RESET} $*"; }
log_error()   { echo -e "${CLR_ERROR}[BŁĄD]${CLR_RESET} $*" >&2; }
die()         { log_error "$*"; exit 1; }

# Sprawdzenie czy uruchomiono na hoście Proxmox VE
if ! command -v pct >/dev/null 2>&1 || ! command -v pvesm >/dev/null 2>&1; then
  die "To polecenie musi być uruchomione bezpośrednio na hoście Proxmox VE (wymagane narzędzia 'pct' i 'pvesm')."
fi

CTID=""
HOSTNAME="emet-agenthub"
CORES=2
MEMORY=2048
SWAP=1024
DISK=16
STORAGE=""
BRIDGE="vmbr0"
IP_CONFIG="dhcp"
GATEWAY=""
REPO_URL="https://github.com/TezlaBOOM/EMET.git"

for arg in "$@"; do
  case "$arg" in
    --ctid=*)     CTID="${arg#*=}" ;;
    --hostname=*) HOSTNAME="${arg#*=}" ;;
    --cores=*)    CORES="${arg#*=}" ;;
    --memory=*)   MEMORY="${arg#*=}" ;;
    --swap=*)     SWAP="${arg#*=}" ;;
    --disk=*)     DISK="${arg#*=}" ;;
    --storage=*)  STORAGE="${arg#*=}" ;;
    --bridge=*)   BRIDGE="${arg#*=}" ;;
    --ip=*)       IP_CONFIG="${arg#*=}" ;;
    --gateway=*)  GATEWAY="${arg#*=}" ;;
    --repo=*)     REPO_URL="${arg#*=}" ;;
    --help|-h)
      sed -n '2,27p' "$0"
      exit 0
      ;;
    *) die "Nieznana opcja: $arg" ;;
  esac
done

# Ustalenie wolnego ID kontenera
if [[ -z "$CTID" ]]; then
  CTID="$(pvesh get /cluster/nextid)"
  log_info "Przydzielono automatycznie wolne ID kontenera: ${CTID}"
fi

# Ustalenie domyślnego magazynu
if [[ -z "$STORAGE" ]]; then
  if pvesm status -storage local-lvm &>/dev/null; then
    STORAGE="local-lvm"
  elif pvesm status -storage local-zfs &>/dev/null; then
    STORAGE="local-zfs"
  else
    STORAGE="local"
  fi
fi
log_info "Wybrany magazyn dla dysku LXC: ${STORAGE}"

# Sprawdzenie lub pobranie szablonu Debian 12
TEMPLATE_STORAGE="local"
log_info "Wyszukiwanie szablonu Debian 12 w magazynie ${TEMPLATE_STORAGE}..."
TEMPLATE_PATH="$(pveam list "$TEMPLATE_STORAGE" 2>/dev/null | grep -E 'debian-12-standard' | awk '{print $1}' | head -n1 || true)"

if [[ -z "$TEMPLATE_PATH" ]]; then
  log_info "Aktualizacja listy szablonów Proxmox (pveam update)..."
  pveam update || true
  AVAILABLE_TEMPLATE="$(pveam available | grep -E 'debian-12-standard' | awk '{print $2}' | sort -V | tail -n1 || true)"
  if [[ -z "$AVAILABLE_TEMPLATE" ]]; then
    AVAILABLE_TEMPLATE="$(pveam available | grep -E 'debian-11-standard' | awk '{print $2}' | sort -V | tail -n1 || true)"
  fi
  [[ -n "$AVAILABLE_TEMPLATE" ]] || die "Nie znaleziono odpowiedniego szablonu Debiana w pveam available."

  log_info "Pobieranie szablonu ${AVAILABLE_TEMPLATE} do magazynu ${TEMPLATE_STORAGE}..."
  pveam download "$TEMPLATE_STORAGE" "$AVAILABLE_TEMPLATE"
  TEMPLATE_PATH="${TEMPLATE_STORAGE}:vztmpl/${AVAILABLE_TEMPLATE}"
fi

log_info "Używany szablon kontenera: ${TEMPLATE_PATH}"

# Konfiguracja sieci
NET_PARAM="name=eth0,bridge=${BRIDGE},ip=${IP_CONFIG}"
if [[ "$IP_CONFIG" != "dhcp" && -n "$GATEWAY" ]]; then
  NET_PARAM="${NET_PARAM},gw=${GATEWAY}"
fi

# Hasło roota dla nowego kontenera
ROOT_PASSWORD="$(openssl rand -hex 12)"

log_info "Tworzenie kontenera LXC (CTID: ${CTID}, Hostname: ${HOSTNAME})..."
pct create "$CTID" "$TEMPLATE_PATH" \
  --hostname "$HOSTNAME" \
  --cores "$CORES" \
  --memory "$MEMORY" \
  --swap "$SWAP" \
  --rootfs "${STORAGE}:${DISK}" \
  --net0 "$NET_PARAM" \
  --ostype debian \
  --features nesting=1,keyctl=1 \
  --onboot 1 \
  --unprivileged 1 \
  --password "$ROOT_PASSWORD"

log_info "Uruchamianie kontenera ${CTID}..."
pct start "$CTID"

log_info "Oczekiwanie na inicjalizację systemu i połączenie sieciowe..."
sleep 6

# Czekamy na dostęp do sieci (maks. 30 sek)
for i in {1..15}; do
  if pct exec "$CTID" -- ping -c 1 -W 2 1.1.1.1 &>/dev/null; then
    break
  fi
  sleep 2
done

# Pobranie przydzielonego adresu IP
CONTAINER_IP="$(pct exec "$CTID" -- ip -4 addr show scope global 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -n1 || true)"
log_success "Kontener ${CTID} uruchomiony! IP: ${CONTAINER_IP:-DHCP}"

# Kopiowanie bieżącego projektu lub klonowanie
LOCAL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ -f "$LOCAL_DIR/artisan" ]]; then
  log_info "Kopiowanie plików projektu z węzła Proxmox do kontenera /var/www/agenthub..."
  pct exec "$CTID" -- mkdir -p /var/www/agenthub
  
  # Tworzenie tymczasowego archiwum i rozpakowanie wewnątrz LXC
  TAR_TMP="$(mktemp /tmp/emet-deploy-XXXXXX.tar.gz)"
  tar -czf "$TAR_TMP" -C "$LOCAL_DIR" \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='storage/logs/*' \
    .
  pct push "$CTID" "$TAR_TMP" /tmp/emet.tar.gz
  rm -f "$TAR_TMP"
  pct exec "$CTID" -- tar -xzf /tmp/emet.tar.gz -C /var/www/agenthub
  pct exec "$CTID" -- rm -f /tmp/emet.tar.gz
  pct exec "$CTID" -- chmod +x /var/www/agenthub/scripts/install-lxc.sh
  
  log_info "Uruchamianie instalatora w kontenerze..."
  pct exec "$CTID" -- /var/www/agenthub/scripts/install-lxc.sh --app-dir=/var/www/agenthub
else
  # Klonowanie z repozytorium
  [[ -n "$REPO_URL" ]] || die "Nie wykryto lokalnego kodu ani nie podano --repo=<git_url>."
  log_info "Pobieranie instalatora do kontenera i uruchamianie..."
  pct exec "$CTID" -- apt-get update -y
  pct exec "$CTID" -- apt-get install -y curl git ca-certificates
  pct exec "$CTID" -- git clone "$REPO_URL" /var/www/agenthub
  pct exec "$CTID" -- chmod +x /var/www/agenthub/scripts/install-lxc.sh
  pct exec "$CTID" -- /var/www/agenthub/scripts/install-lxc.sh --app-dir=/var/www/agenthub
fi

echo
log_success "================================================================="
log_success " KONTENER PROXMOX LXC ${CTID} ZOSTAŁ UTWORZONY I SKONFIGUROWANY! "
log_success "================================================================="
echo "  CTID:               ${CTID}"
echo "  Nazwa hosta:        ${HOSTNAME}"
echo "  Adres URL:          http://${CONTAINER_IP:-<IP_KONTENERA>}"
echo "  Hasło roota kont.:  ${ROOT_PASSWORD}"
echo "  Panel logowania:    admin@admin.lan / admin"
echo
echo "Aby wejść do powłoki kontenera:"
echo "  pct enter ${CTID}"
echo
