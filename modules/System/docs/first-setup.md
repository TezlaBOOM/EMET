# Pierwsza Konfiguracja: Moduł System (v1.5.0)

## Krok: Zarządzanie konfiguracją systemu i transfery

1. **Wymagane uprawnienia**:
   - `system.manage` – dostęp do panelu transferów konfiguracji, generatora kopii zapasowych oraz instrukcji pierwszej konfiguracji.

2. **Eksport i Import Konfiguracji**:
   - Eksport do archiwum ZIP (`config-<timestamp>.zip`) obejmuje wszystkie zarejestrowane sekcje `ConfigSectionInterface`.
   - Opcjonalne szyfrowanie sekretów (`secrets.enc`) hasłem administratora przy użyciu algorytmów Argon2id + XChaCha20-Poly1305.
   - Przed każdym importem system tworzy automatyczną kopię bezpieczeństwa w `storage/backups/config-<timestamp>.zip`.
   - Podgląd `dry-run` generuje pełny raport różnic bez wprowadzania zmian w bazie danych.

3. **Komendy CLI**:
   - `php artisan config:export` – eksport konfiguracji do pliku.
   - `php artisan config:import` – import konfiguracji z pliku z opcją `--dry-run`.
   - `php artisan docs:first-setup --build` – generowanie dokumentu `docs/FIRST_SETUP.md`.
