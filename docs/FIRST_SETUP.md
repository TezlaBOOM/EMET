# Podręcznik Pierwszej Konfiguracji AgentHub (Wydanie 1.5.0)

> Niniejszy dokument jest generowany automatycznie poleceniem `php artisan docs:first-setup --build`.
> Ostatnia aktualizacja: 2026-11-11 17:11:31

## Spis treści

- [Moduł Agenci AI](#moduł-agents)
- [Moduł Czat AI](#moduł-chat)
- [Moduł Pamięć Wektorowa](#moduł-memory)
- [Moduł Scenariusze](#moduł-scenarios)
- [Moduł Integracje](#moduł-integrations)
- [Moduł System](#moduł-system)

---

<a name="moduł-agents"></a>

## Moduł: Agenci AI

# Pierwsza Konfiguracja: Agenci AI & Skille (v1.5.7)

## Krok: Konfiguracja magazynu skilli, zdolności agentów oraz wyboru modeli AI

1. **Weryfikacja uprawnień**:
   - `skills.view` – przeglądanie listy i szczegółów skilli.
   - `skills.manage` – dodawanie nowych skilli.
   - `skills.edit` – edycja metadanych i kodu definicji skilla (v1.5.7).
   - `skills.delete` – przenoszenie skilla do kosza (soft-delete) oraz przywracanie (v1.5.7).
   - `skills.purge` – trwałe usuwanie skilla z bazy danych i magazynu (v1.5.7).

2. **Dysk dyskowy skilli**:
   Upewnij się, że katalog `storage/app/skills` posiada prawa zapisu dla procesu aplikacji (`chmod 775 storage/app/skills`).

3. **Dodawanie i import skilla**:
   Przejdź do zakładki **Agenci AI → Skille** i kliknij **Dodaj skill**.
   - Możesz przesłać paczkę archiwum `.zip` zawierającą manifest `skill.json` lub utworzyć skill manualnie.
   - Każda paczka jest automatycznie weryfikowana pod kątem sumy kontrolnej SHA-256 oraz bezpieczeństwa ścieżek (Zip-Slip).

4. **Wersjonowanie i edycja skilli (v1.5.7)**:
   - Edycja metadanych (nazwa, opis, tagi) nie inkrementuje wersji semantycznej.
   - Edycja definicji technicznej (`content`, `entrypoint`, `schema`) wymaga wskazania typu podbicia wersji SemVer (`patch`, `minor`, `major`) oraz wpisania notatki zmian (changelog).
   - Historia wersji archiwalnych jest trwale rejestrowana w tabeli `skill_versions`.

5. **Czerwona flaga 🚩 dla nieużywanych skilli & manualne usuwanie (v1.5.7)**:
   - Skille, które nie są przypisane do żadnego agenta ani nie występują w węzłach grafów scenariuszy, są w widoku listy automatycznie oznaczane czerwoną flagą (🚩 badge „Nieużywany”).
   - Zgodnie z zasadą bezpieczeństwa, system **nie usuwa automatycznie nieużywanych skilli w tle** — decyzja o usunięciu pozostaje całkowicie w rękach użytkownika.
   - Próba usunięcia skilla używanego przez aktywnych agentów lub scenariusze zostaje zablokowana z kodem HTTP 409 i czytelną listą zależności.
   - Usunięte skille trafiają do Kosza (`deleted_at`), skąd mogą zostać w każdej chwili przywrócone lub trwale usunięte przez administratora (`skills.purge`).

6. **Kaskadowy wybór modelu w konfiguracji agenta (v1.5.7)**:
   - Formularze tworzenia i edycji agenta wykorzystują komponent kaskadowy `<x-model-select>`:
     Wybór środowiska wykonawczego (Runtime: Ollama, Hermes, OpenClaw, Brama LLM) → Wybór konta/puli dostawcy → Filtrowana lista modeli.
   - Reguła walidacyjna `ValidModelForAgent` weryfikuje dostępność i stan włączenia wybranego modelu.
   - W przypadku wyłączenia lub usunięcia modelu z rejestru, powiązani agenci są w panelu oznaczani widoczną etykietą ostrzegawczą „Model niedostępny”.

7. **Podsystem Ewaluacji Jakości i Testów A/B Agentów (v1.8.0)**:
   - Przejdź do zakładki **Agenci AI → Ewaluacje i Benchmarki A/B** (`/agents/evals`).
   - Zdefiniuj zestaw testowy (*Golden Dataset*) zawierający przypadki JSON (`prompt`, `expected_keywords`, `reference_answer`) oraz próg zaliczenia (`pass_threshold`).
   - Uruchom porównanie A/B dwóch modeli (`model_a` vs `model_b`), aby ocenić ich jakość, średnią latencję oraz koszt tokenów przed wdrożeniem zmian na produkcję.

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

<a name="moduł-memory"></a>

## Moduł: Pamięć Wektorowa

---
key: memory.hybrid_rag_and_graph
title: Kolekcje Pamięci Wektorowej, Hybrydowy RAG i GraphRAG
order: 300
since_version: 1.7.0
optional: false
link: memory.collections
---

## Konfiguracja Pamięci Wektorowej i GraphRAG (v1.7.0)

1. **Kolekcje Wektorowe (`Pamięć → Kolekcje wektorowe`)**:
   - Utwórz kolekcję wiedzy wybierając silnik **Qdrant** lub **PgVector**, dostawcę embeddingów oraz wymiarowość wektora (np. `1536` lub `768`).
2. **Automatyczna Ekstrakcja Encji do Grafu Wiedzy (GraphRAG)**:
   - Podczas dodawania dokumentu Markdown system automatycznie dzieli tekst, oblicza wektory gęste oraz ekstrahuje encje techniczne, identyfikatory i ich relacje do tabel `memory_entities` i `memory_relations`.
3. **Wyszukiwanie Hybrydowe z Rerankerem (`Pamięć → Wyszukiwarka semantyczna`)**:
   - Tryb **Hybrydowy RAG (Dense + BM25 + GraphRAG + Reranker)** łączy podobieństwo kosinusowe wektorów, pełnotekstowy scoring BM25 (dla dokładnych identyfikatorów i kodów błędów) oraz ekspansję sąsiednich węzłów grafu wiedzy (1-hop).

---

<a name="moduł-scenarios"></a>

## Moduł: Scenariusze

# Pierwsza Konfiguracja: Scenariusze Wizualne (v1.5.7)

## Krok: Konfiguracja edytora blokowego, wersjonowania i harmonogramów

1. **Wymagane uprawnienia**:
   - `scenarios.view` – przeglądanie listy scenariuszy i historii runów.
   - `scenarios.manage` – tworzenie, edycja i publikowanie grafów w edytorze Drawflow, zarządzanie blokadami i harmonogramami.
   - `scenarios.run` – ręczne wyzwalanie uruchomień testowych scenariuszy.

2. **Kreator i edycja scenariuszy (v1.5.7)**:
   - Formularz tworzenia scenariusza (`/scenarios/create`) inicjalizuje atomowo wersję roboczą (draft) z początkowym grafem `start` → `end` w ramach bezpiecznej transakcji bazodanowej z automatyczną deduplikacją unikalnego slugu.
   - Edycja metadanych dostępna pod adresem `/scenarios/{id}/edit`.
   - Zmiana opublikowanego scenariusza tworzy nową wersję roboczą grafu, pozostawiając wersję opublikowaną aktywną dla produkcyjnych wywołań.
   - Publikacja nowej wersji grafu automatycznie zatwierdza poprawność schematu (węzły start, end, brak wiszących krawędzi) i podmienia wskaźnik `current_version_id`.
   - Blokada współbieżnej edycji: otwarcie edytora przez użytkownika nakłada blokadę `locked_by` i `locked_at` (ważność 15 minut z mechanizmem heartbeat). Inny użytkownik widzi ostrzeżenie z możliwością przejęcia blokady przez administratora.

3. **Edytor w osobnym oknie**:
   - Bezpośredni URL do edytora: `/scenarios/{id}/editor`.
   - Edytor obsługuje 12 typów węzłów: `start`, `agent`, `skill`, `memory`, `condition`, `parallel`, `join`, `loop`, `human`, `transform`, `delay`, `end`.
   - Zabezpieczenie `SCENARIO_MAX_NODES=200` chroni przed przeciążeniem przeglądarki i serwera.

4. **Harmonogramy i Timery (v1.5.7)**:
   - Panel konfiguracji timerów: `/scenarios/{id}/triggers`.
   - 6 typów wyzwalaczy czasowych: `once` (jednorazowo), `interval` (co zadany interwał minutowy), `daily` (codziennie o określonej godzinie), `weekly` (wybrane dni tygodnia), `monthly` (dzień miesiąca) oraz `cron` (dowolne wyrażenie 5-polowe).
   - Domyślna strefa czasowa: `Europe/Warsaw` z automatycznym uwzględnianiem czasu letniego/zimowego.
   - Podgląd kolejnych 5 uruchomień wyliczany dynamicznie w interfejsie.
   - Cykliczny proces konsolowy: `php artisan scenarios:dispatch-due` (rejestrowany co minutę w harmonogramie Laravel `routes/console.php`).
   - Ochrona przed współbieżnością: atomowa blokada `Cache::lock('scenarios:dispatch-due', 55)` zapobiega równoległym wywołaniom z wielu workerów.
   - Klucz idempotentności: każde utworzone wykonanie `scenario_runs` posiada unikalny `idempotency_key`.
   - Auto-pauza: po 5 kolejnych nieudanych uruchomieniach wyzwalacz zostaje automatycznie zatrzymany (`state = error`) w celu ochrony zasobów, a w UI prezentowany jest szczegółowy komunikat błędu.

5. **Debugger Scenariuszy „Time-Travel” i Forking Stanu (v1.8.0)**:
   - Podczas każdego uruchomienia silnik `ScenarioEngine` zapisuje pełną migawkę zmiennych kontekstowych przed (`state_snapshot_before`) i po (`state_snapshot_after`) każdym węźle oraz czas wykonania w milisekundach (`duration_ms`).
   - Endpoint `GET /scenarios/runs/{run}/timeline` udostępnia pełną oś czasu wykonania wraz ze skorelowanym identyfikatorem `trace_id` OpenTelemetry.
   - Endpoint `POST /scenarios/runs/{run}/fork` (`from_node_id`, `override_vars`) pozwala rozwidlić dowolny historyczny lub przerwany przebieg od wskazanego węzła bez ponownego wykonywania wcześniejszych kroków.

---

<a name="moduł-integrations"></a>

## Moduł: Integracje

# Pierwsza Konfiguracja: Integracje i Kontenery Docker (v1.5.0)

## Krok: Konfiguracja adapterów integracji i kontenerów Docker

1. **Wymagane uprawnienia**:
   - `integrations.manage` – wykrywanie, adopcja i konfiguracja kontenerów Docker oraz instancji zewnętrznych.

2. **Dostęp do Docker Socket i tryb Mock**:
   - AgentHub komunikuje się z demonem Docker przez dedykowany socket: `unix:///var/run/docker.sock` (konfiguracja: `DOCKER_SOCKET_PATH`).
   - W środowiskach deweloperskich lub bez demona Docker dostępny jest aktywny sterownik mock (`DOCKER_MOCK=true` lub `CONTAINERS_MOCK=true`), symulujący obecność kontenerów runtime'ów (Ollama, Hermes, OpenClaw).
   - W razie problemów ze środowiskiem lub socketem, automatyczną konfigurację wykonuje skrypt: `./scripts/repairkit.sh`.
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

