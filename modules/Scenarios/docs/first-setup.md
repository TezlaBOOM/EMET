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
