# Implementation Tasks: AgentHub Platform v1

**Feature**: `001-agenthub-v1`  
**Plan**: [`plan.md`](./plan.md) | **Spec**: [`spec.md`](./spec.md) | **Constitution**: [`.specify/memory/constitution.md`](../../.specify/memory/constitution.md)  

---

## Przegląd etapów implementacji (10 Milestones)

| Etap | Nazwa etapu | Zakres zadań | Rezultat |
|:---:|---|---|---|
| **Etap 1** | Fundament & Architektura | T001 – T008 | Działający szkielet z logowaniem, layoutem 7 bloków i rejestrem modułów |
| **Etap 2** | Ustawienia & Bezpieczeństwo | T009 – T015 | CRUD użytkowników, role RBAC, audyt zdarzeń, konfiguracja dostawców AI |
| **Etap 3** | LLM Gateway & Pule Kont | T016 – T023 | Sterowniki LLM, pule kont, dynamiczny cooldown 429, streaming i telemetria `llm_calls` |
| **Etap 4** | Agenci AI & Zunifikowany Czat | T024 – T030 | CRUD agentów, konfiguracja skilli, czat globalny i panel pływający |
| **Etap 5** | Pamięć Wektorowa (Memory) | T031 – T037 | VectorStore (Qdrant/pgvector), chunking, embeddingi, eksplorator i graf Obsidian |
| **Etap 6** | Integracje & Provisioning Instancji | T038 – T046 | Adaptery runtime'ów, szablony systemd/docker, provisioning instancji, reconcile loop |
| **Etap 7** | Telemetria & Live Dashboard | T047 – T052 | Telemetry collector, rollupy godzinowe, widżety Reverb WebSockets, cennik modeli |
| **Etap 8** | Setup Wizard & Instalatory | T053 – T057 | Wizard pierwszego startu, `install.sh`, `install-debian13.sh`, `agenthub:selftest` |
| **Etap 9** | Auto-Update & Zgodność | T058 – T061 | `compat-check.sh`, `update.sh`, `rollback.sh`, panel aktualizacji w UI |
| **Etap 10** | Dokumentacja & Hardening v1.0.0 | T062 – T066 | Kompletna dokumentacja w `docs/`, audyt bezpieczeństwa, moduł referencyjny `Hello` |

---

## Etap 1: Fundament & Architektura bazowa

- [x] **T001** Zainicjalizowanie aplikacji Laravel 13 bezpośrednio w katalogu głównym `Projekt-emet` (bez tworzenia nowego podfolderu projektu) z konfiguracją `config/agenthub.php` i plikiem `.env.example`.
- [x] **T002** [P] Konfiguracja środowiska kontenerowego `docker-compose.yml` (usługi: `app`, `db` [PostgreSQL 16], `redis`, `qdrant`, `reverb`).
- [x] **T003** Konfiguracja bazy danych i migracji użytkowników oraz instalacja i konfiguracja pakietu `spatie/laravel-permission` (migracje ról i uprawnień).
- [x] **T004** Implementacja idempotentnych seederów: `AdminUserSeeder.php` (`admin@admin.lan` / `admin` z rolą `admin`) oraz `RolesAndPermissionsSeeder.php`.
- [x] **T005** [P] Utworzenie szkieletu modułowości: `app/Providers/ModuleServiceProvider.php` skanującego katalog `modules/` z obsługą manifestów `module.json`.
- [x] **T006** Implementacja układu 7 bloków w Tailwind CSS / CSS Grid:
  - Blok 1: Logo (`resources/views/components/layout/logo.blade.php`)
  - Blok 2: Menu 1 (`resources/views/components/layout/menu-primary.blade.php`)
  - Blok 3: Wyloguj (`resources/views/components/layout/logout.blade.php`)
  - Blok 4: Topbar Center Slot (`resources/views/components/layout/topbar-center.blade.php`)
  - Blok 5: Profil zalogowanego (`resources/views/components/layout/user-nav.blade.php`)
  - Blok 6: Menu 2 (`resources/views/components/layout/menu-secondary.blade.php`)
  - Blok 7: Obszar roboczy (`resources/views/layouts/app.blade.php`)
- [x] **T007** [P] Konfiguracja słowników i tłumaczeń bazowych: `lang/pl/` oraz `lang/en/` (brak zahardkodowanych stringów).
- [x] **T008** Testy automatyczne etapu 1: test logowania domyślnego admina, poprawność renderowania 7 bloków, seeder idempotency test (`tests/Feature/Auth/AdminLoginTest.php`).

---

## Etap 2: Ustawienia, Bezpieczeństwo i Baza Konfiguracji AI

- [x] **T009** Utworzenie modułu `modules/Users/` z manifestem `module.json` i rejestracją w Menu 1.
- [x] **T010** Implementacja widoków i komponentów Livewire dla `modules/Users`: CRUD użytkowników, przypisywanie ról, formularz profilu z opcjonalną zmianą hasła.
- [x] **T011** [P] Utworzenie modułu `modules/Logs/` oraz modelu `AuditLog` rejestrującego zdarzenia logowania, zmian konfiguracji i działań użytkowników.
- [x] **T012** Migracje bazy danych dla konfiguracji AI: tabele `llm_providers` oraz `llm_accounts` (z polem `api_key` oznaczonym castem `encrypted`).
- [x] **T013** Seeder `DefaultProvidersSeeder.php` wprowadzający domyślnych dostawców (Gemini, OpenAI, Claude, Ollama, OpenRouter).
- [x] **T014** Komponent Livewire w module `modules/AiSettings`: lista dostawców, dodawanie wielu kont do jednego dostawcy, formularz z walidacją i testem połączenia (`testConnection`).
- [x] **T015** Testy automatyczne etapu 2: weryfikacja szyfrowania klucza API w bazie danych (`tests/Unit/LlmAccountEncryptionTest.php`), testy uprawnień CRUD użytkowników (`tests/Feature/Users/UserManagementTest.php`).

---

## Etap 3: LLM Gateway & Pule Kont

- [x] **T016** Utworzenie serwisu `services/llm-gateway/` oraz interfejsu `App\Contracts\Llm\LlmGatewayInterface`.
- [x] **T017** [P] Implementacja sterowników dostawców:
  - `GeminiDriver.php`
  - `OpenAiDriver.php`
  - `AnthropicDriver.php`
  - `OllamaDriver.php`
  - `OpenRouterDriver.php`
- [x] **T018** Migracje tabel `llm_account_pools`, `llm_account_pool_members`, `llm_calls` oraz `model_pricing`.
- [x] **T019** Implementacja strategii wyboru konta z puli w `services/llm-gateway/Strategies/`: `RoundRobinStrategy`, `WeightedStrategy`, `LeastUsedStrategy`, `PriorityFallbackStrategy`.
- [x] **T020** Implementacja mechanizmu **Dynamic Cooldown & Failover**:
  - Wykrywanie kodów HTTP 429 i nagłówka `Retry-After`.
  - Przypisywanie statusu `cooldown` i automatyczny ponowny wybór dostępnego konta z puli.
- [x] **T021** Obsługa strumieniowania tokenów w `LlmGateway` (Server-Sent Events i dispatching zdarzeń do Reverb).
- [x] **T022** Pomiar telemetrii wywołania: rejestracja tokenów, TTFT (Time To First Token), latencji całkowitej i estymacji kosztów z tabeli `model_pricing` do `llm_calls`.
- [x] **T023** Testy automatyczne etapu 3: test strategii puli kont, test failoveru przy symulacji błędu 429, test pomiaru tokenów (`tests/Unit/LlmGateway/PoolFailoverTest.php`).

---

## Etap 4: Agenci AI & Zunifikowany Czat

- [x] **T024** Migracje tabel `agents`, `agent_skills`, `chat_conversations`, `chat_messages`.
- [x] **T025** Utworzenie modułu `modules/Agents/` z rejestracją w Menu 1 i podkategoriami Menu 2: Aktywni, Wszyscy agenci, Utwórz agenta, Szablony.
- [x] **T026** Komponenty Livewire dla zarządzania agentami: tworzenie/edycja agenta, dobór puli kont LLM, konfiguracja system promptu, temperatury i powiązania z pamięcią.
- [x] **T027** Implementacja zarządzania skillami agenta (`agent_skills`): włączanie narzędzi, walidacja konfiguracji narzędzi.
- [x] **T028** Utworzenie modułu `modules/Chat/` (pełny widok z Menu 1) oraz pływającego widgetu czatu (przycisk w prawym dolnym rogu Bloku 7).
- [x] **T029** Obsługa strumieniowanej konwersacji w czacie z podglądem kroków myślenia agenta i użycia narzędzi.
- [x] **T030** Testy automatyczne etapu 4: CRUD agenta, zapis historii czatu, test strumieniowania wiadomości (`tests/Feature/Chat/AgentChatStreamTest.php`).

---

## Etap 5: Pamięć Wektorowa (Memory Service)

- [x] **T031** Utworzenie serwisu `services/memory-service/` oraz interfejsu `App\Contracts\Memory\VectorStoreInterface`.
- [x] **T032** [P] Implementacja sterowników bazy wektorowej: `QdrantVectorStore.php` (REST/gRPC) oraz `PgVectorStore.php`.
- [x] **T033** Migracje tabel `memory_collections` oraz `memory_chunks`.
- [x] **T034** Implementacja generatora embeddingów (`EmbeddingService.php`) oraz mechanizmu dzielenia tekstu (*chunker* dla Markdown i plików tekstowych).
- [x] **T035** Utworzenie modułu `modules/Memory/` z widokami w Menu 2: Eksplorator, Graf powiązań, Wyszukiwarka wektorowa, Kolekcje.
- [x] **T036** Implementacja grafu powiązań pamięci w stylu Obsidian (komponent wizualizacji grafowej JavaScript/Canvas) oraz bezpośredniego edytora notatek.
- [x] **T037** Testy automatyczne etapu 5: zapis wektorów, semantyczne zapytanie wyszukiwarki, test chunkera tekstu (`tests/Unit/Memory/VectorSearchTest.php`).

---

## Etap 6: Integracje & Automatyczny Provisioning Instancji

- [x] **T038** Utworzenie serwisu `services/integration-manager/` i kontraktu `App\Contracts\Integrations\AgentRuntimeAdapter`.
- [x] **T039** [P] Implementacja adapterów runtime'ów z pełnymi sterownikami mock: `HermesAdapter.php`, `OpenClawAdapter.php`, `ClaudeCodeAdapter.php`, `CodexAdapter.php`.
- [x] **T040** Migracje tabel `integration_instances` oraz `provisioning_jobs`.
- [x] **T041** Przygotowanie szablonów instancji w `services/integration-manager/templates/`: `manifest.json`, `systemd.service.tpl`, `compose.yaml.tpl`, `config.tpl`.
- [x] **T042** Implementacja asynchronicznego zadania `ProvisionInstanceJob.php`: walidacja, alokacja portu z puli, render szablonów, uruchomienie jednostki systemd/kontenera, health probe i rejestracja.
- [x] **T043** Implementacja pętli uzgadniania stanu (**Reconciliation Loop**) uruchamianej co 30 s (`agenthub:reconcile-instances`).
- [x] **T044** Implementacja mechanizmu automatycznego rollbacku przy niepowodzeniu uruchomienia instancji.
- [x] **T045** Utworzenie modułu `modules/Integrations/` z podkategoriami w Menu 2: Hermes Agent, OpenClaw, Claude Code, Codex, Inne.
- [x] **T046** Testy automatyczne etapu 6: test provisioningu instancji z mock adapterem, test procedury rollback, test pętli reconcile (`tests/Feature/Integrations/InstanceProvisioningTest.php`).

---

## Etap 7: Telemetria & Reaktywny Dashboard

- [x] **T047** Utworzenie serwisu `services/telemetry-collector/` zbierającego metryki wywołań LLM, zdarzenia agentów i health instancji.
- [x] **T048** Implementacja komendy konsolowej i zadania cron do rollupów danych: agregacja godzinowa i dzienna tokenów, latencji i kosztów.
- [x] **T049** Konfiguracja kanałów Laravel Reverb dla telemetrii na żywo (`telemetry.agents`, `telemetry.dashboard`).
- [x] **T050** Utworzenie modułu `modules/Dashboard/` z podkategoriami Menu 2: Przegląd, Tokeny, Wydajność, Koszty.
- [x] **T051** Implementacja widżetów Livewire na Dashboardzie: aktywni agenci, wykres zużycia tokenów, prędkość (TTFT, p50/p95), szacunkowe koszty, status zdrowia integracji.
- [x] **T052** Testy automatyczne etapu 7: wyliczenia kosztów w rollupach, poprawność emisji zdarzeń Reverb (`tests/Feature/Dashboard/TelemetryDashboardTest.php`).

---

## Etap 8: Setup Wizard & Instalatory

- [x] **T053** Implementacja kreatora pierwszego uruchomienia (`Setup Wizard`) w interfejsie: wykrywanie flagi `setup_completed=false`, kroki 1–7 (wybór bazy wektorowej, dodanie konta AI, detekcja integracji, pierwszy agent).
- [x] **T054** Przygotowanie skryptu instalacyjnego dla środowiska Docker: `scripts/install.sh` (sprawdzanie wymagań, generowanie kluczy, start kontenerów, migracje, budowa frontendu).
- [x] **T055** Przygotowanie referencyjnego instalatora natywnego dla Debian 13: `scripts/install-debian13.sh` (obsługa `--db=pgsql|mysql`, `--vector=qdrant|pgvector`, Apache 2.4, PHP-FPM, systemd, zapis `/root/agenthub-install.txt`).
- [x] **T056** Implementacja komendy diagnostycznej `php artisan agenthub:selftest` sprawdzającej bazę, cache, Qdrant, Reverb i adaptery.
- [x] **T057** Weryfikacja działania skryptów powłoki (`bash -n scripts/*.sh` oraz `shellcheck`).

---

## Etap 9: System Aktualizacji i Zgodności

- [x] **T058** Implementacja narzędzia `scripts/compat-check.sh` sprawdzającego wersje PHP, rozszerzenia, miejsce na dysku i strukturę bazy.
- [x] **T059** Implementacja skryptu aktualizacji `scripts/update.sh` z automatycznym backupem bazy i plików konfiguracyjnych.
- [x] **T060** Implementacja skryptu awaryjnego przywracania wersji `scripts/rollback.sh`.
- [x] **T061** Panel aktualizacji w *Ustawienia -> System*: sprawdzanie dostępnych wydań, uruchamianie testu zgodności przed aktualizacją.

---

## Etap 10: Dokumentacja, Bezpieczeństwo & Wersja 1.0.0

- [x] **T062** [P] Opracowanie kompletu dokumentacji w katalogu `docs/`: `README.md`, `INSTALL.md`, `ARCHITECTURE.md`, `MODULES.md`, `SECURITY.md`, `integrations/hermes.md`, `integrations/openclaw.md`.
- [x] **T063** [P] Utworzenie referencyjnego modułu rozszerzenia `modules/Hello/` demonstrującego dodawanie nowej funkcjonalności bez modyfikacji rdzenia.
- [x] **T064** Audyt bezpieczeństwa: weryfikacja szyfrowania kluczy w bazie, brak wycieku sekretów w logach, analiza uprawnień procesów `agenthub-runner`.
- [x] **T065** Uruchomienie pełnego pakietu testów regresyjnych (Pest/PHPUnit) oraz statycznej analizy kodu (PHPStan level 6+, Laravel Pint).
- [x] **T066** Aktualizacja pliku `CHANGELOG.md` dla oficjalnego wydania v1.0.0.
