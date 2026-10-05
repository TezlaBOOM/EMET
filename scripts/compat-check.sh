#!/usr/bin/env bash
# =============================================================================
# AgentHub – test zgodności środowiska i kompatybilności wstecznej (compat-check)
# Uruchamiany automatycznie przed aktualizacją (update.sh) oraz na żądanie z UI.
#
# Kod wyjścia:
#   0 - Wszystkie testy zaliczone (OK)
#   1 - Wykryto błędy krytyczne (FAIL) - aktualizacja zablokowana
# =============================================================================
set -Eeuo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FAIL_COUNT=0
WARN_COUNT=0

log_ok()   { printf "  \033[1;32m[  OK  ]\033[0m %s\n" "$*"; }
log_warn() { printf "  \033[1;33m[ WARN ]\033[0m %s\n" "$*"; WARN_COUNT=$((WARN_COUNT + 1)); }
log_fail() { printf "  \033[1;31m[ FAIL ]\033[0m %s\n" "$*"; FAIL_COUNT=$((FAIL_COUNT + 1)); }

echo "================================================="
echo "   AgentHub Pre-Upgrade Compatibility Check      "
echo "================================================="

# 1. Weryfikacja wersji PHP
if command -v php >/dev/null 2>&1; then
  PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
  PHP_MAJOR="$(php -r 'echo PHP_MAJOR_VERSION;')"
  PHP_MINOR="$(php -r 'echo PHP_MINOR_VERSION;')"

  if [[ "$PHP_MAJOR" -gt 8 || ("$PHP_MAJOR" -eq 8 && "$PHP_MINOR" -ge 3) ]]; then
    log_ok "Wersja PHP: $PHP_VERSION (wymagane >= 8.3)"
  else
    log_fail "Wersja PHP: $PHP_VERSION jest za niska (wymagane >= 8.3)"
  fi
else
  log_fail "Brak zainstalowanego interpretera PHP na hoście."
fi

# 2. Weryfikacja wymaganych rozszerzeń PHP
REQUIRED_EXTS=(bcmath curl mbstring openssl pdo tokenizer xml intl json)
for ext in "${REQUIRED_EXTS[@]}"; do
  if php -r "exit(extension_loaded('$ext') ? 0 : 1);" 2>/dev/null; then
    log_ok "Rozszerzenie PHP: $ext"
  else
    log_fail "Brakujące rozszerzenie PHP: $ext"
  fi
done

# 3. Weryfikacja miejsca na dysku (minimum 500 MB)
FREE_DISK_MB="$(df -m "$REPO_ROOT" | awk 'NR==2 {print $4}')"
if [[ -n "$FREE_DISK_MB" && "$FREE_DISK_MB" -ge 500 ]]; then
  log_ok "Dostępne miejsce na dysku: ${FREE_DISK_MB} MB (wymagane >= 500 MB)"
else
  log_fail "Niewystarczająca ilość miejsca na dysku: ${FREE_DISK_MB:-0} MB"
fi

# 4. Uprawnienia zapisu w storage i bootstrap/cache
WRITABLE_PATHS=("$REPO_ROOT/storage" "$REPO_ROOT/bootstrap/cache")
for path in "${WRITABLE_PATHS[@]}"; do
  if [[ -d "$path" && -w "$path" ]]; then
    log_ok "Prawa do zapisu: $(basename "$path")"
  else
    log_warn "Katalog $path nie istnieje lub brak praw do zapisu"
  fi
done

# 5. Sprawdzenie modułów
MODULES_DIR="$REPO_ROOT/modules"
if [[ -d "$MODULES_DIR" ]]; then
  MODULE_COUNT="$(find "$MODULES_DIR" -mindepth 1 -maxdepth 1 -type d | wc -l | tr -d ' ')"
  log_ok "Wykryto $MODULE_COUNT zarejestrowanych modułów w $MODULES_DIR"
fi

# 6. Test symulacji migracji bazodanowych
if [[ -f "$REPO_ROOT/artisan" ]]; then
  if php "$REPO_ROOT/artisan" migrate --pretend >/dev/null 2>&1; then
    log_ok "Symulacja migracji bazy danych (artisan migrate --pretend) powiodła się"
  else
    log_warn "Nie udało się przeprowadzić symulacji migracji (baza offline lub brak konfiguracji)"
  fi
fi

echo "-------------------------------------------------"
if [[ $FAIL_COUNT -gt 0 ]]; then
  printf "\033[1;31mTest zgodności zakończony niepowodzeniem: %d błędów krytycznych, %d ostrzeżeń.\033[0m\n" "$FAIL_COUNT" "$WARN_COUNT"
  echo "Aktualizacja została zablokowana ze względów bezpieczeństwa."
  exit 1
else
  printf "\033[1;32mTest zgodności zakończony sukcesem (%d ostrzeżeń). Środowisko gotowe do aktualizacji.\033[0m\n" "$WARN_COUNT"
  exit 0
fi
