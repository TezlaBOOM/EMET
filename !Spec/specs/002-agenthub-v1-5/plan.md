# Implementation Plan: AgentHub Platform v1.5 (Aktualizacja 1.5.0)

**Branch**: `002-agenthub-v1-5` | **Date**: 2026-10-10 | **Spec**: [`spec.md`](./spec.md)  
**Input**: Specyfikacja aktualizacji [`Agenthub_v1.5.md`](../../Agenthub_v1.5.md) rozwijająca [`AGENT_HUB_SPEC_1.md`](../../AGENT_HUB_SPEC_1.md)  

---

## 1. Summary

Celem wdrożenia jest implementacja kompleksowej aktualizacji **1.5.0** platformy **AgentHub**. Aktualizacja rozwija system o:
1. Pełny magazyn i zakładkę skilli z wersjonowaniem i izolowanym sandboxem,
2. Czat grupowy z wieloma agentami i 4 trybami orkiestracji (`mention`, `broadcast`, `round_robin`, `moderator`),
3. Nowy moduł wizualnych scenariuszy blokowych (`modules/Scenarios`) z edytorem Drawflow w osobnym oknie i telemetrią na żywo,
4. Trójwymiarową kontrolę zdolności agenta (internet z egress proxy, wiedza wektorowa, bezstanowość wywołań `stateless`),
5. Wykrywanie, podłączanie i auto-konfigurację kontenerów Docker w *Integracjach* bez dowolnego `exec`,
6. Nowy moduł *System* z rozszerzalnym eksportem/importem całej konfiguracji (`ConfigSectionInterface`),
7. Instrukcję pierwszej konfiguracji w UI (`FIRST_SETUP.md`) oraz automatycznego strażnika aktualności dokumentacji w CI (`php artisan docs:check`).

Prace realizowane są w 7 logicznych etapach (Etapy 11–17) kontynuujących wdrożenie 1.0.0, zgodnie z [Konstytucją Projektu](../../.specify/memory/constitution.md) oraz regułami dokumentacyjnymi v1.5 §0.

---

## 2. Technical Context

- **Język i środowisko wykonawcze**: PHP 8.3 / PHP 8.4
- **Framework backendowy**: Laravel 11.x / 12.x / 13.x
- **Warstwa frontendowa**: Laravel Livewire 3 + Alpine.js + Tailwind CSS
- **Edytor schematów blokowych**: Drawflow (lekka biblioteka JS osadzona w komponencie Alpine.js bez narzutu SPA)
- **Komunikacja na żywo (WebSockets)**: Laravel Reverb (prywatne kanały `conversations.{id}` oraz `scenarios.{run_id}`)
- **Baza danych**: PostgreSQL 16 (domyślna) / MariaDB 10.11+
- **Pamięć podręczna i kolejki asynchroniczne**: Redis 7+ / Laravel Horizon
- **Magazyn wektorowy (Vector Store)**: Qdrant (lokalny daemon/kontener) / pgvector
- **Zarządzanie kontenerami i izolacja procesów**:
  - Dostęp do lokalnego gniazda Dockera (`unix:///var/run/docker.sock`) wyłącznie dla demona `integration-manager` (`agenthub-runner`)
  - Zamknięta biała lista szablonów poleceń (`execAllowlist()`) – całkowity zakaz interaktywnego terminala i dowolnego `exec`
- **Kryptografia i bezpieczeństwo sekretów**:
  - Eloquent `encrypted` casts dla poświadczeń w bazie danych
  - Szyfrowanie eksportu sekretów: `libsodium` (Argon2id KDF + XChaCha20-Poly1305 AEAD)
- **Zapewnienie jakości i CI**:
  - Testy: Pest / PHPUnit
  - Styl kodu: PSR-12, Laravel Pint
  - Analiza statyczna: PHPStan na poziomie minimum 6
  - Weryfikator dokumentacji: `php artisan docs:check` wbudowany w CI i `compat-check.sh`

---

## 3. Constitution Check

*GATE: Weryfikacja zgodności z Konstytucją Projektu oraz regułami uzupełniającymi 1.5.0.*

| Reguła konstytucyjna | Status | Mechanizm wdrożenia w planie 1.5.0 |
|---|:---:|---|
| **I. Praca etapowa (Etapy 11–17)** | PASS | Plan podzielony na 7 niezależnych, testowalnych etapów z mierzalnymi kryteriami DoD. |
| **II. Kontrakty & zakaz zgadywania API** | PASS | Nowe kontrakty: `ConfigSectionInterface`, `ContainerAdapterInterface`, `GroupChatOrchestratorInterface`, `ScenarioEngineInterface`. Sterowniki mock dla adapterów. |
| **III. Testy & jakość kodu** | PASS | Obowiązkowe testy jednostkowe i integracyjne (Pest) dla każdego etapu. PHPStan L6+, Pint. |
| **IV. Bezpieczeństwo i poufność** | PASS | Zero sekretów w logach/etykietach. Szyfrowany eksport `secrets.enc` (Argon2id+libsodium). Brak praw `www-data` do Dockera. Egress proxy dla internetu agentów. |
| **V. Modularność bez modyfikacji rdzenia** | PASS | Nowy moduł `modules/Scenarios` i `modules/System`. Rozszerzenia w `modules/Agents`, `modules/Chat`, `modules/Integrations` poprzez manifesty `module.json`. |
| **VI. 100% lokalizacji (I18n)** | PASS | Wszystkie nowe komunikaty i etykiety w `lang/pl/` oraz `lang/en/`. Całkowity zakaz hardcodowania tekstów. |
| **VII. Reguła dokumentacyjna (1.5 §0 pkt 7–10)** | PASS | Każdy PR aktualizuje `CHANGELOG.md`, fragment `first-setup.md` i sekcję `ConfigSection`. `docs:check` egzekwowane w CI. |

---

## 4. Project Structure

### 4.1 Dokumentacja i infrastruktura Spec Kit (`!Spec/`)
```text
!Spec/
├── Agenthub_v1.5.md                 # Specyfikacja źródłowa aktualizacji 1.5.0
├── .specify/                        # Konfiguracja Spec Kit
│   ├── memory/constitution.md       # Konstytucja projektu
│   └── templates/                   # Szablony artefaktów
└── specs/002-agenthub-v1-5/         # Baza wiedzy wdrożeniowej aktualizacji 1.5.0
    ├── spec.md                      # Skonsolidowane wymagania funkcjonalne
    ├── research.md                  # Decyzje architektoniczne i analiza Z1–Z11
    ├── data-model.md                # Schemat relacji i struktura tabel
    ├── quickstart.md                # Szybki start i weryfikacja scenariuszy
    ├── plan.md                      # Niniejszy dokument wdrożeniowy
    ├── tasks.md                     # Szczegółowa lista zadań wykonawczych
    └── contracts/                   # Formalne interfejsy PHP (ConfigSection, ContainerAdapter, etc.)
```

### 4.2 Kod źródłowy aplikacji (Workspace Root)
```text
/Applications/ServBay/www/EMET/
├── AGENTS.md                        # Reguły dla AI z regułą dokumentacyjną
├── CHANGELOG.md                     # Dziennik zmian projektu (sekcja [1.5.0])
├── app/
│   ├── Contracts/
│   │   ├── Config/ConfigSectionInterface.php
│   │   ├── Integrations/ContainerAdapterInterface.php
│   │   ├── Chat/GroupChatOrchestratorInterface.php
│   │   └── Scenarios/ScenarioEngineInterface.php
│   └── Console/Commands/
│       ├── DocsCheckCommand.php     # php artisan docs:check
│       ├── DocsFirstSetupCommand.php# php artisan docs:first-setup --build
│       ├── ConfigExportCommand.php  # php artisan config:export
│       └── ConfigImportCommand.php  # php artisan config:import
├── docs/
│   ├── FIRST_SETUP.md               # Generowana instrukcja pierwszej konfiguracji
│   └── adr/
│       └── 0001-scenario-editor.md  # ADR: Wybór Drawflow dla edytora schematów
├── modules/
│   ├── Agents/                      # Zdolności agenta, zakładka Skille, sandbox
│   │   ├── docs/first-setup.md
│   │   └── Config/AgentsConfigSection.php
│   ├── Chat/                        # Czat grupowy, orkiestrator, polityki kontekstu
│   │   ├── docs/first-setup.md
│   │   └── Config/ChatConfigSection.php
│   ├── Integrations/                # Zakładka Kontenery, auto-konfiguracja, podpisy adapterów
│   │   ├── docs/first-setup.md
│   │   └── Config/IntegrationsConfigSection.php
│   ├── Scenarios/                   # [NOWY] Edytor Drawflow, silnik Horizon, telemetria
│   │   ├── module.json
│   │   ├── docs/first-setup.md
│   │   └── Config/ScenariosConfigSection.php
│   └── System/                      # [NOWY] Eksport/Import, Instrukcja konfiguracji
│       ├── module.json
│       └── Config/SystemConfigSection.php
├── resources/views/
│   ├── layouts/
│   │   └── editor.blade.php         # Pełnoekranowy layout edytora scenariuszy
│   └── modules/
└── storage/app/skills/              # Repozytorium paczek skilli
```

---

## 5. Implementation Roadmap – Etapy 11–17

### Etap 11: Zdolności Agenta, Egress Proxy & Kontrakt Eksportu (Fundament 1.5.0)
- **Cel**: Zapewnienie bezpiecznego fundamentu dla kontroli agentów, reguły dokumentacyjnej oraz kontraktu eksportu.
- **Zakres**:
  1. Migracja tabeli `agents`: dodanie `internet_mode`, `context_mode`, `context_window_messages`; automatyczny backfill danych.
  2. Implementacja logiki bezstanowości w `AgentContextBuilder` dla trybu `stateless`.
  3. Konfiguracja Egress Proxy z tokenami agenta i blokadą SSRF dla `internet_mode=allowlist` i `open`.
  4. Nowy interfejs `ConfigSectionInterface` i rejestracja sekcji w manifeście `module.json`.
  5. Aktualizacja `AGENTS.md` o regułę dokumentacyjną (v1.5 §0) oraz utworzenie szablonu PR `.github/pull_request_template.md`.
  6. Szkielet polecenia `php artisan docs:check` i wpięcie do `compat-check.sh`.
- **Kryterium akceptacji (DoD)**: Drugie wywołanie agenta w trybie `stateless` nie zawiera historii; zablokowane zapytanie internetowe generuje zdarzenie `internet.blocked`; `docs:check` weryfikuje obecność wpisów; testy przechodzą na zielono.

### Etap 12: Skille – Zakładka, Wersjonowanie & Magazyn
- **Cel**: Kompletny system zarządzania skillami z wersjonowaniem i izolowanym sandboxem.
- **Zakres**:
  1. Migracje: rozszerzenie tabeli `skills`, nowa tabela `skill_versions`, modyfikacja `agent_skill`, nowa tabela `skill_runs`.
  2. Dysk storage `skills` (`storage/app/skills`) z ochroną przed zip-slip i sprawdzaniem sum SHA-256.
  3. Interfejs użytkownika w module *Agenci AI → Skille* (lista, filtry, podgląd szczegółów z Markdown, historia wersji).
  4. Kreator dodawania skilla: formularz ręczny, upload paczki ZIP, pobieranie z Git URL, import z integracji Hermes/OpenClaw.
  5. Izolowany sandbox wykonawczy dla testowania i uruchamiania skilli przez demona `agenthub-runner`.
  6. Walidacja zgodności uprawnień (`requires` vs zdolności agenta) przy przypisywaniu i wywołaniu.
  7. Implementacja `SkillsConfigSection` oraz fragmentu `first-setup.md` dla modułu.
- **Kryterium akceptacji (DoD)**: Możliwość wgrania i przetestowania skilla w sandboxie; blokada przypisania do agenta przy braku wymaganych zdolności; przypięcie wersji per agent działa poprawnie.

### Etap 13: Czat z Wieloma Agentami & Orkiestracja
- **Cel**: Rozmowy wieloagentowe z zaawansowaną orkiestracją, dynamicznym dołączaniem i limitami kosztowymi.
- **Zakres**:
  1. Migracje: rozszerzenie `conversations`, nowa tabela `conversation_participants`, rozszerzenie `messages`.
  2. Implementacja `GroupChatOrchestrator` z obsługą 4 trybów (`mention`, `broadcast`, `round_robin`, `moderator`).
  3. Logika dodawania/usuwania uczestników w trakcie konwersacji z politykami kontekstu (`full`, `summary`, `last_n`, `none`).
  4. Realtime: równoległe strumieniowanie delt odpowiedzi przez kanał prywatny Reverb `conversations.{id}` z identyfikacją uczestników.
  5. Zabezpieczenia: twardy limit `max_turns`, budżet kosztowy, wykrywanie powtórzeń i przerwanie pętli dialogowych.
  6. Kontrakt `ChatConfigSection` oraz fragment `first-setup.md`.
- **Kryterium akceptacji (DoD)**: Płynna rozmowa z 3 agentami w trybie `round_robin`; poprawne działanie wywołania `@mention`; zadziałanie bezpiecznika przy próbie zapętlenia; testy integracyjne orkiestratora.

### Etap 14: Scenariusze – Wizualny Edytor Blokowy & Telemetria
- **Cel**: Wizualne projektowanie przepływów agentów w osobnym oknie i ich asynchroniczne wykonywanie z telemetrią na schemacie.
- **Zakres**:
  1. Nowy moduł `modules/Scenarios` z rejestracją w Menu 1.
  2. Migracje: `scenarios`, `scenario_versions`, `scenario_triggers`, `scenario_runs`, `scenario_run_steps` oraz rozszerzenie tabel telemetrycznych.
  3. Osobny layout `/scenarios/{id}/editor` z integracją biblioteki **Drawflow** i komponentem Alpine.js (obsługa 12 typów węzłów).
  4. Walidacja poprawności grafu (brak cykli poza węzłem `loop`, jeden `start`, zamknięte ścieżki).
  5. Asynchroniczny silnik `ScenarioEngine` na kolejce Horizon z idempotentnymi krokami, pauzowaniem i wznawianiem (w tym krok `human`).
  6. Bezpieczny parser warunków (`symfony/expression-language`) bez PHP `eval`.
  7. Telemetria na żywo na schemacie (stany węzłów, animowane krawędzie, liczniki tokenów/kosztów) oraz odtwarzacz runów.
  8. Wyzwalacze: manual, cron, webhook (`POST /api/v1/hooks/scenarios/{token}`).
  9. Kontrakt `ScenariosConfigSection` oraz fragment `first-setup.md`.
- **Kryterium akceptacji (DoD)**: Zaprojektowanie, opublikowanie i uruchomienie scenariusza z agentem, warunkiem i krokiem `human`; telemetria aktualizuje się w kanwie na żywo; awaria workera nie powoduje utraty stanu runu.

### Etap 15: Integracje – Adopcja & Auto-Konfiguracja Kontenerów Docker
- **Cel**: Wykrywanie istniejących kontenerów Docker, ich bezpieczne podłączanie i auto-konfiguracja z rollbackiem.
- **Zakres**:
  1. Migracje: `integration_containers`, `autoconfig_profiles`, `container_config_runs`.
  2. Rozszerzenie kontraktu adapterów o `ContainerAdapterInterface` (sygnatury, `configWritablePaths()`, `execAllowlist()`).
  3. UI w *Integracje → Kontenery*: wykrywanie kandydatów (Hermes, OpenClaw, Ollama), kreator podłączenia w 3 trybach (`observe`, `configure`, `managed`).
  4. Bezpieczna konfiguracja ręczna (formularz z `configSchema()` i podgląd diffa).
  5. Wykonywanie profili auto-konfiguracji (auto-wybór modelu, zapis endpointów do proxy, generowanie tokenu instancji).
  6. Mechanizm automatycznego rollbacku do `config_snapshot` w razie niepowodzenia `healthProbe()`.
  7. Twardy zakaz otwierania terminala i dowolnego polecenia `exec`.
  8. Kontrakt `IntegrationsConfigSection` oraz fragment `first-setup.md`.
- **Kryterium akceptacji (DoD)**: Wykrycie kontenera Ollama/Hermes; zastosowanie auto-konfiguracji; rollback przy symulacji błędu; brak ingerencji w kontenery nieadoptowane.

### Etap 16: Eksport i Import Całej Konfiguracji (Moduł System)
- **Cel**: Pełny transfer konfiguracji platformy dla administratora z opcjonalnym szyfrowaniem sekretów.
- **Zakres**:
  1. Nowy moduł `modules/System` dostępny w Menu 1 wyłącznie dla roli `admin`.
  2. Migracje: `config_transfers`.
  3. Serwis `ConfigTransferManager` koordynujący eksport i import w oparciu o zarejestrowane `ConfigSectionInterface` z poszanowaniem `dependsOn()`.
  4. Generowanie paczki ZIP: manifest, pliki JSON sekcji, paczki skilli w `assets/skills/`.
  5. Bezpieczne szyfrowanie `secrets.enc` za pomocą `libsodium` (Argon2id + XChaCha20-Poly1305) z hasłem podanym przez admina (lub pominięcie sekretów).
  6. Kreator importu w UI oraz komenda CLI `php artisan config:import`:
     - Weryfikacja sum SHA-256 i wersji aplikacji,
     - Pełny raport dry-run z diffem,
     - Tryby `merge`, `overwrite`, `new_only`,
     - Automatyczny backup konfiguracji w `storage/backups/config-<timestamp>.zip` przed importem,
     - Wykonanie w transakcjach per sekcja z możliwością wycofania.
  7. CLI: `php artisan config:export` oraz `php artisan config:import`.
- **Kryterium akceptacji (DoD)**: Round-trip test: eksport konfiguracji, reset bazy i import daje 100% zgodności konfiguracji; zły hasło do `secrets.enc` zwraca błąd i nie modyfikuje bazy; dry-run nie zmienia stanu.

### Etap 17: Instrukcja Pierwszej Konfiguracji, Hardening & Wydanie 1.5.0
- **Cel**: Spójna instrukcja pierwszej instalacji, pełne testy E2E, audyt bezpieczeństwa i przygotowanie wydania.
- **Zakres**:
  1. Tabela `setup_progress` i interfejs w *System → Instrukcja konfiguracji* z paskiem postępu, deep-linkami i detektorami ukończenia.
  2. Komenda `php artisan docs:first-setup --build` składająca `docs/FIRST_SETUP.md` z fragmentów modułów.
  3. Pełna implementacja komendy CI `php artisan docs:check`.
  4. Rozszerzenie `scripts/compat-check.sh` oraz `scripts/update.sh` o weryfikacje 1.5.0 (Docker socket, nowe zmienne `.env`, test selftest).
  5. Seeder ról i uprawnień: rejestracja wszystkich nowych uprawnień 1.5.0 (`skills.*`, `chat.group.*`, `scenarios.*`, `integrations.containers.*`, `system.config.*`, `system.setup.view`).
  6. Aktualizacja wpisu w `CHANGELOG.md` dla wydania [1.5.0] wg wzoru ze specyfikacji §14.
  7. Aktualizacja dokumentacji technicznej: `ARCHITECTURE.md`, `MODULES.md`, `UPDATE.md`, `SECURITY.md`, `docs/integrations/*.md`.
  8. Pakiet testów regresyjnych E2E (Pest / Playwright).
- **Kryterium akceptacji (DoD)**: Wszystkie kryteria akceptacji Definition of Done (§12 specyfikacji) spełnione; zielone testy jednostkowe i integracyjne; brak błędów PHPStan L6+; `docs:check` przechodzi bez zastrzeżeń; gotowe wydanie 1.5.0.

---

## 6. Complexity Tracking & Risk Management

| Zagadnienie / Ryzyko | Potencjalny problem | Rozwiązanie architektoniczne w planie |
|---|---|---|
| **Złożoność edytora blokowego w Alpine** | Brak dedykowanego frameworka SPA mógłby utrudniać zarządzanie stanem węzłów i krawędzi. | Wybór biblioteki **Drawflow** (decyzja ADR 0001) – lekka, natywna obsługa DOM i zdarzeń, bezpośrednio spięta z komponentem Alpine.js i Reverb. |
| **Pętle dialogowe i eksplozja kosztów w czacie grupowym** | Agenci AI w dyskusji grupowej mogą wejść w nieskończoną pętlę generowania odpowiedzi. | Trzywarstwowy bezpiecznik w `GroupChatOrchestrator`: twardy limit `max_turns` (domyślnie 20), detektor powtarzalności hashy odpowiedzi oraz globalny budżet kosztowy konwersacji. |
| **Wykonywanie złośliwego kodu ze skilli (RCE / SSRF)** | Skille wgrywane z zewnętrznych źródeł mogą próbować uzyskać dostęp do bazy lub sieci lokalnej. | Wykonywanie wyłącznie w odizolowanym procesie/kontenerze sandbox (`agenthub-runner`) bez zmiennych `.env` i sekretów aplikacji; status `pending_review` przed publikacją; ochrona egress proxy. |
| **Bezpieczeństwo socketu Dockera przy adopcji kontenerów** | Przejęcie uprawnień do Dockera daje pełne uprawnienia na maszynie hosta. | Dostęp do socketu ma wyłącznie proces demona; brak interaktywnego terminala; polecenia wyłącznie z `execAllowlist()`; nieadoptowane kontenery są nietykalne. |
| **Wyciek poświadczeń w eksporcie konfiguracji** | Niezaszyfrowany plik ZIP z kluczami API na dysku użytkownika to krytyczne ryzyko bezpieczeństwa. | Domyślny eksport całkowicie wycina sekrety (`secretFields()`). Eksport z sekretami wymusza szyfrowanie Argon2id + XChaCha20-Poly1305 hasłem podanym przez admina (zero plaintextu). |
