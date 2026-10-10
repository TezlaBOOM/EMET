# Dziennik Zmian (CHANGELOG) – Projekt-Emet

Wszystkie istotne zmiany w projekcie Projekt-Emet są dokumentowane w tym pliku.
Format opiera się na [Keep a Changelog](https://keepachangelog.com/pl/1.0.0/).

## [Unreleased]

### Dodano (Added)
- **Skrypt naprawczy środowiska `scripts/repairkit.sh`** (oraz dowiązanie symboliczne `repairkit.sh` w katalogu głównym):
  - Automatyczna diagnoza i naprawa problemów z symulacją migracji bazy danych (`migrate --pretend`), w tym auto-konfiguracja lokalnego SQLite w razie niedostępności relacyjnego serwera bazy danych.
  - Wykrywanie stanu demona Docker i automatyczna aktywacja sterownika mock (`DOCKER_MOCK=true`, `CONTAINERS_MOCK=true`) przy braku socketu `docker.sock`.
  - Diagnoza magazynu wektorowego Qdrant, próba startu usługi lub aktywacja trybu symulacji (`QDRANT_MOCK=true`).
  - Naprawa uprawnień do katalogów `storage/` i `bootstrap/cache/` oraz czyszczenie pamięci podręcznej.
  - Obsługa trybów `--auto`, `--dry-run`, `--sqlite` oraz `--mock-all`.
- **Sterownik Mock dla Magazynu Wektorowego**:
  - Klasa `App\Services\MemoryService\MockVectorStore` z obsługą operacji wektorowych w pamięci i odpowiedzi na `ping()`.
  - Obsługa `QDRANT_MOCK=true` oraz `VECTOR_STORE_DRIVER=mock` w `QdrantVectorStore` i `AppServiceProvider`.
- **Aktywny Sterownik Mock dla Docker**:
  - Wzbogacenie `DockerSocketService` o wbudowany zestaw kontenerów symulacyjnych (Ollama, Hermes, OpenClaw) oraz metody `enableMockDriver()` i `isMock()`.
  - Plik konfiguracyjny `config/integrations.php`.

### Naprawiono (Fixed)
- **Symulacja migracji w `scripts/compat-check.sh`**:
  - Wyświetlanie czytelnej instrukcji naprawy z odniesieniem do `repairkit.sh` w przypadku niepowodzenia testu `migrate --pretend`.
- **Odpowiedzi na ping sterownika Qdrant**:
  - Poprawiono odczyt parametrów URL, hosta i portu Qdrant z konfiguracji `agenthub.memory.qdrant`.
  - Poprawna obsługa nagłówka `api-key` przy autoryzowanych zapytaniach do Qdrant.
  - Integracja statusu mock w poleceniu `agenthub:selftest`.


## [1.5.0] - 2026-10-10

### Dodano (Added)
- **Zdolności Agenta & Egress Proxy** (Etap 11):
  - Tryby dostępu do sieci `internet_mode` (`off`, `allowlist`, `open`) z filtrowaniem domen i ochroną przed SSRF.
  - Tryby kontekstu `context_mode` (`stateful`, `stateless`) oraz limit wiadomości `context_window_messages`.
  - Wirtualne pole kompatybilności `internet_enabled`.
  - Reguła dokumentacyjna v1.5 §0 i weryfikator `php artisan docs:check`.
- **Magazyn Skilli, Wersjonowanie & Sandbox** (Etap 12):
  - Zakładka *Agenci AI → Skille* z wyszukiwaniem, filtrowaniem i historią wersji.
  - Kreator dodawania skilla: formularz, paczki ZIP, repozytoria Git z allowlisty, import z adapterów Hermes/OpenClaw.
  - Bezpieczny sandbox wykonawczy dla skilli (`SkillSandboxService`) z ochroną Zip-Slip i weryfikacją SHA-256.
  - Blokada przypisywania skilli wymagających zdolności przewyższających uprawnienia agenta (`SkillPermissionGuard`).
- **Czat z Wieloma Agentami & Orkiestracja** (Etap 13):
  - Konwersacje grupowe z 2..8 agentami w 4 trybach (`mention`, `broadcast`, `round_robin`, `moderator`).
  - Dynamiczne dołączanie/odłączanie agentów z politykami kontekstu (`full`, `summary`, `last_n`, `none`).
  - Zabezpieczenia: detektor zapętlenia dialogu, twardy licznik `max_turns`, budżet tokenów/kosztów, oznaczanie wypowiedzi jako `untrusted_source`.
  - Endpointy REST API `/api/v1/conversations`.
- **Scenariusze – Wizualny Edytor Blokowy & Telemetria** (Etap 14):
  - Nowy moduł `modules/Scenarios` z edytorem Drawflow na dedykowanym layoutcie `/scenarios/{id}/editor`.
  - Obsługa 12 typów węzłów (`start`, `agent`, `skill`, `memory`, `condition`, `parallel`, `join`, `loop`, `human`, `transform`, `delay`, `end`).
  - Walidator grafu z limitem `SCENARIO_MAX_NODES=200` i acyklicznością.
  - Asynchroniczny silnik `ScenarioEngine` na kolejce Horizon z idempotentnym wznawianiem kroków.
  - Bezpieczny parser warunków logicznych `symfony/expression-language` (całkowity zakaz PHP `eval`).
  - Telemetria na schemacie w czasie rzeczywistym przez Reverb `scenarios.{run_id}` i odtwarzacz runów.
  - Wyzwalacze: formularz ręczny, cron oraz webhooki `POST /api/v1/hooks/scenarios/{token}`.
- **Integracje – Adopcja & Auto-Konfiguracja Kontenerów Docker** (Etap 15):
  - Zakładka *Integracje → Kontenery* z detekcją lokalnych kontenerów Docker przez socket `unix:///var/run/docker.sock`.
  - Tryby adopcji: `observe`, `configure`, `managed` (dołączenie do sieci `agenthub-net`).
  - Bezpieczna konfiguracja ręczna ze schematem `configSchema()` i podglądem diffa.
  - Profile auto-konfiguracji (`AutoConfigProfile`) z automatycznym rollbackiem przy awarii health-checka.
  - Twardy zakaz terminala interaktywnego i filtrowanie komend przez `execAllowlist()` adaptera.
- **Eksport i Import Całej Konfiguracji** (Etap 16):
  - Nowy moduł `modules/System` z obsługą transferów konfiguracji w oparciu o kontrakt `ConfigSectionInterface`.
  - Topologiczne sortowanie sekcji według grafu zależności `dependsOn()`.
  - Szyfrowanie sekretów `secrets.enc` za pomocą `libsodium` (Argon2id + XChaCha20-Poly1305) z hasłem podanym przez administratora.
  - Podgląd importu dry-run z wizualnym diffem oraz automatyczny backup w `storage/backups/config-<timestamp>.zip`.
  - Komendy konsolowe `php artisan config:export` i `php artisan config:import`.
- **Instrukcja Konfiguracji, Diagnostyka & Hardening** (Etap 17):
  - Podręcznik pierwszej konfiguracji w interfejsie *System → Instrukcja konfiguracji* z paskiem postępu.
  - Generator dokumentacji `php artisan docs:first-setup --build` tworzący `docs/FIRST_SETUP.md`.
  - Rozszerzona diagnostyka `php artisan agenthub:selftest` o testy weryfikacyjne 1.5.0.
  - Aktualizacja skryptów wdrożeniowych `scripts/compat-check.sh` oraz `scripts/update.sh`.

## [1.0.0] - 2026-10-04

### [Etap 10: Dokumentacja, Bezpieczeństwo & Wersja 1.0.0] - 2026-10-04

#### Dodano
- Kompletna dokumentacja techniczna w katalogu `docs/`:
  - `docs/README.md` (spis treści i przewodnik architektoniczny).
  - `docs/INSTALL.md` (szczegółowa instrukcja instalacji Docker All-in-one oraz natywnej Debian 13 z konfiguracją Apache, PHP-FPM, systemd i certbotem).
  - `docs/ARCHITECTURE.md` (opis przepływów danych, 7-blokowego layoutu UI, LLM Gateway, pamięci wektorowej, provisioningu instancji i telemetrii).
  - `docs/MODULES.md` (przewodnik rozszerzania platformy, struktura modułów, format manifestu `module.json` i automatyczny `ModuleManager`).
  - `docs/SECURITY.md` (architektura bezpieczeństwa: szyfrowanie kluczy AES-256-CBC, ochrona przed SSRF, allowlisty domen, prompt injection, sandboxing procesów `agenthub-runner`).
  - `docs/integrations/hermes.md` (dokumentacja integracji Hermes Agent z procedurami provisioningu i health checków).
  - `docs/integrations/openclaw.md` (dokumentacja integracji OpenClaw z mostkiem sieciowym i ochroną danych).
- Referencyjny moduł demonstracyjny `modules/Hello/`:
  - Manifest `module.json`, trasy `routes/web.php` oraz szablon Blade `resources/views/index.blade.php` prezentujące bezinwazyjne dodawanie nowych modułów do menu.
- Aktualizacja głównego pliku `README.md` z pełnym podsumowaniem możliwości platformy v1.0.0.
- Kompleksowy audyt bezpieczeństwa i testy:
  - `tests/Feature/Security/SecurityAndExtensibilityTest.php` (weryfikacja szyfrowania kluczy w bazie, brak wycieku sekretów w `audit_logs`, dostępność referencyjnego modułu Hello).
- 53 automatyczne testy jednostkowe i integracyjne (298 asercji) pokrywające wszystkie 10 etapów wdrożenia.

### [Etap 9: System Aktualizacji i Zgodności] - 2026-10-04

#### Dodano
- Plik wersji SemVer `VERSION` (`1.0.0`) oraz konfiguracja macierzy zgodności `config/compat.php`.
- Skrypt weryfikacji zgodności środowiska przed aktualizacją `scripts/compat-check.sh`:
  - Kontrola wersji PHP (>= 8.3), obecności modułów (`bcmath`, `curl`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `intl`, `json`).
  - Weryfikacja wolnego miejsca na dysku (>= 500 MB), uprawnień do zapisu w `storage/` i `bootstrap/cache/`, weryfikacja zarejestrowanych modułów oraz symulacja migracji (`migrate --pretend`).
- Skrypt pełnej automatycznej aktualizacji `scripts/update.sh`:
  - 7-krokowy proces: weryfikacja compat-check, tryb konserwacji (`artisan down`), zrzut kopii zapasowej (do `storage/backups/<timestamp>`), instalacja zależności Composer i npm, migracje bazy danych, czyszczenie i buforowanie pamięci podręcznej, testy diagnostyczne `agenthub:selftest` oraz automatyczny powrót (rollback) przy błędzie.
- Skrypt awaryjnego przywracania `scripts/rollback.sh`:
  - Przywracanie konfiguracji `.env`, bazy danych i wersji z backupu oraz wyłączenie trybu konserwacji.
- Komenda konsolowa `php artisan agenthub:check-update` (`CheckUpdateCommand.php`):
  - Sprawdzanie dostępności nowych wydań i aktualizacji na kanałach `stable`/`beta` z opcją formatowania JSON.
- Panel *Ustawienia -> System i Aktualizacje* (`/users/system`):
  - Podgląd aktualnej wersji i kanału wydań, interaktywne uruchamianie testu zgodności, lista kopii zapasowych.
- Zestaw testów automatycznych Etapu 9:
  - `tests/Feature/System/UpdateSystemTest.php` (5 testów: autoryzacja panelu systemu, podgląd wersji, komenda `agenthub:check-update`, wykonanie `scripts/compat-check.sh`, uruchamianie compat-check przez interfejs HTTP).

### [Etap 8: Setup Wizard & Instalatory] - 2026-10-04

#### Dodano
- Kreator pierwszego uruchomienia (`Setup Wizard`) w interfejsie (`resources/views/setup/wizard.blade.php`) i kontroler `SetupWizardController.php`:
  - 7 interaktywnych kroków: Powitanie i język, Opcjonalna zmiana danych administratora, Wybór silnika pamięci wektorowej (Qdrant vs PgVector), Dodanie pierwszego konta dostawcy AI, Detekcja integracji runtime (Hermes, OpenClaw, Claude Code, Codex), Szybki kreator pierwszego agenta, Podsumowanie i gotowość platformy.
  - Możliwość pominięcia każdego kroku i automatyczne zapisywanie stanu `setup_completed=true` w pliku `.env` oraz cache.
  - Middleware `CheckSetupCompleted.php` przekierowujący niezainicjowane instalacje bezpośrednio do kreatora.
- Skrypt instalacyjny dla środowiska Docker (`scripts/install.sh`):
  - Sprawdzanie wymagań wstępnych (Docker, docker compose, wolne porty 80, 5432, 6379, 6333, 8080).
  - Automatyczna generacja pliku `.env`, klucza aplikacji, uruchomienie kontenerów oraz wykonanie migracji i seederów.
- Referencyjny instalator natywny dla Debian 13 (`scripts/install-debian13.sh`):
  - Automatyczna instalacja i konfiguracja Apache 2.4 (z proxy WebSocket dla Reverb), PHP-FPM 8.4, Redis, PostgreSQL (lub MariaDB), Qdrant (lub pgvector), Node.js i Composer.
  - Szablony jednostek systemd dla instancji runtime'ów: `agenthub-hermes@.service` i `agenthub-openclaw@.service`.
  - Zapis danych instalacyjnych do `/root/agenthub-install.txt`.
- Komenda diagnostyczna `php artisan agenthub:selftest` (`SelfTestCommand.php`):
  - Testy stanu: połączenie z bazą, integralność kluczowych tabel schematu, zapis/odczyt cache, ping magazynu wektorowego, brama dostawców LLM, status adapterów runtime, obecność konta administratora.
  - Wsparcie flagi `--json` dla zautomatyzowanych testów i monitoringu zewnętrznego.
- Pełna weryfikacja składni powłoki bash (`bash -n scripts/*.sh`).
- Zestaw testów automatycznych Etapu 8:
  - `tests/Feature/Setup/SetupWizardAndSelfTestTest.php` (4 testy: przekierowanie gości, nawigacja kroków 1–7, zapis danych i pomijanie, wykonanie komendy `agenthub:selftest` z weryfikacją JSON).

### [Etap 7: Telemetria & Reaktywny Dashboard] - 2026-10-04

#### Dodano
- Podsystem telemetrii `services/telemetry-collector/` i kontrakt `App\Contracts\Telemetry\TelemetryCollectorInterface`:
  - `TelemetryCollectorService.php` odpowiedzialny za rejestrowanie zdarzeń agentów, wyliczanie metryk aktywności, agregację zużycia tokenów, wyliczanie latencji (TTFT, p50, p95), rozliczanie kosztów i monitorowanie zdrowia instancji.
- Baza danych dla telemetrii:
  - Tabela `telemetry_events` (surowe zdarzenia: `run.started`, `tool.called`, `memory.read`, `memory.write`, `llm.call`, `run.finished`, `error` z payloadem JSON).
  - Tabela `telemetry_rollups` (zagregowane godzinowo i dziennie statystyki połączeń, tokenów prompt/completion, TTFT, latencji p95 i kosztów per agent, dostawca i model).
  - Modele Eloquent: `TelemetryEvent.php` oraz `TelemetryRollup.php`.
- Komenda konsolowa i harmonogram cron:
  - `php artisan agenthub:telemetry-rollup` (`TelemetryRollupCommand.php`) agregująca wywołania LLM i zdarzenia do rollupów.
  - Zarejestrowane zadania w `routes/console.php`: godzinowy i dzienny rollup telemetrii oraz reconciliation loop instancji.
- Kanały Laravel Reverb do transmisji w czasie rzeczywistym:
  - `AgentTelemetryBroadcastEvent` (kanał `telemetry.agents`, alias `agent.event`).
  - `DashboardMetricsBroadcastEvent` (kanał `telemetry.dashboard`, alias `dashboard.updated`).
- Moduł `modules/Dashboard/` z pełną obsługą Menu 2 i podkategorii:
  - Przegląd (`overview.blade.php`) z reaktywnym pollingiem Alpine.js (5s), kafelkami KPI, listą aktywnych agentów, statusem zdrowia integracji i rozbiciem modeli.
  - Tokeny (`tokens.blade.php`) ze szczegółową analizą podziału wejście/wyjście per model i dostawca.
  - Wydajność (`performance.blade.php`) z analizą TTFT, mediany p50, percentyla p95 i wskaźnika sukcesu.
  - Koszty (`costs.blade.php`) z rozbiciem wydatków i podglądem cennika modeli `model_pricing`.
  - Endpoint JSON API `/dashboard/api/metrics` dla dynamicznych aktualizacji UI.
- Pełny słownik tłumaczeń w `lang/pl/dashboard.php` i `lang/en/dashboard.php` (zero hardcoded strings).
- Zestaw testów automatycznych Etapu 7:
  - `tests/Feature/Dashboard/TelemetryDashboardTest.php` (7 testów: autoryzacja, widoki podkategorii, format JSON API, rejestracja zdarzeń i broadcast Reverb, wyliczenia rollupów godzinowych, zdrowie integracji).

### [Etap 6: Integracje & Automatyczny Provisioning Instancji] - 2026-10-04

#### Dodano
- Podsystem zarządzania zewnętrznymi runtime'ami `services/integration-manager/` i kontrakt `App\Contracts\Integrations\AgentRuntimeAdapter`:
  - DTOs: `HealthStatus`, `TaskInput`, `RunHandle`, `ProvisionRequest`, `ProvisionSpec`.
  - 4 sterowniki runtime'ów agentowych: `HermesAdapter.php` (Hermes Agent), `OpenClawAdapter.php` (OpenClaw), `ClaudeCodeAdapter.php` (Claude Code CLI), `CodexAdapter.php` (Codex CLI) z wbudowanymi mockami i obsługą health checków.
- Baza danych dla instancji i procesów instalacyjnych:
  - Tabela `integration_instances` (nazwa, slug, typ runtime'u, tryb uruchomienia `systemd`/`docker`, dedykowany port, status, endpoint URL, timestamp testu zdrowia i treść ostatniego błędu).
  - Tabela `provisioning_jobs` (UUID zadania, powiązana instancja, typ akcji, status `queued`/`running`/`completed`/`failed`/`rolled_back`, logi tekstowe, komunikaty błędów).
- Szablony usług i konfiguracji w `services/integration-manager/templates/`:
  - `manifest.json`, `systemd.service.tpl`, `compose.yaml.tpl`, `config.tpl`.
- Serwis `IntegrationService.php` i asynchroniczne zadanie `ProvisionInstanceJob.php`:
  - Dynamiczna alokacja wolnego portu z puli 8100–8900.
  - Wykonywanie provisioningu: generowanie konfiguracji, rejestracja usługi, health probe.
  - Procedura automatycznego rollbacku (`rollback`) w razie niepowodzenia instalacji.
  - Pętla uzgadniania stanu (**Reconciliation Loop**) `agenthub:reconcile-instances` odpytująca health status wszystkich instancji.
- Moduł `modules/Integrations/` z manifestem `module.json`, kontrolerem `IntegrationController`, trasami i widokami:
  - Podkategorie w Menu 2: Wszystkie, Hermes Agent, OpenClaw, Claude Code, Codex.
  - Interaktywny modal instalacji nowej instancji z wyborem runtime'u i trybu.
  - Przycisk wymuszenia reconciliacji i usuwania instancji z audytem w `audit_logs`.
- Zestaw testów automatycznych Etapu 6:
  - `tests/Feature/Integrations/InstanceProvisioningTest.php` (6 testów: autoryzacja, widoki podkategorii, provisioning z alokacją portów, automatyczny rollback, pętla reconciliacji, usuwanie z logiem audytu).

#### Dodano
- Podsystem pamięci semantycznej `services/memory-service/` oraz kontrakt `App\Contracts\Memory\VectorStoreInterface`:
  - Sterownik `QdrantVectorStore` (REST API do tworzenia kolekcji, upsertu punktów z payloadem i wyszukiwania kosinusowego).
  - Sterownik `PgVectorStore` (obsługa wektorowa z kalkulacją podobieństwa kosinusowego w pamięci na potrzeby testów i środowisk deweloperskich).
- Baza danych dla pamięci wiedzy:
  - Tabela `memory_collections` (kolekcje wektorowe z konfiguracją wymiarowości, metryki `cosine`/`euclidean`/`dot` i modelu embeddingów).
  - Tabela `memory_chunks` (zindeksowane fragmenty tekstów z powiązaniem do wektorów i metadanymi).
- Generator embeddingów i chunker:
  - `TextChunker` (podział dokumentów Markdown i tekstowych z zachowaniem struktury nagłówków i konfigurowalnym overlapem).
  - `EmbeddingService` (automatyczna generacja wektorów przez `LlmGateway`, zapis w bazie relacyjnej i wektorowej, zapytania k-NN oraz generowanie grafu relacji).
- Moduł `modules/Memory` z manifestem `module.json`, kontrolerem `MemoryController`, trasami i widokami:
  - Eksplorator wiedzy (`index.blade.php`) z filtrowaniem kolekcji i modalem dodawania notatek.
  - Graf powiązań w stylu Obsidian (`graph.blade.php`) na HTML5 Canvas z fizyką odpychania i przyciągania węzłów.
  - Wyszukiwarka semantyczna (`search.blade.php`) z rankingiem trafności (Cosine Similarity %).
  - Panel zarządzania kolekcjami (`collections.blade.php`).
- Zestaw testów automatycznych Etapu 5:
  - `tests/Unit/Memory/VectorSearchTest.php` (4 testy jednostkowe: podział tekstu przez `TextChunker`, podobieństwo kosinusowe w `PgVectorStore`, indeksowanie i wyszukiwanie w `EmbeddingService`, stan sterownika `QdrantVectorStore`).

### [Etap 4: Agenci AI & Zunifikowany Czat] - 2026-10-04

#### Dodano
- Baza danych dla agentów i historii konwersacji:
  - Tabela `agents` (definicje agentów: nazwa, slug, opis, runtime `native`/`hermes`/`openclaw`/`claude_code`/`codex`, pula kont LLM, model priorytetowy, prompt systemowy, temperatura).
  - Tabela `agent_skills` (konfiguracja narzędzi agenta: `web_search`, `file_editor`, `memory_search`).
  - Tabele `chat_conversations` i `chat_messages` (pełna historia czatów powiązana z użytkownikami i agentami).
- Moduł `modules/Agents` z manifestem `module.json`, kontrolerem `AgentController`, trasami i widokami:
  - Lista agentów z podglądem runtime'u, modeli, puli i aktywnych skilli (`index.blade.php`).
  - Kreator agenta (`create.blade.php`) ze suwakiem temperatury i wyborem puli kont.
  - Formularz edycji agenta z interaktywnym włączaniem/wyłączaniem umiejętności (`edit.blade.php`).
  - Predefiniowane szablony ról (`templates.blade.php`: Badacz wiedzy, Coder, Asystent Hermes).
- Moduł `modules/Chat` z manifestem `module.json`, kontrolerem `ChatController`, trasami i widokami:
  - Zunifikowany interfejs czatu z boczną historią rozmów (`index.blade.php`).
  - Archiwum rozmów z paginacją i wyszukiwaniem (`history.blade.php`).
  - Strumieniowanie tokenów przez Server-Sent Events (`streamMessage`) oraz asynchroniczne wysyłanie wiadomości AJAX z budowaniem kontekstu historii i promptu systemowego.
- Zestaw testów automatycznych Etapu 4:
  - `tests/Feature/Chat/AgentChatStreamTest.php` (4 testy funkcjonalne: autoryzacja gości, pełny CRUD agenta i toggle skilli, tworzenie konwersacji i wysyłanie wiadomości, strumieniowanie SSE).

### [Etap 3: LLM Gateway & Pule Kont] - 2026-10-04

#### Dodano
- Podsystem **LLM Gateway** (`services/llm-gateway/`) oraz kontrakt `App\Contracts\Llm\LlmGatewayInterface`:
  - DTOs: `CompletionRequest`, `CompletionResponse`, `EmbeddingRequest`, `EmbeddingResponse`, `ConnectionTestResult`.
  - Interfejs sterowników `App\Contracts\Llm\LlmDriverInterface`.
- Sterowniki dostawców LLM:
  - `GeminiDriver` (Google Gemini REST API v1beta z obsługą formatu contents/parts i embeddingów).
  - `OpenAiDriver` (OpenAI REST API v1 chat/completions oraz embeddings).
  - `AnthropicDriver` (Anthropic Claude Messages API v1).
  - `OllamaDriver` (Lokalny serwer Ollama HTTP API /api/chat i /api/embeddings).
  - `OpenRouterDriver` (OpenRouter API ze wsparciem nagłówków platformy).
- Baza danych i migracje dla bramy modeli:
  - Tabela `llm_account_pools` (definicje pul kont i strategii routingu).
  - Tabela `llm_account_pool_members` (przypisanie kont do pul z priorytetami i wagami).
  - Tabela `model_pricing` z seederem `ModelPricingSeeder` (wzorcowe cenniki input/output za 1M tokenów).
  - Tabela `llm_calls` (rejestr telemetrii: tokeny, latencja całkowita, TTFT, wyestymowany koszt USD i status).
- 4 strategie routingu w pulach (`services/llm-gateway/Strategies/`):
  - `RoundRobinStrategy` (naprzemienny wybór kont w oparciu o atomowy licznik Cache).
  - `WeightedStrategy` (losowy ważony dobór według wag kont).
  - `LeastUsedStrategy` (dobór konta najdawniej lub wcale nieużywanego).
  - `PriorityFallbackStrategy` (dobór konta o najwyższym priorytecie liczbowym).
- Mechanizm **Dynamic Cooldown & Failover**:
  - Detekcja błędu HTTP 429 oraz nagłówka `Retry-After`.
  - Rzucanie dedykowanego wyjątku `RateLimitExceededException` i automatyczne nakładanie cooldownu na konto.
  - Automatyczny ponowny wybór kolejnego dostępnego konta w puli bez przerywania zapytania użytkownika (do 3 prób failoveru).
- Strumieniowanie tokenów (generator `stream()`) i estymacja kosztów operacyjnych.
- Zestaw testów automatycznych Etapu 3:
  - `tests/Unit/LlmGateway/PoolFailoverTest.php` (4 testy jednostkowe: strategie selekcji kont, failover przy 429 i aktywacja cooldownu, telemetria strumieniowania i kalkulacja kosztów z tabeli cennika).

### [Etap 2: Ustawienia, Bezpieczeństwo i Baza Konfiguracji AI] - 2026-10-04

#### Dodano
- Moduł `modules/Users` z manifestem `module.json`, kontrolerem `UserController`, trasami i widokami:
  - Lista użytkowników z paginacją, przypisywaniem ról RBAC (`admin`, `operator`, `viewer`) i zabezpieczeniem przed samousunięciem.
  - Formularze profilu użytkownika, zmiany motywu (`light`, `dark`, `system`) i podglądu ról.
- Moduł `modules/Logs` oraz model `AuditLog` z migracją `create_audit_logs_table` i helperem `AuditLog::record(...)`:
  - Rejestracja zdarzeń logowania, tworzenia i usuwania użytkowników oraz zmian konfiguracji kont AI z adresem IP i user-agentem.
- Baza danych dla konfiguracji AI:
  - Tabela `llm_providers` z seederem `DefaultProvidersSeeder` (Gemini, OpenAI, Anthropic Claude, Ollama, OpenRouter).
  - Tabela `llm_accounts` z bezpiecznym szyfrowaniem kluczy API (`api_key`, `api_secret`) w locie przez mechanizm `'encrypted'` w Laravelu (AES-256-CBC).
- Moduł `modules/AiSettings` z manifestem `module.json`, kontrolerem `AiSettingsController` i widokami:
  - Przegląd dostawców modeli i domyślnych parametrów (`index.blade.php`).
  - Zarządzanie kontami API z dynamicznym testem połączenia (`accounts.blade.php`, `LlmConnectionTester`).
  - Wizualizacja strategii routingu i pul kont (`pools.blade.php`).
  - Limity RPM/TPM oraz referencyjny cennik tokenów (`limits.blade.php`).
- Zestaw testów automatycznych Etapu 2:
  - Test jednostkowy `tests/Unit/LlmAccountEncryptionTest.php` weryfikujący, że klucze API w surowym SQL są zaszyfrowane jako ciphertext, a przez model Eloquent odszyfrowywane w locie.
  - Testy funkcjonalne `tests/Feature/Users/UserManagementTest.php` (uprawnienia RBAC, zarządzanie użytkownikami, audit logi, zarządzanie kontami AI i test połączenia).

### [Etap 1: Fundament & Architektura bazowa] - 2026-10-04

#### Dodano
- Zainicjalizowano szkielet frameworka **Laravel 13** (`13.34.0`) bezpośrednio w głównym katalogu `Projekt-emet` bez podfolderów.
- Utworzono plik konfiguracyjny platformy [config/agenthub.php](file:///Applications/ServBay/www/Projekt-emet/config/agenthub.php) ze stałymi, limitami instancji, parametrami bramy LLM oraz pamięci wektorowej.
- Przygotowano środowisko konteneryzacji [docker-compose.yml](file:///Applications/ServBay/www/Projekt-emet/docker-compose.yml) (`app` [PHP 8.4], `db` [PostgreSQL 16 + pgvector], `redis`, `qdrant`, `reverb`) wraz z `docker/app/Dockerfile` i `.dockerignore`.
- Skonfigurowano migracje użytkowników z rozszerzeniami o kolumny `theme` oraz `setup_completed`.
- Zainstalowano i zintegrowano pakiet `spatie/laravel-permission` z obsługą ról (`admin`, `operator`, `viewer`) i uprawnień.
- Zaimplementowano w pełni idempotentne seedery: `AdminUserSeeder` (`admin@admin.lan` / `admin` z rolą `admin`) oraz `RolesAndPermissionsSeeder`.
- Zaimplementowano silnik modularności `ModuleManager` oraz `ModuleServiceProvider` z automatycznym wykrywaniem manifestów `module.json` w `modules/`.
- Zaimplementowano bezramkowy interfejs w układzie 7 bloków w Tailwind CSS / CSS Grid:
  - Blok 1: Logo (`resources/views/components/layout/logo.blade.php`)
  - Blok 2: Menu 1 (`resources/views/components/layout/menu-primary.blade.php`)
  - Blok 3: Wyloguj (`resources/views/components/layout/logout.blade.php`)
  - Blok 4: Topbar Center Slot (`resources/views/components/layout/topbar-center.blade.php`)
  - Blok 5: Profil zalogowanego (`resources/views/components/layout/user-nav.blade.php`)
  - Blok 6: Menu 2 (`resources/views/components/layout/menu-secondary.blade.php`)
  - Blok 7: Obszar roboczy (`resources/views/layouts/app.blade.php` oraz `resources/views/components/layouts/app.blade.php`)
- Przygotowano słowniki językowe I18n w `lang/pl/` i `lang/en/` (`common.php`, `auth.php`, `dashboard.php`, `chat.php`).
- Utworzono pierwszy moduł [modules/Dashboard](file:///Applications/ServBay/www/Projekt-emet/modules/Dashboard) z manifestem `module.json` i widokiem kafelkowym.
- Zaimplementowano kontroler logowania [LoginController.php](file:///Applications/ServBay/www/Projekt-emet/app/Http/Controllers/Auth/LoginController.php) oraz formularz logowania `auth/login.blade.php`.
- Przygotowano i zweryfikowano pełny zestaw testów automatycznych `tests/Feature/Auth/AdminLoginTest.php` (6 testów zielonych, weryfikacja logowania, idempotencji seederów, ról i układu 7 bloków).
