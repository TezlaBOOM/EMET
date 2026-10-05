#!/usr/bin/env bash
# =============================================================================
# AgentHub – awaryjne przywracanie wersji (rollback.sh)
# Przywraca konfigurację, bazę danych i pliki ze wskazanego lub ostatniego backupu.
#
# Użycie:   ./scripts/rollback.sh [timestamp_backupu]
# =============================================================================
set -Eeuo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="$REPO_ROOT/storage/backups"

log()   { printf "  \033[1;34m[Rollback]\033[0m %s\n" "$*"; }
warn()  { printf "  \033[1;33m[ UWAGA  ]\033[0m %s\n" "$*"; }
die()   { printf "  \033[1;31m[ BŁĄD   ]\033[0m %s\n" "$*" >&2; exit 1; }

TARGET_BACKUP="${1:-}"

if [[ -z "$TARGET_BACKUP" ]]; then
  if [[ -d "$BACKUP_DIR" ]]; then
    TARGET_BACKUP="$(find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d | sort -r | head -n1 || true)"
  fi
else
  if [[ ! -d "$TARGET_BACKUP" && -d "$BACKUP_DIR/$TARGET_BACKUP" ]]; then
    TARGET_BACKUP="$BACKUP_DIR/$TARGET_BACKUP"
  fi
fi

[[ -n "$TARGET_BACKUP" && -d "$TARGET_BACKUP" ]] || die "Nie znaleziono katalogu backupu do przywrócenia."

log "Przywracanie systemu z kopii: $TARGET_BACKUP"

# 1. Przywracanie pliku .env
if [[ -f "$TARGET_BACKUP/.env" ]]; then
  cp "$TARGET_BACKUP/.env" "$REPO_ROOT/.env"
  log "Przywrócono plik .env"
fi

# 2. Przywracanie bazy danych SQLite (jeśli istnieje)
if [[ -f "$TARGET_BACKUP/database.sqlite" ]]; then
  cp "$TARGET_BACKUP/database.sqlite" "$REPO_ROOT/database/database.sqlite"
  log "Przywrócono bazę SQLite"
fi

# 3. Przywracanie pliku VERSION
if [[ -f "$TARGET_BACKUP/VERSION" ]]; then
  cp "$TARGET_BACKUP/VERSION" "$REPO_ROOT/VERSION"
  log "Przywrócono wersję: $(cat "$REPO_ROOT/VERSION")"
fi

# 4. Czyszczenie cache i wyjście z trybu konserwacji
if [[ -f "$REPO_ROOT/artisan" ]]; then
  php "$REPO_ROOT/artisan" optimize:clear >/dev/null 2>&1 || true
  php "$REPO_ROOT/artisan" up >/dev/null 2>&1 || true
  log "Wyłączono tryb konserwacji (artisan up)"
fi

log "Procedura rollback zakończona sukcesem."
