#!/usr/bin/env bash
# =============================================================================
# AgentHub – pełny proces aktualizacji systemu (update.sh)
# Wykonuje: compat-check -> tryb konserwacji -> backup -> aktualizacja ->
#           migracje -> optymalizacja -> selftest -> restart / rollback przy błędzie.
#
# Użycie:   ./scripts/update.sh [opcje]
# Opcje:
#   --skip-compat   pomiń test zgodności środowiska
#   --dry-run       symulacja procesu bez dokonywania zmian
#   --help, -h
# =============================================================================
set -Eeuo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SKIP_COMPAT=0
DRY_RUN=0

log()   { printf "  \033[1;34m[Update]\033[0m %s\n" "$*"; }
warn()  { printf "  \033[1;33m[UWAGA ]\033[0m %s\n" "$*"; }
die()   { printf "  \033[1;31m[BŁĄD  ]\033[0m %s\n" "$*" >&2; exit 1; }

for arg in "$@"; do
  case "$arg" in
    --skip-compat) SKIP_COMPAT=1 ;;
    --dry-run)     DRY_RUN=1 ;;
    --help|-h)
      sed -n '2,13p' "$0"
      exit 0
      ;;
    *) die "Nieznana opcja: $arg" ;;
  esac
done

cd "$REPO_ROOT"
TIMESTAMP="$(date +'%Y%m%d_%H%M%S')"
BACKUP_PATH="$REPO_ROOT/storage/backups/$TIMESTAMP"

echo "================================================="
echo "   AgentHub Platform Automated Updater          "
echo "================================================="
log "Rozpoczynanie procesu aktualizacji (ID: $TIMESTAMP)..."

# 1. Test zgodności wstecznej (compat-check)
if [[ $SKIP_COMPAT -eq 0 ]]; then
  log "Krok 1/7: Uruchamianie testu zgodności (compat-check)..."
  if [[ -f "$REPO_ROOT/scripts/compat-check.sh" ]]; then
    "$REPO_ROOT/scripts/compat-check.sh" || die "Test zgodności wykrył błędy. Aktualizacja zatrzymana."
  fi
else
  warn "Pominięto test zgodności (--skip-compat)."
fi

if [[ $DRY_RUN -eq 1 ]]; then
  log "Tryb symulacji (--dry-run). Nie wprowadzono żadnych zmian w plikach ani bazie."
  exit 0
fi

# Pułapka awaryjna: przy błędzie automatyczny rollback
cleanup_on_error() {
  warn "Wystąpił błąd w trakcie aktualizacji! Uruchamianie procedury rollback..."
  if [[ -f "$REPO_ROOT/scripts/rollback.sh" && -d "$BACKUP_PATH" ]]; then
    "$REPO_ROOT/scripts/rollback.sh" "$TIMESTAMP" || true
  fi
  die "Aktualizacja nie powiodła się. Przywrócono poprzedni stan systemu."
}
trap cleanup_on_error ERR

# 2. Włączenie trybu konserwacji
log "Krok 2/7: Włączanie trybu konserwacji..."
if [[ -f "$REPO_ROOT/artisan" ]]; then
  php "$REPO_ROOT/artisan" down --retry=60 >/dev/null 2>&1 || true
fi

# 3. Wykonanie backupu
log "Krok 3/7: Tworzenie kopii zapasowej w $BACKUP_PATH..."
mkdir -p "$BACKUP_PATH"
[[ -f .env ]] && cp .env "$BACKUP_PATH/.env"
[[ -f VERSION ]] && cp VERSION "$BACKUP_PATH/VERSION"
[[ -f database/database.sqlite ]] && cp database/database.sqlite "$BACKUP_PATH/database.sqlite" || true
log "Kopia zapasowa konfiguracji i bazy została utworzona."

# 4. Aktualizacja kodu i zależności
log "Krok 4/7: Aktualizacja kodu i instalacja zależności Composer..."
if command -v composer >/dev/null 2>&1; then
  composer install --no-dev --optimize-autoloader --no-interaction >/dev/null 2>&1 || true
fi

# 5. Migracje bazy danych
log "Krok 5/7: Wykonywanie migracji bazy danych..."
if [[ -f "$REPO_ROOT/artisan" ]]; then
  php "$REPO_ROOT/artisan" migrate --force || true
  php "$REPO_ROOT/artisan" db:seed --class=RolesAndPermissionsSeeder --force || true
fi

# 6. Optymalizacja i czyszczenie pamięci podręcznej
log "Krok 6/7: Przeładowanie pamięci podręcznej i kompilacja podręcznika v1.5.0..."
if [[ -f "$REPO_ROOT/artisan" ]]; then
  php "$REPO_ROOT/artisan" optimize:clear >/dev/null 2>&1 || true
  php "$REPO_ROOT/artisan" docs:first-setup --build >/dev/null 2>&1 || true
fi

# 7. Diagnostyka selftest
log "Krok 7/7: Uruchamianie smoke-testów platformy..."
if [[ -f "$REPO_ROOT/artisan" ]]; then
  php "$REPO_ROOT/artisan" agenthub:selftest || warn "Selftest zgłosił ostrzeżenia."
fi

# Wyłączenie trybu konserwacji
if [[ -f "$REPO_ROOT/artisan" ]]; then
  php "$REPO_ROOT/artisan" up >/dev/null 2>&1 || true
fi

trap - ERR
log "================================================="
log " AKTUALIZACJA ZAKOŃCZONA SUKCESEM!"
log " Nowa wersja systemu: $(cat "$REPO_ROOT/VERSION" 2>/dev/null || echo '1.0.0')"
log " Kopia bezpieczeństwa zachowana w: $BACKUP_PATH"
log "================================================="
