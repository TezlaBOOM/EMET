# Podręcznik Pierwszej Konfiguracji AgentHub (Wydanie 1.5.0)

> Niniejszy dokument jest generowany automatycznie poleceniem `php artisan docs:first-setup --build`.
> Ostatnia aktualizacja: 2026-10-10 09:52:53

## Spis treści

- [Moduł Agenci AI](#moduł-agents)
- [Moduł Czat AI](#moduł-chat)
- [Moduł Scenariusze](#moduł-scenarios)
- [Moduł Integracje](#moduł-integrations)
- [Moduł System](#moduł-system)

---

<a name="moduł-agents"></a>

## Moduł: Agenci AI

# Pierwsza Konfiguracja: Agenci AI & Skille (v1.5.0)

## Krok: Konfiguracja magazynu skilli i zdolności agentów

1. **Weryfikacja uprawnień**:
   Upewnij się, że rola użytkownika posiada uprawnienia `skills.view` oraz `skills.manage`.

2. **Dysk dyskowy skilli**:
   Upewnij się, że katalog `storage/app/skills` posiada prawa zapisu dla procesu aplikacji (`chmod 775 storage/app/skills`).

3. **Dodawanie pierwszego skilla**:
   Przejdź do zakładki **Agenci AI → Skille** i kliknij **Dodaj skill**.
   - Możesz przesłać paczkę archiwum `.zip` zawierającą manifest `skill.json`.
   - Każda paczka jest automatycznie weryfikowana pod kątem sumy kontrolnej SHA-256 oraz bezpieczeństwa ścieżek (Zip-Slip).

4. **Weryfikacja kompatybilności zdolności**:
   Przed przypisaniem skilla do agenta system sprawdza zgodność wymagań (`requires`):
   - Jeśli skill wymaga dostępu do sieci (`internet`), agent musi posiadać `internet_mode` ustawiony na `allowlist` lub `open`.
   - Jeśli skill wymaga pamięci wektorowej, agent musi mieć przypiętą kolekcję wektorową.

---

<a name="moduł-chat"></a>

## Moduł: Czat AI

# Pierwsza Konfiguracja: Czat z Wieloma Agentami (v1.5.0)

## Krok: Konfiguracja czatu grupowego i trybów orkiestracji

1. **Wymagane uprawnienia**:
   - `chat.group.create` – tworzenie konwersacji z udziałem wielu agentów.
   - `chat.group.manage` – dodawanie/usuwanie uczestników oraz zatrzymywanie dialogu.

2. **Dostępne tryby orkiestracji**:
   - **Mention (`mention`)**: Odpowiada tylko agent oznaczony za pomocą `@nazwa_agenta` lub domyślny agent prowadzący.
   - **Broadcast (`broadcast`)**: Każdy aktywny agent w grupie otrzymuje wiadomość użytkownika i generuje odpowiedź.
   - **Round-robin (`round_robin`)**: Agenci odpowiadają cyklicznie jeden po drugim.
   - **Moderator (`moderator`)**: Dedykowany agent-moderator analizuje kontekst i wskazuje kolejnego mówcę.

3. **Limity bezpieczeństwa**:
   - Zabezpieczenie `max_turns` ogranicza liczbę wymian zdań w rundzie (domyślnie 20).
   - Wbudowany detektor pętli przerywa generowanie, gdy agenci zaczną powtarzać identyczne wypowiedzi.

---

<a name="moduł-scenarios"></a>

## Moduł: Scenariusze

# Pierwsza Konfiguracja: Scenariusze Wizualne (v1.5.0)

## Krok: Konfiguracja edytora blokowego i silnika wykonawczego

1. **Wymagane uprawnienia**:
   - `scenarios.view` – przeglądanie listy scenariuszy i historii runów.
   - `scenarios.manage` – tworzenie, edycja i publikowanie grafów w edytorze Drawflow.
   - `scenarios.run` – ręczne wyzwalanie uruchomień testowych scenariuszy.

2. **Edytor w osobnym oknie**:
   - Bezpośredni URL do edytora: `/scenarios/{id}/editor`.
   - Edytor obsługuje 12 typów węzłów: `start`, `agent`, `skill`, `memory`, `condition`, `parallel`, `join`, `loop`, `human`, `transform`, `delay`, `end`.
   - Zabezpieczenie `SCENARIO_MAX_NODES=200` chroni przed przeciążeniem przeglądarki i serwera.

3. **Wyzwalacze i webhooki**:
   - Scenariusze mogą być uruchamiane ręcznie, harmonogramem cron oraz webhookami HTTPS:
     `POST /api/v1/hooks/scenarios/{token}`.
   - Token webhooka jest haszowany funkcją SHA-256 w bazie danych.

---

<a name="moduł-integrations"></a>

## Moduł: Integracje

# Pierwsza Konfiguracja: Integracje i Kontenery Docker (v1.5.0)

## Krok: Konfiguracja adapterów integracji i kontenerów Docker

1. **Wymagane uprawnienia**:
   - `integrations.manage` – wykrywanie, adopcja i konfiguracja kontenerów Docker oraz instancji zewnętrznych.

2. **Dostęp do Docker Socket**:
   - AgentHub komunikuje się z demonem Docker przez dedykowany socket: `unix:///var/run/docker.sock` (konfiguracja: `DOCKER_SOCKET_PATH`).
   - Brak terminala interaktywnego – wszelkie polecenia wewnątrz kontenerów są ograniczone przez ścisłą białą listę `execAllowlist()`.
   - Ścieżki zapisu konfiguracji są ograniczone przez `configWritablePaths()`.

3. **Tryby adopcji kontenerów**:
   - `observe` – monitorowanie stanu i logów bez możliwości modyfikacji plików.
   - `configure` – możliwość ręcznej konfiguracji z podglądem diffa.
   - `managed` – pełne zarządzanie z automatycznym podłączeniem do sieci `agenthub-net` oraz obsługą profili auto-konfiguracji.
   - Kontenery o statusie `none` (nieadoptowane) są traktowane jako nietykalne i nie podlegają żadnym modyfikacjom.

4. **Auto-konfiguracja i rollback**:
   - Zastosowanie profilu auto-konfiguracji (`AutoConfigProfile`) tworzy punkt przywracania (snapshot).
   - W przypadku niepowodzenia health-checka, system natychmiast wycofuje zmiany (rollback) i raportuje błąd w audycie.

---

<a name="moduł-system"></a>

## Moduł: System

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

---

