# AgentHub Constitution

Dokument nadrzędny określający niezmienne zasady architektury, jakości kodu i procesu realizacji projektu **AgentHub**.

## Core Principles

### I. Etapowa realizacja i weryfikacja (Phase-Driven Delivery)
Prace nad projektem muszą być prowadzone ściśle według 10 etapów zdefiniowanych w specyfikacji projektu (sekcja 22).
- Każdy etap musi zakończyć się działającym, przetestowanym rezultatem.
- Po każdym etapie uruchamiane są testy automatyczne (Pest/PHPUnit) oraz statyczna analiza kodu.
- Przejście do kolejnego etapu wymaga spełnienia Definition of Done (DoD) dla danego etapu.

### II. Ścisłe kontrakty i brak zgadywania API (Contract-First & No Guessing)
Niedozwolone jest wymyślanie zewnętrznych API (Hermes Agent, OpenClaw, Qdrant itp.).
- Każda integracja musi być oparta o oficjalną dokumentację, a ustalenia spisane w `docs/integrations/<nazwa>.md`.
- Wszystkie adaptery runtime'ów muszą implementować formalny kontrakt interfejsu (`AgentRuntimeAdapter`).
- Do momentu pełnej weryfikacji i dostępności środowiska docelowego, adaptery muszą posiadać pełnowartościowy sterownik testowy typu mock (`MockRuntimeAdapter`).
- Polecenia uruchamiane przez `integration-manager` pochodzą wyłącznie z zamkniętej białej listy szablonów.

### III. Test-Driven & Quality Gates (NON-NEGOTIABLE)
Jakość kodu jest egzekwowana automatycznie i bezkompromisowo:
- **Język i framework**: PHP 8.3+, Laravel (aktualna stabilna wersja), Livewire 3 / Blade, TailwindCSS.
- **Styl kodu**: PSR-12, formatowanie przez Laravel Pint.
- **Analiza statyczna**: PHPStan na poziomie minimum 6 (docelowo level 8 dla krytycznych serwisów `llm-gateway` i `memory-service`).
- **Testy**: Pest / PHPUnit – pokrycie testami jednostkowymi modeli, serwisów, sterowników oraz testami integracyjnymi endpointów API i zadań asynchronicznych.
- **Dziennik zmian**: Każda zmiana funkcjonalna wymaga odnotowania w `CHANGELOG.md` oraz powiązanego testu automatycznego.

### IV. Bezpieczeństwo i poufność danych (Security by Design)
- **Szyfrowanie sekretów**: Wszystkie klucze API, tokeny dostępowe oraz poświadczenia kont są bezwzględnie szyfrowane w bazie danych za pomocą mechanizmu `encrypted` w Eloquent.
- **Zakaz wycieku danych w logach**: Maskowanie kluczy API, tokenów oraz opcjonalne wyłączanie logowania pełnych treści promptów/odpowiedzi.
- **Izolacja uprawnień**: `www-data` nie posiada bezpośredniego dostępu do gniazda Dockera; operacje zarządcze wykonuje demon `agenthub-runner` na prawach dedykowanego użytkownika.
- **Baza i usługi**: Domyślny nasłuch komponentów pomocniczych (Qdrant, Redis, bazy) wyłącznie na `127.0.0.1` z autoryzacją tokenem/hasłem.

### V. Modularność i rozszerzalność (Zero-Core Modification)
Architektura aplikacji musi być modularna:
- Nowe funkcjonalności są dodawane jako moduły w katalogu `modules/` z własnym manifestem `module.json`, trasami, migracjami i widokami.
- Rdzeń aplikacji (`app/`) dostarcza jedynie kontrakty, rejestr modułów, magistralę zdarzeń oraz układ 7 bloków interfejsu użytkownika.
- Menu 1 i Menu 2 są deklaratywnie wstrzykiwane przez moduły, bez modyfikacji głównego layoutu.

### VI. Pełna lokalizacja i ergonomia interfejsu (I18n & UX Standards)
- Wszystkie teksty w interfejsie użytkownika muszą pochodzić z plików tłumaczeń (`lang/pl`, `lang/en`).
- Całkowity zakaz umieszczania zahardkodowanych napisów w szablonach Blade i komponentach Livewire.
- Interfejs w układzie 7 bloków zgodny z WCAG AA, z obsługą motywu jasnego/ciemnego i pełną responsywnością (desktop/mobile).

## Zarządzanie konfiguracją i środowiskiem

1. **Środowiska uruchomieniowe**:
   - Konteneryzacja: `docker-compose.yml` (wariant podstawowy dla deweloperów).
   - Instalacja natywna: dedykowany, idempotentny skrypt `scripts/install-debian13.sh` dla Debian 13 (Apache + PHP-FPM + MariaDB/PostgreSQL + Qdrant/pgvector).
2. **Konto początkowe**:
   - `AdminUserSeeder`: tworzy administratora `admin@admin.lan` z hasłem `admin` i rolą `admin`.
   - Seeder jest w pełni idempotentny (`updateOrCreate`).
   - Aplikacja nie blokuje działania przy haśle domyślnym, udostępniając zmianę w profilu użytkownika.
3. **Zarządzanie wieloma kontami LLM (Multi-Account Pools)**:
   - Dostęp do modeli oparty o pule kont z obsługą strategii (Round-robin, Weighted, Least-used), limitów TPM/RPM, dynamicznego cooldownu i automatycznego przełączania awaryjnego (failover).

## Governance

- Konstytucja jest nadrzędna wobec wszelkich doraźnych decyzji implementacyjnych.
- Odstępstwa od zasad konstytucji wymagają udokumentowania w sekcji `Complexity Tracking` planu implementacji.
- Każde zadanie implementowane przez asystenta AI (`/speckit-implement`) musi weryfikować zgodność z niniejszą konstytucją.

**Version**: 1.0.0 | **Ratified**: 2026-10-04 | **Status**: Active
