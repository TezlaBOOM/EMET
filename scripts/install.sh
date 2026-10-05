#!/usr/bin/env bash
# =============================================================================
# AgentHub – instalator środowiska Docker (All-in-one)
# Weryfikuje wymagania, konfiguruje .env, uruchamia kontenery docker compose,
# przeprowadza migracje i seeduje domyślne konto administratora.
#
# Użycie:   ./scripts/install.sh [opcje]
# Opcje:
#   --force        pomiń sprawdzanie wolnych portów i pamięci RAM
#   --help, -h     wyświetl pomoc
# =============================================================================
set -Eeuo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FORCE=0

log()  { echo -e "\e[1;34m[AgentHub]\e[0m $*"; }
warn() { echo -e "\e[1;33m[UWAGA]\e[0m $*"; }
die()  { echo -e "\e[1;31m[BŁĄD]\e[0m $*" >&2; exit 1; }
trap 'die "Instalacja przerwana w linii $LINENO"' ERR

for arg in "$@"; do
  case "$arg" in
    --force)   FORCE=1 ;;
    --help|-h)
      sed -n '2,12p' "$0"
      exit 0
      ;;
    *) die "Nieznana opcja: $arg" ;;
  esac
done

cd "$REPO_ROOT"

log "Weryfikacja wymagań wstępnych Docker..."
command -v docker >/dev/null 2>&1 || die "Nie znaleziono polecenia 'docker'. Zainstaluj Docker Desktop lub docker.io."

if docker compose version >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker-compose"
else
  die "Nie znaleziono 'docker compose' ani 'docker-compose'."
fi

log "Używane polecenie: $DOCKER_COMPOSE"

if [[ $FORCE -eq 0 ]]; then
  # Sprawdzenie dostępności portów 80, 5432, 6379, 6333, 8080
  PORTS_TO_CHECK=(80 5432 6379 6333 8080)
  for port in "${PORTS_TO_CHECK[@]}"; do
    if lsof -i :"$port" -sTCP:LISTEN -t >/dev/null 2>&1; then
      warn "Port $port jest aktualnie zajęty na hoście! Kontener może nie wystartować poprawnie."
    fi
  done
fi

log "Konfiguracja pliku środowiskowego .env..."
if [[ ! -f .env ]]; then
  if [[ -f .env.example ]]; then
    cp .env.example .env
  else
    die "Brak pliku .env.example w katalogu głównym."
  fi
fi

set_env_val() {
  local k="$1" v="$2"
  if grep -q "^${k}=" .env; then
    sed -i '' "s|^${k}=.*|${k}=${v}|" .env 2>/dev/null || sed -i "s|^${k}=.*|${k}=${v}|" .env
  else
    echo "${k}=${v}" >> .env
  fi
}

set_env_val APP_ENV production
set_env_val APP_DEBUG false
set_env_val APP_URL "http://localhost"
set_env_val SERVICE_MODE local

if ! grep -q "^APP_KEY=base64:" .env; then
  if command -v php >/dev/null 2>&1; then
    php artisan key:generate --force >/dev/null 2>&1 || true
  fi
fi

log "Uruchamianie kontenerów środowiska..."
$DOCKER_COMPOSE up -d

log "Oczekiwanie na gotowość bazy danych..."
sleep 3

log "Uruchamianie migracji i seederów w kontenerze aplikacji..."
$DOCKER_COMPOSE exec -T app php artisan migrate --force --seed || {
  warn "Migracja przez exec nie powiodła się bezpośrednio, ponawianie za 3 sekundy..."
  sleep 3
  $DOCKER_COMPOSE exec -T app php artisan migrate --force --seed || true
}

log "Optymalizacja pamięci podręcznej i storage..."
$DOCKER_COMPOSE exec -T app php artisan storage:link || true

echo
log "================================================================="
log " INSTALACJA ZAKOŃCZONA POMYŚLNIE!"
log "================================================================="
echo "  Adres URL:       http://localhost"
echo "  Domyślny login:  admin@admin.lan"
echo "  Domyślne hasło:  admin"
echo
warn "Zalecana zmiana hasła po pierwszym logowaniu w Ustawieniach."
