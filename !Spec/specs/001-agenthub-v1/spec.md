# Feature Specification: AgentHub Platform v1

**Feature Name**: `001-agenthub-v1`  
**Source Specification**: [`AGENT_HUB_SPEC_1.md`](../../AGENT_HUB_SPEC_1.md)  
**Status**: Ready for Planning & Implementation  

---

## 1. Cel i zakres biznesowy

AgentHub to kompleksowa, modularna platforma webowa służąca do tworzenia, konfigurowania, uruchamiania, monitorowania i zarządzania cyklem życia autonomicznych agentów AI z poziomu zunifikowanego interfejsu graficznego.

### Główne filary systemu:
1. **Zarządzanie agentami AI i integracjami runtime**:
   - Domyślna integracja z runtime'ami **Hermes Agent** i **OpenClaw** oraz wsparcie dla agentów CLI (**Claude Code**, **Codex**).
   - **Automatyczny provisioning instancji** Hermes Agent i OpenClaw z poziomu panelu (jako osobne mikroserwisy systemd lub kontenery Docker) z pętlą uzgadniania stanu (*reconcile loop*) i automatycznym wycofaniem zmian (*rollback*).
2. **Brama modeli AI (LLM Gateway)**:
   - Wielodostawczość: Gemini, OpenAI, Anthropic Claude, Ollama, LM Studio, OpenRouter, vLLM, DeepSeek, Mistral, xAI Grok i dostawcy niestandardowi (OpenAI-compatible).
   - **Wielokontowość (Multi-Account Pools)**: obsługa wielu niezależnych kont/kluczy per dostawca, pule kont z automatycznym routingiem (Round-robin, Weighted, Least-used), śledzenie limitów RPM/TPM, cooldown po błędach 429 i automatyczny failover.
3. **Pamięć wektorowa współdzielona (Memory Service)**:
   - Abstrakcja magazynu wektorowego (`VectorStoreInterface`): Qdrant (domyślny) oraz PostgreSQL z rozszerzeniem `pgvector`.
   - Chunking, generowanie embeddingów, semantyczne wyszukiwanie hybrydowe, kolekcja pamięci z podglądem w stylu Obsidian (graf powiązań wiedzy) oraz możliwość bezpośredniej edycji notatek i wpisów pamięci.
4. **Zunifikowany interfejs w układzie 7 bloków**:
   - Dedykowany, bezramkowy layout webowy podzielony na 7 ergonomicznych bloków:
     - Blok 1: Logo/nawigacja główna
     - Blok 2: Menu 1 (rejestr głównych modułów platformy)
     - Blok 3: Przycisk wylogowania (przypięty do dołu kolumny bocznej)
     - Blok 4: Górny slot centralny (breadcrumbs/wyszukiwarka)
     - Blok 5: Profil zalogowanego użytkownika i status
     - Blok 6: Menu 2 (poziome podkategorie aktywnego modułu)
     - Blok 7: Główny obszar roboczy (widok modułu / podkategorii)
   - Globalne okno czatu dostępne z Menu 1 oraz jako pływający widget szybkiego dostępu.
5. **Telemetria, audyt i dashboard w czasie rzeczywistym**:
   - Śledzenie metryk zużycia tokenów, czasów reakcji (TTFT, latencja p50/p95), estymacji kosztów na podstawie konfigurowalnego cennika `model_pricing`.
   - Zdarzenia strumieniowane na żywo przez Laravel Reverb (WebSockets).
   - Kompletny dziennik audytu (działania użytkowników, aktywność agentów, zdarzenia AI, logi systemowe).
6. **Architektura modularna i bezobsługowa instalacja**:
   - Rozszerzalność bez modyfikacji rdzenia: każdy moduł w `modules/` deklaruje własny manifest, migracje, trasy, uprawnienia i pozycje menu.
   - Szybki start: gotowy kreator pierwszego uruchomienia (*Setup Wizard*), środowisko Docker Compose oraz idempotentny skrypt instalacji natywnej na Debian 13 (`scripts/install-debian13.sh`).
   - Domyślne konto administratora: `admin@admin.lan` / `admin` (idempotentny seeder, brak blokady systemu).

---

## 2. Aktorzy i scenariusze użytkownika

### Aktorzy:
- **Administrator (`admin`)**: Pełne uprawnienia do konfiguracji dostawców AI, pul kont, provisioningu instancji, zarządzania użytkownikami, systemem modułów i audytem.
- **Deweloper / Inżynier AI (`operator` / `developer`)**: Tworzenie i edycja agentów, podgląd pamięci wektorowej, uruchamianie zadań, interakcja przez czat, podgląd telemetrii.
- **Użytkownik standardowy (`viewer` / `user`)**: Rozmowa z przydzielonymi agentami przez czat, podgląd własnych sesji.

### Kluczowe scenariusze (User Journeys):
1. **Pierwsze uruchomienie**: Administrator uruchamia skrypt `install.sh` lub `install-debian13.sh`, loguje się danymi `admin@admin.lan` / `admin`. Otwiera się kreator Setup Wizard, w którym admin konfiguruje bazowego dostawcę AI i opcjonalnie inicjuje instalację runtime'u Hermes Agent.
2. **Konfiguracja puli kont LLM**: Administrator dodaje 3 konta dla dostawcy Google Gemini. Tworzy pulę kont `gemini-flash-pool` ze strategią *Least-used*, ustawia limity TPM i przypisuje pulę do agentów operacyjnych.
3. **Automatyczny provisioning instancji**: W module *Integracje → Hermes Agent → Instancje* użytkownik klika „Utwórz instancję”, wybiera wersję i zasoby. System uruchamia zadanie w tle, weryfikuje sumę kontrolną artefaktu, konfiguruje usługę systemd, sprawdza health-check i rejestruje nową instancję.
4. **Interakcja z agentem z pamięcią**: Użytkownik w oknie czatu zadaje pytanie agentowi. Agent pobiera kontekst z bazy wektorowej (Qdrant), wysyła zapytanie przez `llm-gateway`, generuje strumieniowaną odpowiedź, a telemetria rejestruje zużyte tokeny i czas wykonania.

---

## 3. Kryteria akceptacji (Definition of Done)

- [ ] Gotowy szkielet aplikacji Laravel 13 w głównym katalogu `Projekt-emet` z układem 7 bloków (WCAG AA, motyw jasny/ciemny, 100% stringów w `lang/pl` i `lang/en`).
- [ ] Działający mechanizm autentykacji, ról (RBAC) oraz idempotentny seeder konta `admin@admin.lan` / `admin`.
- [ ] Zaimplementowany moduł LLM Gateway z obsługą wielu dostawców, pul kont, cooldownu po 429 i streamingu odpowiedzi.
- [ ] Działający VectorStore z obsługą Qdrant oraz pgvector, chunkingiem i eksploratorem pamięci w stylu Obsidian.
- [ ] Zaimplementowany moduł Integracji z `AgentRuntimeAdapter`, adapterami Hermes Agent i OpenClaw (z mockami) oraz automatycznym provisioningiem instancji.
- [ ] Działający czat ze streamingiem oraz interaktywny dashboard z metrykami na żywo (Reverb/WebSockets).
- [ ] Gotowy i przetestowany instalator natywny dla Debian 13 (`scripts/install-debian13.sh`) oraz `docker-compose.yml`.
- [ ] Zielone testy automatyczne (Pest/PHPUnit) dla każdego modułu, PHPStan level 6+, czysty `CHANGELOG.md`.
