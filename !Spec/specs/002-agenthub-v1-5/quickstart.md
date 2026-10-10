# Quickstart & Verification Guide: AgentHub v1.5.0

**Projekt**: AgentHub 1.5.0  
**Wymagania minimalne**: PHP 8.3+, Composer 2, Node.js 20+, Docker (lokalny socket `unix:///var/run/docker.sock`), Redis 7+, PostgreSQL 16 / MariaDB 10.11+  

---

## 1. Aktualizacja istniejącej instancji (1.0.x → 1.5.0)

Aktualizacja przeprowadzana jest automatycznie za pomocą skryptu aktualizacyjnego z wbudowanym backupem i testem zgodności:

```bash
# 1. Sprawdzenie kompatybilności środowiska
./scripts/compat-check.sh

# 2. Uruchomienie procedury aktualizacji (wykonuje backup, migracje i build)
./scripts/update.sh

# 3. Uruchomienie rozszerzonego testu diagnostycznego 1.5.0
php artisan agenthub:selftest
```

Weryfikator `agenthub:selftest` sprawdza:
- Poprawność migracji tabel 1.5.0 (`skills`, `conversations`, `scenarios`, `integration_containers`, `config_transfers`),
- Dostępność gniazda Dockera dla demona `integration-manager`,
- Zdolność do uruchomienia izolowanego sandboxa dla skilli,
- Pomyślny testowy eksport i import `--dry-run` konfiguracji.

---

## 2. Weryfikacja kluczowych funkcjonalności (Scenariusze testowe)

### Scenariusz A: Magazyn i sandbox skilli
1. Przejdź do **Agenci AI → Skille**.
2. Kliknij **Dodaj skill** i wybierz import przykładowej paczki testowej lub utwórz skill typu `tool`.
3. Kliknij przycisk **Testuj**:
   - System uruchamia wykonanie w izolowanym sandboxie.
   - Weryfikacja: Wynik zwraca status `success`, czas wykonania < 500 ms, brak dostępu do zmiennych środowiskowych `.env` hosta.
4. Przypisz skill do agenta i zweryfikuj czy wersja jest poprawnie śledzona w profilu agenta.

### Scenariusz B: Wymuszenie trybu bezstanowego (`stateless`)
1. W edycji agenta w sekcji **Zdolności i pamięć** ustaw:
   - `Pamięć wywołań`: **Bezstanowa (stateless)**.
   - `Internet`: **Wyłączony (off)**.
2. Rozpocznij nową konwersację na czacie:
   - Wyślij: *"Nazywam się Jan i mój ulubiony kolor to niebieski."*
   - Wyślij w kolejnej turze: *"Jaki jest mój ulubiony kolor?"*
3. **Oczekiwany rezultat**: Agent informuje, że nie pamięta wcześniejszej wypowiedzi (potwierdzenie braku historii w promptzie).

### Scenariusz C: Czat grupowy z orkiestracją
1. W module **Czat** utwórz nową rozmowę i dodaj 3 agentów (np. `Researcher`, `Analyst`, `Reviewer`).
2. Wybierz tryb orkiestracji: **Round-robin** z limitem `max_turns=6`.
3. Wyślij zapytanie problemowe do grupy.
4. **Oczekiwany rezultat**:
   - Odpowiedzi agentów pojawiają się kolejno, każdy agent widzi wypowiedź poprzednika.
   - Po 6 turach dialog zatrzymuje się automatycznie bez nieskończonych pętli.

### Scenariusz D: Edytor blokowy scenariuszy z telemetrią na żywo
1. W Menu 1 przejdź do **Scenariusze → Utwórz scenariusz**.
2. Kliknij **Otwórz edytor w osobnym oknie** (adres `/scenarios/{id}/editor`).
3. Zbuduj graf:
   - Węzeł `start` (trigger ręczny),
   - Węzeł `agent` (wybór agenta testowego),
   - Węzeł `condition` (warunek logiczny wyjścia),
   - Węzeł `end`.
4. Kliknij **Zapisz i opublikuj wersję**, a następnie **Uruchom testowo**:
   - W kanwie edytora obserwuj animację przepływu danych.
   - Bloczki węzłów zmieniają kolor na zielony (`success`), prezentując liczbę tokenów i czas wykonania.

### Scenariusz E: Wykrywanie i podłączenie kontenera Docker
1. Upewnij się, że lokalnie działa kontener (np. Ollama lub Hermes Agent).
2. Otwórz **Integracje → Kontenery** i kliknij **Wykryj kontenery**.
3. Kliknij **Podłącz** przy wykrytym kontenerze i wybierz tryb **Managed**.
4. Wybierz profil auto-konfiguracji i zatwierdź:
   - Sprawdź logi zadania provisioningu.
   - Kontener uzyskuje status `healthy` i pojawia się w rejestrze usług.

### Scenariusz F: Round-trip eksportu i importu konfiguracji
1. Zaloguj się jako administrator i przejdź do **System → Eksport/Import**.
2. Kliknij **Eksportuj konfigurację**:
   - Pobierz wygenerowane archiwum ZIP `agenthub-config-1.5.0-*.zip`.
3. Przetestuj import w trybie dry-run:
   ```bash
   php artisan config:import storage/app/backups/latest-config.zip --dry-run
   ```
4. Raport powinien wykazać brak konfliktów (lub 100% dopasowanie stanu).

---

## 3. Weryfikacja reguły dokumentacyjnej i CI

Sprawdź poprawność działania strażnika dokumentacji w terminalu:

```bash
# 1. Zbudowanie pełnej instrukcji konfiguracji z fragmentów modułów
php artisan docs:first-setup --build

# 2. Uruchomienie audytu zgodności dokumentacji (odpowiednik testu w CI)
php artisan docs:check
```

Polecenie `docs:check` musi zwrócić kod `0` przy zsynchronizowanych plikach `docs/`, `CHANGELOG.md` i manifestach `module.json`.
