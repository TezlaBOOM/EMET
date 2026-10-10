# Aktualizacja Platformy AgentHub do wersji 1.5.0

Ten dokument opisuje procedurę aktualizacji środowiska produkcyjnego i deweloperskiego AgentHub do wersji 1.5.0.

---

## 1. Wymagania wstępne (Prerequisites)

- PHP >= 8.3 z rozszerzeniami: `bcmath`, `curl`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `intl`, `json`, `sodium`, `zip`.
- Node.js >= 18 oraz npm.
- MySQL >= 8.0 lub PostgreSQL >= 15 (lub SQLite w środowisku testowym).
- Dostęp do lokalnego demona Docker poprzez socket `unix:///var/run/docker.sock` (dla modułu adopcji kontenerów).

---

## 2. Automatyczna aktualizacja skryptem (`scripts/update.sh`)

Najprostszym i zalecanym sposobem aktualizacji jest uruchomienie skryptu:

```bash
./scripts/update.sh
```

Skrypt wykonuje sekwencję kroków:
1. `scripts/compat-check.sh` – weryfikacja zgodności środowiska i rozszerzeń.
2. `php artisan down` – włączenie trybu konserwacji.
3. Kopia zapasowa bazy danych i konfiguracji w `storage/backups/<timestamp>/`.
4. `composer install --no-dev --optimize-autoloader`.
5. `php artisan migrate --force` – migracje schematu bazy danych.
6. `php artisan db:seed --class=RolesAndPermissionsSeeder --force` – rejestracja nowych uprawnień v1.5.0.
7. `php artisan optimize:clear` oraz `php artisan docs:first-setup --build`.
8. `php artisan agenthub:selftest` – smoke-test podsystemów.
9. `php artisan up` – wyłączenie trybu konserwacji.

W przypadku niepowodzenia dowolnego kroku następuje automatyczne wycofanie zmian (`scripts/rollback.sh`).

---

## 3. Ręczna aktualizacja krok po kroku

Jeśli zarządzasz wdrożeniem manualnie:

```bash
# 1. Sprawdzenie kompatybilności
./scripts/compat-check.sh

# 2. Pobranie kodu i instalacja zależności
git pull origin main
composer install --no-dev --optimize-autoloader
npm install
npm run build

# 3. Migracje bazy i seedery
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force

# 4. Przebudowanie dokumentacji i pamięci podręcznej
php artisan optimize:clear
php artisan docs:first-setup --build

# 5. Weryfikacja spójności
php artisan agenthub:selftest
php artisan docs:check
```

---

## 4. Nowości i Zmiany w Wersji 1.5.0

- **Zdolności Agenta**: Wprowadzenie `internet_mode` (off/allowlist/open) i `context_mode` (stateful/stateless).
- **Magazyn Skilli**: Zakładka *Agenci AI → Skille*, wersjonowanie, import ZIP/Git i izolowany sandbox.
- **Czat Grupowy**: Orkiestracja konwersacji z wieloma agentami w 4 trybach (`mention`, `broadcast`, `round_robin`, `moderator`).
- **Scenariusze Blokowe**: Wizualny edytor schematów Drawflow na trasie `/scenarios/{id}/editor` z telemetrią na żywo i silnikiem Horizon.
- **Adopcja Kontenerów Docker**: Skanowanie, tryby adopcji (`observe`, `configure`, `managed`), auto-konfiguracja modeli z rollbackiem oraz egzekwowanie `execAllowlist()`.
- **Eksport i Import Konfiguracji**: Pełny transfer konfiguracji w paczkach ZIP z szyfrowaniem sekretów Argon2id + XChaCha20-Poly1305.
