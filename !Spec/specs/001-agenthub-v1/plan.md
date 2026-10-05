# Implementation Plan: AgentHub Platform v1

**Branch**: `001-agenthub-v1` | **Date**: 2026-10-04 | **Spec**: [`spec.md`](./spec.md)  
**Input**: Bazowa specyfikacja projektu [`AGENT_HUB_SPEC_1.md`](../../AGENT_HUB_SPEC_1.md)  

---

## 1. Summary

Celem wdrożenia jest budowa kompletnej, modularnej platformy **AgentHub** służącej do orkiestracji, uruchamiania, monitorowania i zarządzania agentami AI. Architektura opiera się na szkielecie Laravel 11/12 z reaktywnym frontendem Livewire 3 / Alpine.js w bezramkowym układzie 7 bloków, dedykowanej bramie modeli AI (LLM Gateway) z obsługą wielu kont i automatycznego failoveru, współdzielonej pamięci wektorowej (Qdrant / pgvector) z grafem powiązań wiedzy, automatycznym provisioningu mikroserwisów agentów (Hermes Agent, OpenClaw) oraz telemetrii na żywo strumieniowanej przez WebSockets (Laravel Reverb).

Realizacja prowadzona jest w 10 ścisłych etapach z zachowaniem zasad określonych w [Konstytucji Projektu](../../.specify/memory/constitution.md).

---

## 2. Technical Context

- **Język i środowisko bazowe**: PHP 8.3 / PHP 8.4
- **Framework backendowy**: Laravel 13 (aktualna wersja)
- **Warstwa reaktywna i UI**: Laravel Livewire 3, Alpine.js, Tailwind CSS (Vanilla/Tailwind zestaw stylów, ciemny/jasny motyw, WCAG AA)
- **Komunikacja na żywo (WebSockets)**: Laravel Reverb
- **Bazy danych relacyjne**: PostgreSQL 16 (domyślna) / MariaDB 10.11+
- **Pamięć podręczna i kolejki asynchroniczne**: Redis 7+ / Laravel Horizon
- **Baza wektorowa (Vector Store)**: Qdrant (lokalny daemon systemd lub kontener) z alternatywą w postaci `pgvector`
- **Orkiestracja i środowisko wykonawcze**:
  - Deweloperskie: `docker-compose.yml` + `scripts/install.sh`
  - Produkcyjne natywne: dedykowany instalator `scripts/install-debian13.sh` dla Debian 13 (Apache 2.4 + PHP-FPM)
  - Instancje agentów: dynamiczne szablony usług systemd (`agenthub-hermes@<slug>.service`) lub dedykowane kontenery Docker
- **Testy i weryfikacja jakości**: Pest / PHPUnit, Laravel Pint (PSR-12), PHPStan (level 6+), `bash -n` i `shellcheck` dla skryptów bash.
- **Bezpieczeństwo**: Szyfrowanie kluczy API w bazie (`encrypted` casts), autoryzacja RBAC, izolacja procesów wykonawczych (`agenthub-runner`).

---

## 3. Constitution Check

*GATE: Weryfikacja zgodności z Konstytucją Projektu.*

| Reguła konstytucyjna | Status | Mechanizm wdrożenia w planie |
|---|:---:|---|
| **I. Praca etapowa (10 etapów)** | PASS | Plan podzielony na 10 niezależnych etapów; każdy etap ma zdefiniowane mierzalne kryteria DoD. |
| **II. Kontrakty & zakaz zgadywania API** | PASS | Interfejsy `AgentRuntimeAdapter`, `LlmGatewayInterface`, `VectorStoreInterface`. Mock drivery do czasu weryfikacji. |
| **III. Testy & jakość kodu** | PASS | Każdy etap zawiera zadania testowe (Pest/PHPUnit). Obowiązkowa analiza PHPStan L6+ oraz Pint. |
| **IV. Bezpieczeństwo i poufność** | PASS | Pole `api_key` szyfrowane w modelu `LlmAccount`. Maskowanie w logach. Brak dostępu `www-data` do Dockera. |
| **V. Modularność bez modyfikacji rdzenia** | PASS | Układ katalogów `modules/` z manifestami `module.json`, dynamiczne wstrzykiwanie pozycji Menu 1 i Menu 2. |
| **VI. 100% lokalizacji (I18n)** | PASS | Pliki językowe `lang/pl` oraz `lang/en`. Zakaz hardcodowania tekstów w szablonach. |
| **VII. Seeder domyślny** | PASS | Idempotentny `AdminUserSeeder` tworzący konto `admin@admin.lan` / `admin`. |

---

## 4. Project Structure

### 4.1 Dokumentacja i specyfikacje (`!Spec/`)
```text
!Spec/
├── AGENT_HUB_SPEC_1.md             # Źródłowa specyfikacja klienta
├── .specify/                       # Konfiguracja i infrastruktura Spec Kit
│   ├── memory/constitution.md      # Konstytucja projektu
│   └── templates/                  # Szablony specyfikacji, planów i zadań
└── specs/001-agenthub-v1/          # Baza wiedzy wdrożeniowej feature 001
    ├── spec.md                     # Skonsolidowane wymagania funkcjonalne
    ├── research.md                 # Analiza techniczna i decyzje
    ├── data-model.md               # Schemat ERD i definicje tabel
    ├── quickstart.md               # Szybki start i diagnostyka
    ├── plan.md                     # Niniejszy dokument wdrożeniowy
    ├── tasks.md                    # Zadania wykonawcze (Phase 2)
    └── contracts/                  # Formalne kontrakty PHP (OpenAPI/Interfejsy)
```

### 4.2 Kod źródłowy aplikacji (Workspace Root: Projekt-emet)
```text
Projekt-emet/
├── !Spec/                          # Dokumentacja Spec Kit i specyfikacje
├── app/                            # Rdzeń aplikacji Laravel 13
│   ├── Contracts/                  # Globalne interfejsy i kontrakty
│   ├── Providers/                  # Service providery (w tym ModuleServiceProvider)
│   ├── Services/                   # Serwisy wspólne (Crypto, ReverbBroadcaster)
│   └── View/Components/            # Komponenty layoutu 7 bloków
├── config/
│   ├── agenthub.php                # Globalna konfiguracja, wersja, flagi
│   └── modules.php                 # Rejestr i konfiguracja modułów
├── contracts/                      # Schematy JSON / OpenAPI
├── database/
│   ├── migrations/                 # Migracje rdzenia (users, rbac, audit)
│   └── seeders/                    # AdminUserSeeder, RolesSeeder, ProvidersSeeder
├── lang/
│   ├── pl/                         # Słowniki języka polskiego
│   └── en/                         # Słowniki języka angielskiego
├── modules/                        # Niezależne moduły biznesowe
│   ├── Dashboard/                  # Blok 7: Widżety, telemetria, koszty
│   ├── Agents/                     # Zarządzanie agentami, skille, profile
│   ├── Memory/                     # Eksplorator pamięci, graf, wyszukiwanie
│   ├── Integrations/               # Hermes, OpenClaw, Claude Code, Codex, provisioning
│   ├── AiSettings/                 # Dostawcy, konta, pule kont, cennik
│   ├── Users/                      # Użytkownicy, role, profile, bezpieczeństwo
│   ├── Logs/                       # Dziennik audytu, logi LLM, systemowe
│   └── Chat/                       # Czat globalny i widget pływający
├── services/                       # Dedykowane podsystemy
│   ├── llm-gateway/                # Brama modeli, pule, cooldown, routing
│   ├── memory-service/             # Integracja z Qdrant / pgvector, chunking
│   ├── integration-manager/        # Zarządca instancji, szablony systemd/docker
│   └── telemetry-collector/        # Agregator metryk i zdarzeń
├── scripts/
│   ├── install.sh                  # Instalator dockerowy
│   ├── install-debian13.sh         # Instalator natywny dla Debian 13
│   ├── update.sh                   # Aktualizator z auto-backupem
│   ├── compat-check.sh             # Kontrola kompatybilności środowiska
│   └── rollback.sh                 # Skrypt awaryjnego przywracania wersji
├── tests/
│   ├── Feature/                    # Testy integracyjne modułów
│   └── Unit/                       # Testy jednostkowe serwisów i sterowników
├── CHANGELOG.md                    # Dziennik zmian projektu
├── docker-compose.yml              # Konfiguracja środowiska kontenerowego
└── .env.example                    # Wzorcowy plik środowiskowy
```

---

## 5. Implementation Roadmap – 10 Etapów Wdrożenia

### Etap 1: Fundament (Scaffolding & Core Architecture)
- **Cel**: Działający, pusty szkielet aplikacji z logowaniem i nawigacją w układzie 7 bloków.
- **Zakres**:
  1. Inicjalizacja projektu Laravel 13 bezpośrednio w katalogu głównym `Projekt-emet` (bez tworzenia nowego podfolderu projektu), konfiguracja `docker-compose.yml` (App, PostgreSQL, Redis, Reverb, Qdrant).
  2. Implementacja autentykacji (Laravel Fortify / Breeze / własna sesyjna), obsługa RBAC (Spatie Permission).
  3. Idempotentne seedery: `AdminUserSeeder` (`admin@admin.lan` / `admin`), `RolesAndPermissionsSeeder`.
  4. Implementacja layoutu CSS Grid (Bloki 1–7): responsywność, zwijanie paska, motyw jasny/ciemny, obsługa WCAG AA.
  5. Silnik modułowy `ModuleServiceProvider` skanujący katalog `modules/`, wstrzykujący trasy, widoki oraz pozycje Menu 1 i Menu 2.
  6. Przygotowanie struktury `lang/pl` i `lang/en` dla wszystkich elementów bazowych.
- **Kryterium akceptacji (DoD)**: Możliwość zalogowania kontem domyślnym; renderuje się poprawny szkielet 7 bloków; testy logowania i ról przechodzą na zielono.

### Etap 2: Ustawienia (Users, RBAC, Audit & Initial AI Config)
- **Cel**: Pełne zarządzanie użytkownikami, role, bazowy audyt i konfiguracja dostawców AI.
- **Zakres**:
  1. Moduł `Users`: CRUD użytkowników, przypisywanie ról, zmiana hasła w profilu (opcjonalna, bez wymuszania).
  2. Moduł `Logs` (część audytowa): automatyczne rejestrowanie zdarzeń logowania, zmian konfiguracji i operacji administracyjnych.
  3. Moduł `AiSettings` (część wstępna): tabela `llm_providers` i `llm_accounts`, formularze dodawania kont z szyfrowaniem `api_key` (`encrypted`), obsługa wielu kont dla jednego dostawcy, test połączenia (`ping`/`test-connection`).
- **Kryterium akceptacji (DoD)**: Dodanie nowego użytkownika, nadanie roli; dodanie konta dostawcy AI z zaszyfrowanym kluczem; pomyślny test połączenia; log w tabeli audytu.

### Etap 3: LLM Gateway & Zarządzanie Pulami Kont
- **Cel**: Działające wywołania modeli AI z routingiem, pulami kont, obsługą błędów 429 i logowaniem wywołań.
- **Zakres**:
  1. Serwis `llm-gateway`: implementacja sterowników dostawców (Gemini, OpenAI, Anthropic, Ollama, OpenRouter).
  2. Obsługa `LlmAccountPool`: strategie `round_robin`, `weighted`, `least_used`, `priority_fallback`.
  3. Mechanizm **Dynamic Cooldown & Failover**: automatyczne wyłapywanie błędów 429, ustawianie statusu `cooldown` na koncie, automatyczne przełączenie na kolejne konto w puli.
  4. Obsługa odpowiedzi strumieniowych (Server-Sent Events / Reverb).
  5. Pomiar metryk: tokeny prompt/completion, TTFT (latencja do pierwszego tokena), czas całkowity, estymacja kosztu na podstawie `model_pricing`.
  6. Asynchroniczny zapis każdego wywołania do tabeli `llm_calls` z opcjonalnym maskowaniem treści.
- **Kryterium akceptacji (DoD)**: Wywołanie API przez pulę kont; symulacja błędu 429 skutkuje natychmiastowym przełączeniem na drugie konto; wpis w `llm_calls` z dokładnymi metrykami; testy jednostkowe sterowników i strategii.

### Etap 4: Agenci AI + Zunifikowany Czat
- **Cel**: Tworzenie agentów natywnych, konfiguracja skilli i komunikacja przez czat.
- **Zakres**:
  1. Moduł `Agents`: CRUD agentów (nazwa, system prompt, temperatura, przypisanie modelu/puli kont, przypisanie kolekcji pamięci).
  2. Obsługa skilli agenta (`agent_skills`): deklaracja narzędzi i konfiguracji.
  3. Moduł `Chat`: globalne okno rozmów (Menu 1) oraz pływający widget szybkiego dostępu (Blok 7).
  4. Strumieniowanie odpowiedzi agenta w czasie rzeczywistym z podglądem kroków myślenia i wywołań narzędzi.
  5. Rejestr uruchomień zadań (*agent runs*) i historia konwersacji (`chat_conversations`, `chat_messages`).
- **Kryterium akceptacji (DoD)**: Utworzenie agenta, przypisanie puli kont; płynna rozmowa na czacie ze strumieniowaniem tokenów; zapamiętywanie historii konwersacji.

### Etap 5: Pamięć Wektorowa (Memory Service)
- **Cel**: Centralny magazyn pamięci wektorowej, wyszukiwanie semantyczne i eksplorator w stylu Obsidian.
- **Zakres**:
  1. Serwis `memory-service` z implementacją `VectorStoreInterface` dla Qdrant oraz `pgvector`.
  2. Generowanie embeddingów przez modele wektorowe (OpenAI `text-embedding-3-small`, Ollama, FastEmbed).
  3. Mechanizm chunkingu dokumentów i notatek (podział na akapity/nagłówki z metadanymi).
  4. Moduł `Memory`: Eksplorator kolekcji, edytor notatek w Markdown, semantyczna wyszukiwarka z suwakiem progu podobieństwa (*similarity threshold*).
  5. Interaktywny graf wiedzy w stylu Obsidian (wizualizacja powiązań między fragmentami pamięci).
  6. Wstrzykiwanie odpowiednich fragmentów pamięci do kontekstu zapytań agentów (RAG).
- **Kryterium akceptacji (DoD)**: Zapis dokumentu do pamięci; wygenerowanie embeddingów; semantyczne wyszukanie fragmentu w teście; agent odpowiada na bazie wiedzy z pamięci; graf powiązań poprawnie renderowany.

### Etap 6: Integracje Runtime'ów & Automatyczny Provisioning Instancji
- **Cel**: Zarządzanie instancjami Hermes Agent i OpenClaw z poziomu panelu (mikroserwisy).
- **Zakres**:
  1. Moduł `Integrations` i serwis `integration-manager`.
  2. Implementacja `AgentRuntimeAdapter` dla Hermes Agent, OpenClaw, Claude Code i Codex (wraz z pełnymi sterownikami mock).
  3. **Automatyczny provisioning instancji (sekcja 9.5)**:
     - Formularz tworzenia instancji (wybór wersji, zasobów CPU/RAM, portu, puli kont LLM, trybu systemd/docker).
     - Zadania asynchroniczne `provisioning_jobs` ze strumieniowaniem logów z procesu tworzenia do interfejsu.
     - Szablony usług `agenthub-hermes@<slug>.service` / `agenthub-openclaw@<slug>.service` i kompozycji Docker.
     - Pętla uzgadniania stanu (**Reconcile loop**) działająca w tle co 30 s.
     - Obsługa automatycznego rollbacku przy niepowodzeniu health-checka.
  4. Cykl życia instancji: start, stop, restart, backup danych, zmiana konfiguracji.
- **Kryterium akceptacji (DoD)**: Zlecenie utworzenia instancji z UI; pomyślny przebieg zadania provisioningu; instancja uzyskuje status `healthy`; pętla reconcile poprawnie monitoruje proces; mock adapter przechodzi testy integracyjne.

### Etap 7: Telemetria & Reaktywny Dashboard
- **Cel**: Pełny monitoring platformy w czasie rzeczywistym.
- **Zakres**:
  1. Serwis `telemetry-collector`: agregacja zdarzeń agentów, wywołań LLM i metryk systemowych.
  2. Automatyczne rollupy godzinowe i dzienne w bazie danych (zapobieganie puchnięciu logów).
  3. Moduł `Dashboard`: reaktywne kafelki i wykresy Livewire (aktywni agenci, zużycie tokenów, prędkość TTFT, koszty per model/konto, stan zdrowia instancji).
  4. Emisja zdarzeń przez Laravel Reverb: aktualizacja wykresów bez przeładowywania strony.
- **Kryterium akceptacji (DoD)**: Wywołania agentów natychmiast aktualizują liczniki dashboardu; wykresy pokazują zużycie tokenów z podziałem na konta i modele; koszty są wyliczane zgodnie z tabelą cennika.

### Etap 8: Kreator Pierwszego Uruchomienia (Setup Wizard) & Instalatory
- **Cel**: Szybkie wdrożenie od zera jednym poleceniem na maszynie deweloperskiej lub serwerze.
- **Zakres**:
  1. Kreator `Setup Wizard` po pierwszym zalogowaniu: powitanie, konfiguracja bazy wektorowej, pierwsze konto AI, detekcja integracji, utworzenie pierwszego agenta, testowa rozmowa.
  2. Skrypt deweloperski `scripts/install.sh` (weryfikacja wymagań, konfiguracja `.env`, docker compose up, seedery).
  3. Natywny instalator dla Debian 13 `scripts/install-debian13.sh` (zgodnie ze specyfikacją sekcji 13.4): obsługa opcji `--db=pgsql|mysql`, `--vector=qdrant|pgvector`, konfiguracja Apache, PHP-FPM, systemd, cron i generowanie `/root/agenthub-install.txt`.
  4. Komenda diagnostyczna `php artisan agenthub:selftest`.
- **Kryterium akceptacji (DoD)**: Czyste uruchomienie `install-debian13.sh` na Debian 13 stawia działającą platformę; `php artisan agenthub:selftest` raportuje 100% sprawności; wizard prowadzi użytkownika do pierwszego zapytania.

### Etap 9: System Aktualizacji i Test Zgodności (Update & Compat)
- **Cel**: Bezpieczne, bezobsługowe aktualizacje platformy z możliwością wycofania zmian.
- **Zakres**:
  1. Narzędzie `scripts/compat-check.sh`: weryfikacja zgodności wersji PHP, rozszerzeń, bazy danych i wolnego miejsca.
  2. Skrypt `scripts/update.sh`: automatyczny backup bazy i konfiguracji, pobranie zmian z git, migracje, budowa frontendu, restart procesów queue/reverb/systemd.
  3. Skrypt awaryjny `scripts/rollback.sh`: przywrócenie ostatniej stabilnej wersji i bazy w przypadku awarii aktualizacji.
  4. Panel webowy w *Ustawienia -> System*: informacja o dostępnych aktualizacjach, uruchamianie testu zgodności.
- **Kryterium akceptacji (DoD)**: Uruchomienie `update.sh` na środowisku testowym przechodzi bezbłędnie; symulacja błędu wyzwala procedurę `rollback.sh` przywracającą stan pierwotny.

### Etap 10: Dokumentacja, Bezpieczeństwo & Wersja 1.0.0
- **Cel**: Zwieńczenie prac, audyt bezpieczeństwa, dokumentacja i przygotowanie wydania v1.0.0.
- **Zakres**:
  1. Komplet dokumentacji w katalogu `docs/`: `README.md`, `INSTALL.md`, `ARCHITECTURE.md`, `MODULES.md`, `SECURITY.md`, `integrations/hermes.md`, `integrations/openclaw.md`.
  2. Wzorcowy moduł demonstracyjny `modules/Hello/` pokazujący jak tworzyć nowe wtyczki bez modyfikacji rdzenia.
  3. Audyt bezpieczeństwa: weryfikacja szyfrowania kluczy w bazie, brak wycieku sekretów w logach, testy podatności na injection, uprawnienia procesów runnera.
  4. Pełny pakiet testów regresyjnych (Pest/PHPUnit) z raportem pokrycia kodu.
  5. Aktualizacja `CHANGELOG.md` dla wydania [1.0.0].
- **Kryterium akceptacji (DoD)**: Komplet testów świeci na zielono; PHPStan raportuje 0 błędów na level 6+; dokumentacja jest kompletna; instalator działa powtarzalnie na czystym systemie.

---

## 6. Complexity Tracking & Risk Management

| Zagadnienie / Ryzyko | Potencjalny problem | Rozwiązanie architektoniczne |
|---|---|---|
| **Dostępność API Hermes Agent i OpenClaw** | Narzędzia zewnętrzne mogą ewoluować lub mieć niekompletne API. | Ścisły interfejs `AgentRuntimeAdapter`, mock driver z możliwością symulacji wszystkich stanów, dokumentacja integracji w `docs/integrations/`. |
| **Limity API i błędy 429 u dostawców AI** | Przekroczenie limitów RPM/TPM może zablokować pracę agentów. | Moduł `llm-gateway` z pulami kont, inteligentnymi strategiami routingu, dynamicznym cooldownem i automatycznym failoverem. |
| **Qdrant na Debian 13** | Brak pakietu w oficjalnym repo Debiana. | Skrypt `install-debian13.sh` pobiera oficjalny binarny release z GitHuba, instaluje w `/usr/local/bin` i konfiguruje jako usługę systemd nasłuchującą tylko na `127.0.0.1`. Alternatywa: `--vector=pgvector`. |
| **Uprawnienia do uruchamiania instancji** | `www-data` nie może mieć dostępu do roota/dockera. | Rozdzielenie odpowiedzialności: proces webowy generuje zadanie w kolejce; demon wykonawczy `agenthub-runner` wykonuje operacje na bazie zaufanych szablonów (allowlist). |
