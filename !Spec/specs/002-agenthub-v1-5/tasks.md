# Implementation Tasks: AgentHub Platform v1.5 (Aktualizacja 1.5.0)

**Feature**: `002-agenthub-v1-5`  
**Plan**: [`plan.md`](./plan.md) | **Spec**: [`spec.md`](./spec.md) | **Constitution**: [`.specify/memory/constitution.md`](../../.specify/memory/constitution.md)  

---

## Przegląd etapów implementacji (Etapy 11–17)

| Etap | Nazwa etapu | Zakres zadań | Rezultat |
|:---:|---|---|---|
| **Etap 11** | Zdolności Agenta & Kontrakt Eksportu | T067 – T074 | Zdolności `internet_mode`/`context_mode`, egress proxy, interfejs `ConfigSection`, reguła dokumentacyjna i `docs:check` |
| **Etap 12** | Skille – Zakładka, Wersjonowanie & Magazyn | T075 – T083 | Magazyn skilli, import ZIP/Git/integracja, wersjonowanie, izolowany sandbox i sprawdzanie uprawnień |
| **Etap 13** | Czat z Wieloma Agentami & Orkiestracja | T084 – T092 | Czat grupowy, 4 tryby orkiestracji, dynamiczne dołączanie, streaming i limity pętli/kosztów |
| **Etap 14** | Scenariusze – Wizualny Edytor Blokowy & Telemetria | T093 – T104 | Moduł `modules/Scenarios`, edytor Drawflow w osobnym oknie, silnik Horizon i telemetria na schemacie |
| **Etap 15** | Integracje – Adopcja & Konfiguracja Kontenerów | T105 – T113 | Wykrywanie lokalnych kontenerów Docker, tryby adopcji, auto-konfiguracja modeli z rollbackiem, brak wolnego exec |
| **Etap 16** | Eksport i Import Całej Konfiguracji | T114 – T122 | Moduł `System`, ZIP export/import, szyfrowanie Argon2id+libsodium, dry-run, transakcje per sekcja |
| **Etap 17** | Instrukcja Konfiguracji, Hardening & Wydanie 1.5.0 | T123 – T132 | UI instrukcji w aplikacji, generator `FIRST_SETUP.md`, testy E2E, seeder uprawnień i wydanie 1.5.0 |

---

## Etap 11: Zdolności Agenta, Egress Proxy & Kontrakt Eksportu

- [X] **T067** Utworzenie migracji rozszerzającej tabelę `agents` o kolumny: `internet_mode` (enum: `off`, `allowlist`, `open`), `context_mode` (enum: `stateful`, `stateless`), `context_window_messages` (integer, nullable) wraz z automatycznym backfillem z `internet_enabled`.
- [X] **T068** Aktualizacja modelu `Agent` w `modules/Agents/Models/Agent.php`: obsługa nowych rzutowań enum, wirtualne pole kompatybilności wstecznej `internet_enabled` oraz walidacja dozwolonych wartości.
- [X] **T069** Implementacja obsługi trybu bezstanowego w `AgentContextBuilder`: całkowite pomijanie historii wiadomości i rezultatów wcześniejszych wywołań w trybie `stateless` przy zachowaniu dostępu do wiedzy wektorowej.
- [X] **T070** Rozszerzenie serwisu proxy sieciowego o weryfikację egress agenta: sprawdzanie dozwolonych domen allowlisty, tokenizacja żądań, blokada SSRF oraz rejestrowanie zdarzeń telemetrii `internet.request` i `internet.blocked`.
- [X] **T071** Utworzenie globalnego interfejsu `App\Contracts\Config\ConfigSectionInterface` w rdzeniu aplikacji oraz mechanizmu dynamicznej rejestracji sekcji z poziomu `module.json`.
- [X] **T072** Aktualizacja pliku reguł dla asystentów AI `AGENTS.md` oraz utworzenie szablonu PR `.github/pull_request_template.md` z listą kontrolną reguły dokumentacyjnej (v1.5 §0).
- [X] **T073** Implementacja komendy konsolowej `php artisan docs:check` weryfikującej spójność dokumentacji modułów, obecność sekcji `[Unreleased]` w `CHANGELOG.md` oraz poprawność linków.
- [X] **T074** Testy automatyczne etapu 11: test bezstanowości `AgentContextBuilderTest`, test egress proxy i blokady SSRF, test negatywny i pozytywny komendy `docs:check` (`tests/Feature/Agents/AgentCapabilitiesTest.php`).

---

## Etap 12: Skille – Zakładka, Wersjonowanie & Magazyn

- [X] **T075** Migracje bazy danych dla magazynu skilli: rozszerzenie tabeli `skills` (slug, readme_md, tags, requires, status, current_version_id), nowa tabela `skill_versions`, pivot `agent_skill` (skill_version_id, sort), nowa tabela `skill_runs`.
- [X] **T076** Konfiguracja dysku `skills` w `config/filesystems.php` (`storage/app/skills`) oraz implementacja serwisu rozpakowywania paczek z ochroną przed podatnością zip-slip i walidacją sumy kontrolnej SHA-256.
- [X] **T077** Modele Eloquent i relacje: `Skill`, `SkillVersion`, `SkillRun` wraz ze stanami publikacji (`draft`, `pending_review`, `active`, `deprecated`).
- [X] **T078** Implementacja widoków Livewire dla *Agenci AI → Skille*: lista z filtrami (typ, tagi, status), wyszukiwarka, karta szczegółów z renderowaniem Markdown i historią wersji.
- [X] **T079** Komponent kreatora dodawania skilla: formularz ręczny, upload paczki ZIP, import z Git URL (z allowlisty `SKILLS_GIT_ALLOWLIST`) oraz import z zarejestrowanych adapterów Hermes/OpenClaw.
- [X] **T080** Implementacja bezpiecznego sandboxa wykonawczego dla skilli: uruchamianie kodu narzędzia w odizolowanym procesie/kontenerze `agenthub-runner` bez dostępu do sekretów aplikacji i bazy danych.
- [X] **T081** Implementacja reguły zgodności uprawnień: blokada przypisania i wykonania skilla, którego wymagania (`requires`) przewyższają zdolności skonfigurowane na agencie.
- [X] **T082** Implementacja `SkillsConfigSection` oraz fragmentu pierwszej konfiguracji `modules/Agents/docs/first-setup.md`.
- [X] **T083** Testy automatyczne etapu 12: import paczki ZIP, weryfikacja sum SHA-256, test sandboxa, test blokady przy braku zdolności agenta (`tests/Feature/Skills/SkillsStoreTest.php`).

---

## Etap 13: Czat z Wieloma Agentami & Orkiestracja

- [X] **T084** Migracje bazy danych dla czatu wieloagentowego: rozszerzenie tabeli `conversations` (mode, orchestration, lead_agent_id, moderator_agent_id, max_turns, limits), nowa tabela `conversation_participants`, rozszerzenie tabeli `messages`.
- [X] **T085** Modele Eloquent: `ConversationParticipant` i rozszerzenie `Conversation` oraz `Message` o relacje uczestników i tury dialogowe.
- [X] **T086** Implementacja serwisu `App\Services\Chat\GroupChatOrchestrator` implementującego `GroupChatOrchestratorInterface` z obsługą 4 trybów: `mention`, `broadcast`, `round_robin`, `moderator`.
- [X] **T087** Logika dynamicznego zarządzania uczestnikami: dodawanie i usuwanie agentów w trakcie trwającej rozmowy z aplikacją wybranej polityki kontekstu (`full`, `summary`, `last_n`, `none`).
- [X] **T088** Zabezpieczenia orkiestracji: detektor zapętlenia dialogu (hashe wypowiedzi), twardy licznik `max_turns`, budżet tokenów/kosztów oraz oznaczanie wypowiedzi agentów jako `untrusted_source`.
- [X] **T089** Realtime i UI Livewire w module `Chat`: nagłówek z zarządzaniem uczestnikami, bąbelki wiadomości z awatarami agentów, równoległe strumieniowanie delt przez Reverb `conversations.{id}`, przycisk Stop/Pomiń.
- [X] **T090** Endpointy API REST i uprawnienia: `POST /api/v1/conversations`, zarządzanie uczestnikami, uprawnienia `chat.group.create` i `chat.group.manage`.
- [X] **T091** Implementacja `ChatConfigSection` oraz fragmentu `modules/Chat/docs/first-setup.md`.
- [X] **T092** Testy automatyczne etapu 13: test każdego trybu orkiestracji, test polityk dołączania kontekstu, test zatrzymania po przekroczeniu limitu tur (`tests/Feature/Chat/GroupChatOrchestrationTest.php`).

---

## Etap 14: Scenariusze – Wizualny Edytor Blokowy & Telemetria

- [X] **T093** Inicjalizacja modułu `modules/Scenarios`: utworzenie katalogu modułu, manifestu `module.json` z rejestracją w Menu 1 oraz trasami web/api.
- [X] **T094** Migracje bazy danych dla scenariuszy: tabele `scenarios`, `scenario_versions`, `scenario_triggers`, `scenario_runs`, `scenario_run_steps` oraz rozszerzenie indeksów telemetrycznych.
- [X] **T095** Implementacja dedykowanego layoutu pełnoekranowego `resources/views/layouts/editor.blade.php` z obsługą przycisku „Otwórz w nowym oknie” i synchronizacją WebSocket Reverb.
- [X] **T096** Integracja biblioteki **Drawflow** z komponentem Alpine.js: paleta węzłów, kanwa (zoom, pan, minimapa, undo/redo), panel parametrów węzła, autozapis szkicu.
- [X] **T097** Implementacja 12 typów węzłów w edytorze: `start`, `agent`, `skill`, `memory`, `condition`, `parallel`, `join`, `loop`, `human`, `transform`, `delay`, `end`.
- [X] **T098** Walidator grafu scenariusza: sprawdzanie acykliczności (poza węzłem loop), weryfikacja węzłów start/end, typów portów oraz limitu `SCENARIO_MAX_NODES=200`.
- [X] **T099** Silnik wykonawczy `ScenarioEngine` na kolejce Horizon: dysponowanie kroków jako idempotentnych zadań, obsługa pauzy/wznowienia, limitów budżetu i timeoutów.
- [X] **T100** Bezpieczny parser warunków logicznych dla węzła `condition` oparty o `symfony/expression-language` (całkowity zakaz PHP `eval`).
- [X] **T101** Telemetria na schemacie w czasie rzeczywistym: aktualizacja stanów węzłów (kolory, plakietki, tokeny, koszty) przez kanał Reverb `scenarios.{run_id}` oraz moduł odtwarzacza runów.
- [X] **T102** Obsługa wyzwalaczy: formularz ręczny, harmonogram `Schedule` (cron) oraz bezpieczny webhook `POST /api/v1/hooks/scenarios/{token}`.
- [X] **T103** Rejestracja `ScenariosConfigSection` oraz fragmentu `modules/Scenarios/docs/first-setup.md`.
- [X] **T104** Testy automatyczne etapu 14: walidator grafu, wykonanie scenariusza z węzłem agenta i warunkiem, krok akceptacji `human`, wznawianie po restarcie (`tests/Feature/Scenarios/ScenarioEngineTest.php`).

---

## Etap 15: Integracje – Adopcja & Auto-Konfiguracja Kontenerów Docker

- [X] **T105** Migracje bazy danych: tabele `integration_containers`, `autoconfig_profiles`, `container_config_runs`.
- [X] **T106** Rozszerzenie interfejsu adaptera integracji `ContainerAdapterInterface` oraz implementacja sterownika Docker socket w serwisie `integration-manager`.
- [X] **T107** Implementacja obsługi sygnatur kontenerów (`containerSignatures()`) dla adapterów Hermes Agent, OpenClaw i Ollama (oraz sterowników mock).
- [X] **T108** Widok Livewire w *Integracje → Kontenery*: tabela wykrytych kontenerów, przycisk „Wykryj kontenery”, filtry i szczegóły kontenera.
- [X] **T109** Kreator adopcji kontenera w 3 trybach: `observe`, `configure`, `managed` z opcjonalnym dołączeniem do sieci `agenthub-net`.
- [X] **T110** Bezpieczna konfiguracja ręczna: generowanie formularza ze schematu `configSchema()` adaptera, podgląd diffa przed zapisem, kopia zapasowa snapshotu.
- [X] **T111** Silnik wykonywania profili auto-konfiguracji (`autoconfig_profiles`): automatyczny dobór modelu z puli, ustawianie endpointów proxy, generowanie tokenu instancji, rollback w razie błędu health-checka.
- [X] **T112** Egzekwowanie bezpieczeństwa kontenerów: twardy zakaz otwierania terminala, filtrowanie poleceń przez `execAllowlist()`, ochrona kontenerów nieadoptowanych.
- [X] **T113** Testy automatyczne etapu 15: wykrywanie kontenerów (mock socket), proces adopcji, auto-konfiguracja i wycofanie zmian przy awarii (`tests/Feature/Integrations/ContainerAdoptionTest.php`).

---

## Etap 16: Eksport i Import Całej Konfiguracji

- [X] **T114** Inicjalizacja modułu `modules/System` z uprawnieniem administracyjnym i rejestracją w Menu 1.
- [X] **T115** Migracje bazy danych: tabela `config_transfers`.
- [X] **T116** Implementacja serwisu `App\Services\Config\ConfigTransferManager` obsługującego orkiestrację sekcji `ConfigSectionInterface` z poszanowaniem grafu zależności `dependsOn()`.
- [X] **T117** Logika eksportu konfiguracji: strumieniowanie danych do plików JSON, dołączanie paczek skilli w `assets/skills/`, generowanie `manifest.json` z sumami SHA-256.
- [X] **T118** Bezpieczne szyfrowanie sekretów: implementacja modułu szyfrującego plik `secrets.enc` za pomocą `libsodium` (Argon2id + XChaCha20-Poly1305) z hasłem podanym przez admina (lub pominięcie sekretów).
- [X] **T119** Kreator importu konfiguracji w UI: upload archiwum ZIP, weryfikacja sum kontrolnych, weryfikacja kompatybilności wersji, pełny raport dry-run z diffem.
- [X] **T120** Aplikacja importu: obsługa trybów `merge`, `overwrite`, `new_only`, automatyczny backup bazy w `storage/backups/config-<timestamp>.zip` oraz wykonanie w transakcji per sekcja.
- [X] **T121** Komendy CLI: `php artisan config:export` oraz `php artisan config:import` z obsługą wszystkich flag i trybu dry-run.
- [X] **T122** Testy automatyczne etapu 16: test round-trip (eksport -> czysta baza -> import -> identyczny stan), test szyfrowania błędnym hasłem, test transakcyjnego rollbacku po błędzie (`tests/Feature/System/ConfigTransferTest.php`).

---

## Etap 17: Instrukcja Pierwszej Konfiguracji, Hardening & Wydanie 1.5.0

- [X] **T123** Migracja bazy danych: tabela `setup_progress` rejestrująca postęp kroków konfiguracji.
- [X] **T124** Widok Livewire *System → Instrukcja konfiguracji*: lista kroków z paskiem postępu, deep-linkami do modułów oraz klasami automatycznej detekcji ukończenia.
- [X] **T125** Komenda konsolowa `php artisan docs:first-setup --build` składająca deterministycznie plik `docs/FIRST_SETUP.md` z fragmentów `first_setup` włączonych modułów.
- [X] **T126** Seeder uprawnień i ról: rejestracja wszystkich nowych uprawnień v1.5.0 w `RolesAndPermissionsSeeder.php` z przypisaniem do ról `admin`, `operator`, `viewer`.
- [X] **T127** Rozszerzenie diagnostyki `php artisan agenthub:selftest` o testy weryfikacyjne 1.5.0 (skille, czat grupowy, scenariusze, Docker socket, export dry-run).
- [X] **T128** Aktualizacja skryptów wdrożeniowych: rozszerzenie `scripts/compat-check.sh` oraz `scripts/update.sh` o testy kompatybilności 1.5.0 i nowe zmienne środowiskowe.
- [X] **T129** Audyt bezpieczeństwa: weryfikacja braku wycieków kluczy w logach/audycie, testy zabezpieczeń zip-slip, brak dostępu `www-data` do Dockera.
- [X] **T130** Aktualizacja dokumentacji projektu w `docs/`: `ARCHITECTURE.md`, `MODULES.md`, `UPDATE.md`, `SECURITY.md`, `docs/integrations/*.md`.
- [X] **T131** Przygotowanie wpisu w `CHANGELOG.md` dla wydania [1.5.0] zgodnie ze wzorem ze specyfikacji §14 oraz podbicie pliku `VERSION` do `1.5.0`.
- [X] **T132** Pełny pakiet testów regresyjnych (Pest/PHPUnit) z weryfikacją pokrycia kodu >= 80% oraz analiza statyczna PHPStan na poziomie 6+.
