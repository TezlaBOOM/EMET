# Architektura Platformy Projekt-Emet

Projekt-Emet to modularna platforma orkiestracji agentów AI, zaprojektowana w oparciu o Laravel 13 z możliwością działania w trybie monolitycznym in-process (`SERVICE_MODE=local`) lub jako zestaw rozproszonych mikroserwisów (`SERVICE_MODE=remote`).

```
+------------------------------------------------------------------------+
|                            Projekt-Emet Core                           |
|       (Laravel 13, RBAC Spatie, Module Manager, Blade + Alpine.js)     |
+----+---------------+------------------+---------------------+----------+
     |               |                  |                     |
     v               v                  v                     v
+------------+ +---------------+ +--------------------+ +----------------+
|    LLM     | |    Memory     | | Integration Manager| |   Telemetry    |
|  Gateway   | |    Service    | |      Service       | |   Collector    |
+------------+ +---------------+ +--------------------+ +----------------+
| - OpenAI   | | - Qdrant REST | | - Hermes Agent     | | - Events DB    |
| - Gemini   | | - PgVector    | | - OpenClaw         | | - Rollups      |
| - Claude   | | - Chunker     | | - Claude Code CLI  | | - Reverb WS    |
| - Ollama   | | - Embeddings  | | - Codex CLI        | | - Cost Tracker |
| - Failover | | - Graph Canvas| | - Port Allocation  | | - Dashboard    |
+------------+ +---------------+ +--------------------+ +----------------+
```

---

## 1. 7-Blokowy Layout UI

Interfejs Projekt-Emet opiera się na stałej 7-blokowej strukturze prezentacji:
- **Blok 1 (Pasek Boczny / Logo i Wersja):** logo platformy, nazwa aplikacji oraz numer wersji projektu (`v{{ config('agenthub.version') }}`). **Zasada wersjonowania:** przy wprowadzaniu większych zmian w projekcie (wersje major/minor, nowe kluczowe moduły lub wydania) numer wersji musi zostać zaktualizowany w pliku `VERSION` oraz `config/agenthub.php` (`version`), aby dashboard zawsze odzwierciedlał aktualny stan systemu.
- **Blok 2 (Nawigacja Modułów - Menu 1):** ikony i etykiety głównych obszarów: Dashboard, Agenci AI, Pamięć, Integracje, Ustawienia AI, Użytkownicy, Logi, Czat.
- **Blok 3 (Podkategorie - Menu 2):** kontekstowe zakładki wybranego modułu.
- **Blok 4 (Okruszki chleba i filtry):** lokalizacja użytkownika, wyszukiwarka i filtry zakresu czasowego.
- **Blok 5 (Pasek Stanu i Powiadomień):** komunikaty flash, powiadomienia WebSocket o zdarzeniach.
- **Blok 6 (Obszar Roboczy):** główna zawartość modułu, tabele danych, wykresy lub widoki edycyjne.
- **Blok 7 (Pływający Panel Czatu):** zunifikowane okno komunikacji z agentami dostępne globalnie w prawym dolnym rogu.

---

## 2. Podsystem Bramy Modeli (LLM Gateway)

Zlokalizowany w `services/llm-gateway/` i dostępny przez `App\Contracts\Llm\LlmGatewayInterface`.

- **Obsługiwane sterowniki:** `GeminiDriver`, `OpenAiDriver`, `AnthropicDriver`, `OllamaDriver`, `OpenRouterDriver`, `LmStudioDriver`.
- **Zarządzanie kontami:** model *wiele kont na jednego dostawcę* (`llm_accounts`) z wagami (`weight`) i priorytetami.
- **Strategie routingu puli:**
  - `RoundRobinStrategy` – rotacyjne przełączanie kolejnych kont w puli.
  - `WeightedStrategy` – losowanie kont proporcjonalnie do przydzielonej wagi.
  - `LeastUsedStrategy` – wybór konta o najmniejszej liczbie zapytań w oknie czasowym.
  - `PriorityFallbackStrategy` – kierowanie ruchu do konta o najwyższym priorytecie z automatycznym failoverem.
  - `ComplexityAwareStrategy` *(v1.6.0)* – inteligentny wybór modelu na podstawie analizy złożoności zapytania (`PromptComplexityAnalyzer`): proste zadania kierowane są do darmowych modeli lokalnych (`ollama`, `lmstudio`) lub *Flash/Mini*, a złożone do modeli flagowych.
- **Semantyczna Pamięć Podręczna (`SemanticCacheService`, v1.6.0):** warstwa cache'u semantycznego w `LlmGateway` rozpoznająca identyczne i semantycznie zbieżne zapytania (próg podobieństwa kosinusowego domyślnie `0.95`) i zwracająca odpowiedź bez kosztu tokenów.
- **Bramka Bezpieczeństwa Promptów i DLP (`AiGuardrailsService`, v1.6.0):** dwukierunkowy filtr wykrywający próby *Prompt Injection / Jailbreak* oraz pseudonimizujący dane wrażliwe PII (`PESEL`, `EMAIL`, `CARD`, `IBAN`, `APIKEY`, `PHONE`) przed wysłaniem do zewnętrznych dostawców chmurowych z automatyczną de-pseudonimizacją w odpowiedzi.
- **Dynamiczny Cooldown:** automatyczne wykrywanie kodu `429 Too Many Requests`, zawieszenie konta na czas cooldownu i natychmiastowe ponowienie zapytania przez kolejne aktywne konto z puli.

---

## 3. Pamięć Wektorowa i Baza Wiedzy (Memory Service)

Zlokalizowany w `services/memory-service/` i dostępny przez `App\Contracts\Memory\VectorStoreInterface`.

- **Sterowniki:**
  - `QdrantVectorStore` – obsługa silnika Qdrant przez REST API z indeksami HNSW i wektorami o konfigurowalnej wymiarowości (domyślnie 1536).
  - `PgVectorStore` – obsługa lokalna / PostgreSQL z kalkulacją podobieństwa kosinusowego.
- **Dzielenie tekstu (`TextChunker`):** inteligentny podział dokumentów Markdown z uwzględnieniem nagłówków H1–H3 oraz konfigurowalnym overlapem fragmentów.
- **Hybrydowy RAG & GraphRAG (`HybridRagService`, v1.7.0):** połączenie wyszukiwania gęstego (Qdrant/PgVector), pełnotekstowego scoringu leksykalnego **BM25**, automatycznej ekstrakcji encji i relacji do Grafu Wiedzy (`memory_entities`, `memory_relations`, ekspansja 1-hop) oraz fuzji **Cross-Encoder Reranker**.
- **Wizualizacja grafowa:** interaktywny widok asocjacji pamięci i encji GraphRAG w stylu Obsidian oparty na elemencie HTML5 Canvas z fizyką odpychania węzłów i przyciągania krawędzi semantycznych.

---

## 4. Zarządzanie Instancjami i MCP Hub (Integration Manager)

Zlokalizowany w `services/integration-manager/` i powiązany z kontraktami `App\Contracts\Integrations\AgentRuntimeAdapter` oraz `App\Contracts\Integrations\McpHubInterface`.

- **Adaptery:** `HermesAdapter`, `OpenClawAdapter`, `ClaudeCodeAdapter`, `CodexAdapter`.
- **Natywny MCP Hub (`McpHubService`, v1.7.0):** dwukierunkowa obsługa standardu Model Context Protocol (JSON-RPC 2.0): podłączanie zewnętrznych serwerów MCP z automatyczną synchronizacją narzędzi do rejestru `skills` oraz wbudowany serwer MCP (`POST /api/v1/mcp/rpc`) wystawiający agentów, pamięć GraphRAG i scenariusze dla zewnętrznych środowisk IDE.
- **Alokacja portów:** dynamiczne przydzielanie wolnych portów TCP z puli `8100–8900`.
- **Szablony konfiguracyjne:** renderowanie szablonów jednostek systemd (`agenthub-hermes@.service`) i konfiguracji Docker Compose.
- **Automatyczny Rollback:** w przypadku niepowodzenia health-probe, system natychmiast czyści wygenerowane pliki, oznacza stan jako `error` i zwalnia zasoby.
- **Reconciliation Loop:** cron uruchamiany co 30 sekund (`agenthub:reconcile-instances`) weryfikujący stan zdrowia wszystkich instancji i aktualizujący statusy w bazie.

---

## 5. Telemetria i Reaktywny Dashboard (Telemetry Collector)

Zlokalizowany w `services/telemetry-collector/`.

- **Zdarzenia agentów:** zapis do tabeli `telemetry_events` (`run.started`, `tool.called`, `memory.read`, `memory.write`, `llm.call`, `run.finished`, `error`).
- **Rollupy danych (`agenthub:telemetry-rollup`):** preagregacja godzinowa i dzienna do tabeli `telemetry_rollups` eliminująca kosztowne skanowanie surowych rekordów `llm_calls` przez Dashboard.
- **Transmisja Reverb:** kanały WebSocket `telemetry.agents` oraz `telemetry.dashboard` dla natychmiastowych aktualizacji statusu agentów i zużycia tokenów w UI.

---

## 6. Rejestr Modeli AI & Kaskadowy Wybór w Agencie (v1.5.7)

Zarządzanie modelami LLM (`App\Contracts\Llm\ModelManagementInterface`, `App\Models\LlmModel`):

- **Tabela `models`:** centralna ewidencja modeli wszystkich dostawców (`provider_id`, `model_key`, `display_name`, `enabled`, `capabilities`, `context_window`, `parameters`, `max_output_tokens`, `overrides`, `tags`).
- **Ochrona nadpisań (`overrides`):** synchronizacja modeli od dostawcy aktualizuje parametry fabryczne, zachowując bez zmian niestandardowe limity tokenów, temperaturę oraz cenniki per konto (`account_id` w `model_pricing`) zdefiniowane przez użytkownika.
- **Kaskada wyboru modelu (`<x-model-select>`):** trzystopniowy interfejs wyboru w agencie (Runtime → Konto/Pula → Model) walidowany regułą `ValidModelForAgent`.
- **Wykrywanie niedostępności:** agenci ze wskazanym modelem, który został wyłączony lub usunięty, są automatycznie oznaczani plakietką „Model niedostępny”.

---

## 7. Cykl Życia Skilli, SemVer i Bezpieczne Usuwanie (v1.5.7)

Zarządzanie skillami (`App\Contracts\Skills\SkillLifecycleInterface`):

- **Wersjonowanie definicji (SemVer):** każda modyfikacja kodu skilla rejestruje wpis w `skill_versions` z wymogiem określenia typu zmiany (`patch`, `minor`, `major`) i changeloga.
- **Flaga nieużywanych skilli (🚩):** automatyczne oznaczanie w UI skilli niepowiązanych z agentami ani grafami scenariuszy. Zgodnie z wytycznymi bezpieczeństwa, system **nie usuwa skilli automatycznie** — o usunięciu decyduje wyłącznie użytkownik.
- **Ochrona zależności i Kosz:** próba usunięcia skilla używanego w aktywnych konfiguracjach zwraca kod HTTP 409 z listą powiązań. Usunięte skille trafiają do kosza (`deleted_at`), z możliwością bezpiecznego przywrócenia lub trwałego usunięcia (`skills.purge`).

---

## 8. Scenariusze Blokowe: Wersjonowanie Grafów & Harmonogramy Czasowe (v1.5.7)

Zarządzanie schematami i timerami (`App\Contracts\Scenarios\ScenarioSchedulerInterface`, `App\Contracts\Scenarios\ScenarioEngineInterface`):

- **Szkice robocze grafów:** edycja opublikowanego scenariusza tworzy wersję roboczą, nie zakłócając działania wersji produkcyjnej aż do jej opublikowania.
- **Blokada współbieżnej edycji:** nakładanie `locked_by` i `locked_at` w edytorze Drawflow chroni przed nadpisywaniem pracy innych użytkowników.
- **Silnik harmonogramów (`scenarios:dispatch-due`):**
  - Obsługa 6 typów wyzwalaczy (`once`, `interval`, `daily`, `weekly`, `monthly`, `cron`) w strefie `Europe/Warsaw`.
  - Atomowa blokada `Cache::lock('scenarios:dispatch-due', 55)` zapobiega wyścigom w środowiskach wielowątkowych.
  - Unikalny `idempotency_key` w `scenario_runs` gwarantuje jednokrotne wykonanie danego cyklu.
  - Automatyczna pauza (`state = error`) po 5 kolejnych awariach chroni infrastrukturę przed kaskadą błędów.
- **Debugger Scenariuszy „Time-Travel” i Forking Stanu (v1.8.0):**
  - Zapis migawek stanu (`state_snapshot_before`, `state_snapshot_after`, `duration_ms`) na każdym kroku `scenario_run_steps` oraz `state_snapshots` w `scenario_runs`.
  - Możliwość inspekcji osi czasu (`inspectRunTimeline`) oraz rozwidlenia wykonania od dowolnego węzła (`forkAndReplayFromStep`) ze zmodyfikowanym stanem zmiennych.

---

## 9. Ewaluacja Jakości Agentów A/B & Rozproszony Tracing OpenTelemetry (v1.8.0)

- **Podsystem Ewaluacji Jakości i Testów A/B (`App\Contracts\Agents\AgentEvaluationInterface`):**
  - Zestawy testowe *Golden Datasets* (`agent_eval_suites`) i przebiegi porównawcze (`agent_eval_runs`) oceniające modele A vs B przy użyciu metryk leksykalnych, referencyjnych i sędziego *LLM-as-a-Judge*.
- **Rozproszony Tracing OpenTelemetry (`App\Contracts\Telemetry\DistributedTracerInterface`):**
  - Hierarchiczne spany (`telemetry_spans`) z korelacją `trace_id` i `parent_span_id`, wizualizacją kaskadową (*Waterfall View*) oraz automatyczną identyfikacją najwolniejszego ogniwa (`bottleneck_span`) i pierwotnej przyczyny błędu (`root_cause_error`).


