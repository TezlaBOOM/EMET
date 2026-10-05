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
- **Blok 1 (Pasek Górny):** logo platformy, wskaźnik stanu systemu, selektor języka (PL/EN), przełącznik motywu ciemny/jasny oraz profil użytkownika.
- **Blok 2 (Nawigacja Modułów - Menu 1):** ikony i etykiety głównych obszarów: Dashboard, Agenci AI, Pamięć, Integracje, Ustawienia AI, Użytkownicy, Logi, Czat.
- **Blok 3 (Podkategorie - Menu 2):** kontekstowe zakładki wybranego modułu.
- **Blok 4 (Okruszki chleba i filtry):** lokalizacja użytkownika, wyszukiwarka i filtry zakresu czasowego.
- **Blok 5 (Pasek Stanu i Powiadomień):** komunikaty flash, powiadomienia WebSocket o zdarzeniach.
- **Blok 6 (Obszar Roboczy):** główna zawartość modułu, tabele danych, wykresy lub widoki edycyjne.
- **Blok 7 (Pływający Panel Czatu):** zunifikowane okno komunikacji z agentami dostępne globalnie w prawym dolnym rogu.

---

## 2. Podsystem Bramy Modeli (LLM Gateway)

Zlokalizowany w `services/llm-gateway/` i dostępny przez `App\Contracts\Llm\LlmGatewayInterface`.

- **Obsługiwane sterowniki:** `GeminiDriver`, `OpenAiDriver`, `AnthropicDriver`, `OllamaDriver`, `OpenRouterDriver`.
- **Zarządzanie kontami:** model *wiele kont na jednego dostawcę* (`llm_accounts`) z wagami (`weight`) i priorytetami.
- **Strategie routingu puli:**
  - `RoundRobinStrategy` – rotacyjne przełączanie kolejnych kont w puli.
  - `WeightedStrategy` – losowanie kont proporcjonalnie do przydzielonej wagi.
  - `LeastUsedStrategy` – wybór konta o najmniejszej liczbie zapytań w oknie czasowym.
  - `PriorityFallbackStrategy` – kierowanie ruchu do konta o najwyższym priorytecie z automatycznym failoverem.
- **Dynamiczny Cooldown:** automatyczne wykrywanie kodu `429 Too Many Requests`, zawieszenie konta na czas cooldownu i natychmiastowe ponowienie zapytania przez kolejne aktywne konto z puli.

---

## 3. Pamięć Wektorowa i Baza Wiedzy (Memory Service)

Zlokalizowany w `services/memory-service/` i dostępny przez `App\Contracts\Memory\VectorStoreInterface`.

- **Sterowniki:**
  - `QdrantVectorStore` – obsługa silnika Qdrant przez REST API z indeksami HNSW i wektorami o konfigurowalnej wymiarowości (domyślnie 1536).
  - `PgVectorStore` – obsługa lokalna / PostgreSQL z kalkulacją podobieństwa kosinusowego.
- **Dzielenie tekstu (`TextChunker`):** inteligentny podział dokumentów Markdown z uwzględnieniem nagłówków H1–H3 oraz konfigurowalnym overlapem fragmentów.
- **Wizualizacja grafowa:** interaktywny widok asocjacji pamięci w stylu Obsidian oparty na elemencie HTML5 Canvas z fizyką odpychania węzłów i przyciągania krawędzi semantycznych.

---

## 4. Zarządzanie Instancjami (Integration Manager)

Zlokalizowany w `services/integration-manager/` i powiązany z kontraktem `App\Contracts\Integrations\AgentRuntimeAdapter`.

- **Adaptery:** `HermesAdapter`, `OpenClawAdapter`, `ClaudeCodeAdapter`, `CodexAdapter`.
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
