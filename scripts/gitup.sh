#!/usr/bin/env bash
# =============================================================================
# Projekt-Emet (AgentHub) – Szybki pobieracz aktualizacji z GitHub (gitup.sh)
#
# Przeznaczenie:
#   Pobiera najnowsze zmiany z repozytorium GitHub (git pull) i czyści pamięć
#   podręczną aplikacji (cache, skompilowane widoki Blade, konfigurację).
#
# Użycie:
#   ./gitup.sh                  # pobiera zmiany z bieżącej gałęzi (np. main)
#   ./gitup.sh --branch=<nazwa> # pobiera z określonej gałęzi
#   ./gitup.sh --hard           # odrzuca lokalne modyfikacje (git reset --hard)
#   ./gitup.sh --migrate        # dodatkowo uruchamia migracje bazy danych
#   ./gitup.sh --help           # wyświetla pomoc
#
# One-liner w konsoli LXC / serwera:
#   curl -fsSL https://raw.githubusercontent.com/TezlaBOOM/EMET/main/gitup.sh | bash
# =============================================================================
set -Eeuo pipefail

# Kolory komunikatów
CLR_RESET="\033[0m"
CLR_INFO="\033[1;34m"
CLR_SUCCESS="\033[1;32m"
CLR_WARN="\033[1;33m"
CLR_FAIL="\033[1;31m"
CLR_TITLE="\033[1;36m"

log_info()    { printf "  ${CLR_INFO}[ INFO ]${CLR_RESET} %s\n" "$*"; }
log_success() { printf "  ${CLR_SUCCESS}[  OK  ]${CLR_RESET} %s\n" "$*"; }
log_warn()    { printf "  ${CLR_WARN}[ WARN ]${CLR_RESET} %s\n" "$*"; }
log_fail()    { printf "  ${CLR_FAIL}[ BŁĄD ]${CLR_RESET} %s\n" "$*"; }

TARGET_BRANCH=""
FORCE_HARD=0
RUN_MIGRATE=0

# Parsowanie parametrów
for arg in "$@"; do
    case "$arg" in
        --branch=*)
            TARGET_BRANCH="${arg#*=}"
            ;;
        --hard|-f)
            FORCE_HARD=1
            ;;
        --migrate|-m)
            RUN_MIGRATE=1
            ;;
        --help|-h)
            printf "${CLR_TITLE}Projekt-Emet – Narzędzie aktualizacji z GitHub (gitup.sh)${CLR_RESET}\n"
            printf "Użycie: ./gitup.sh [opcje]\n\n"
            printf "Dostępne opcje:\n"
            printf "  --branch=<nazwa>    Wybór gałęzi gita (domyślnie aktywna gałąź)\n"
            printf "  --hard              Wymuszenie nadpisania lokalnych zmian (git reset --hard)\n"
            printf "  --migrate           Uruchomienie migracji bazy danych po aktualizacji\n"
            printf "  --help, -h          Wyświetlenie tej pomocy\n"
            exit 0
            ;;
        *)
            log_warn "Nieznany parametr: $arg (zignorowano)"
            ;;
    esac
done

printf "\n${CLR_TITLE}======================================================${CLR_RESET}\n"
printf "${CLR_TITLE}   Projekt-Emet (AgentHub) – Aktualizacja z GitHub    ${CLR_RESET}\n"
printf "${CLR_TITLE}======================================================${CLR_RESET}\n\n"

# 1. Wykrywanie katalogu repozytorium
TARGET_DIR=""

if [[ -d ".git" ]]; then
    TARGET_DIR="$(pwd)"
elif [[ -n "${BASH_SOURCE[0]:-}" ]] && [[ -f "${BASH_SOURCE[0]}" ]]; then
    SOURCE="${BASH_SOURCE[0]}"
    while [ -h "$SOURCE" ]; do
        DIR="$(cd -P "$(dirname "$SOURCE")" && pwd)"
        SOURCE="$(readlink "$SOURCE")"
        [[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE"
    done
    DIR="$(cd -P "$(dirname "$SOURCE")" && pwd)"
    if [[ -d "$DIR/.git" ]]; then
        TARGET_DIR="$DIR"
    elif [[ -d "$DIR/../.git" ]]; then
        TARGET_DIR="$(cd "$DIR/.." && pwd)"
    fi
fi

if [[ -z "$TARGET_DIR" ]]; then
    if [[ -d "/var/www/agenthub/.git" ]]; then
        TARGET_DIR="/var/www/agenthub"
    elif [[ -d "/var/www/emet/.git" ]]; then
        TARGET_DIR="/var/www/emet"
    fi
fi

if [[ -z "$TARGET_DIR" || ! -d "$TARGET_DIR/.git" ]]; then
    log_fail "Nie odnaleziono katalogu repozytorium Git AgentHub!"
    exit 1
fi

cd "$TARGET_DIR"
log_info "Katalog projektu: $TARGET_DIR"

# 2. Bezpieczny katalog Git (omija 'dubious ownership' w LXC/Docker)
if command -v git >/dev/null 2>&1; then
    git config --global --add safe.directory "$TARGET_DIR" 2>/dev/null || true
else
    log_fail "Polecenie 'git' nie jest zainstalowane!"
    exit 1
fi

# 3. Sprawdzenie bieżącej gałęzi
CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "main")"
BRANCH="${TARGET_BRANCH:-$CURRENT_BRANCH}"
log_info "Pobieranie zmian z gałęzi: ${BRANCH}..."

# 4. Pobieranie danych z GitHub
git fetch origin "$BRANCH"

if [[ $FORCE_HARD -eq 1 ]]; then
    log_warn "Tryb --hard: Odrzucanie wszelkich lokalnych zmian..."
    git reset --hard "origin/$BRANCH"
else
    # Jeśli są lokalne zmiany w śledzonych plikach, zrób bezpieczny stash
    if ! git diff-index --quiet HEAD -- 2>/dev/null; then
        log_warn "Wykryto lokalne zmiany w plikach roboczych. Zabezpieczam je (git stash)..."
        git stash push -m "gitup-auto-stash-$(date +%s)" >/dev/null 2>&1 || true
    fi
    git pull origin "$BRANCH"
fi

COMMIT_HASH="$(git rev-parse --short HEAD)"
COMMIT_MSG="$(git log -1 --pretty=format:'%s (%an, %ar)')"
log_success "Zaktualizowano do commita: [${COMMIT_HASH}] ${COMMIT_MSG}"

# 5. Czyszczenie pamięci podręcznej Laravel
if [[ -f "artisan" ]] && command -v php >/dev/null 2>&1; then
    log_info "Przeładowanie pamięci podręcznej aplikacji..."
    php artisan optimize:clear >/dev/null 2>&1 || true
    php artisan view:clear >/dev/null 2>&1 || true
    log_success "Pamięć podręczna i skompilowane widoki Blade zostały wyczyszczone."

    if [[ $RUN_MIGRATE -eq 1 ]]; then
        log_info "Uruchamianie migracji bazy danych..."
        php artisan migrate --force || true
        log_success "Migracje bazy danych zakończone."
    fi
fi

# 6. Prawa dostępu do katalogów w środowiskach Linux
if [[ -d "storage" && "$(uname -s)" == "Linux" ]]; then
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
fi

printf "\n${CLR_SUCCESS}======================================================${CLR_RESET}\n"
printf "${CLR_SUCCESS}   Aktualizacja zakończona pomyślnie!                 ${CLR_RESET}\n"
printf "${CLR_SUCCESS}======================================================${CLR_RESET}\n\n"
