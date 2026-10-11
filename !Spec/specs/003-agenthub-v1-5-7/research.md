# Research & Technical Decisions: AgentHub v1.5.7

**Feature**: `003-agenthub-v1-5-7`  
**Source Specification**: [`Agenthub_v1.5.7.md`](../../Agenthub_v1.5.7.md)  
**Date**: 2026-10-11  

---

## 1. Badania i decyzje architektoniczne (Decisions & Rationales)

### D1. Naprawa błędu przekierowania do edytora scenariuszy
- **Problem**: Po wypełnieniu formularza `modules/Scenarios/resources/views/create.blade.php` i naciśnięciu „Utwórz i przejdź do edytora” użytkownik napotykał błąd aplikacji lub niepoprawny stan edytora.
- **Analiza przyczyny źródłowej**:
  1. `ScenariosController::store` tworzył slug za pomocą `Str::slug($name)` bez sprawdzania unikalności. Jeśli slug już istniał w tabeli `scenarios`, rzucany był wyjątek naruszenia unikalności bazy danych (SQL integrity constraint violation).
  2. Tworzenie `Scenario`, `ScenarioVersion` i aktualizacja `current_version_id` nie były objęte transakcją `DB::transaction()` – częściowe niepowodzenie pozostawiało niespójny rekord.
  3. Edytor Drawflow rzucał błąd JavaScript / Blade, jeśli wersja robocza nie posiadała zainicjalizowanego poprawnego schematu węzłów lub relacja `currentVersion` zwracała `null`.
- **Decyzja**:
  - Otoczenie logiki tworzenia scenariusza w `DB::transaction()`.
  - Wdrożenie bezpiecznego generatora unikalnego slugu (`$slug = $this->generateUniqueSlug($validated['name'])`).
  - Gwarancja inicjalizacji wersji początkowej z kanonicznym schematem zawierającym węzły `start` (id `n1`) oraz `end` (id `n2`) i połączeniem między nimi.
  - Defensywne ładowanie w widoku `editor.blade.php`: w przypadku braku lub uszkodzenia grafu w wersji roboczej, widok automatycznie podstawia kanoniczny szablon początkowy z logowaniem ostrzeżenia w kanale systemowym.
- **Odrzucone alternatywy**:
  - *Zezwolenie na edycję bez wstępnej wersji grafu*: Odrzucono, ponieważ silnik Drawflow wymaga węzłów wejściowych do renderowania canvasu.

---

### D2. Audyt I18n i eliminacja surowych kluczy w UI
- **Problem**: W interfejsie użytkownika pojawiały się surowe identyfikatory tłumaczeń (np. `agent.sub_skill` lub `agents.sub_skills`) oraz nieprzetłumaczone etykiety w szablonach Blade.
- **Decyzja**:
  - Zmiana manifestu `modules/Agents/module.json` i przypisanie podmenu etykiety `modules.agents.submenu.skills` oraz zdefiniowanie jej w `lang/pl/modules.php` jako „Skille” i w `lang/en/modules.php` jako „Skills”.
  - Utworzenie komendy konsolowej `php artisan lang:audit`, która skanuje szablony Blade (`resources/views` oraz `modules/**/resources/views`), manifesty `module.json` oraz pliki `lang/pl` i `lang/en`:
    - Weryfikuje brak pustych ciągów.
    - Weryfikuje obecność tłumaczenia w obu językach dla każdego wywołanego klucza.
    - Sprawdza, czy w polskiej wersji językowej wartość nie jest tożsama z surowym kluczem (wzorzec `^[a-z_]+(\.[a-z_]+)+$`).
  - Dołączenie audytu tłumaczeń do `php artisan docs:check` oraz testu `tests/Feature/I18nCompletenessTest`.
- **Odrzucone alternatywy**:
  - *Doraźne uzupełnienie pojedynczego klucza*: Odrzucono, ponieważ nie chroni to przed powrotem problemu w innych modułach.

---

### D3. Silnik harmonogramów scenariuszy (Timery)
- **Problem**: Dotychczasowe wyzwalacze scenariuszy opierały się wyłącznie na surowym wyrażeniu cron, co było nieintuicyjne dla użytkowników biznesowych i podatne na wyścigi (race conditions) w klastrze.
- **Decyzja**:
  - Rozbudowa tabeli `scenario_triggers` o 6 typów: `once`, `interval`, `daily`, `weekly`, `monthly`, `cron` wraz z ustrukturyzowanym polem `schedule` (JSON) i strefą czasową (domyślnie `Europe/Warsaw`).
  - Obliczanie `next_run_at` z wykorzystaniem `Carbon` oraz biblioteki `dragonmantank/cron-expression`.
  - Stworzenie serwisu `App\Services\Scenarios\ScenarioSchedulerService` odpowiedzialnego za kalkulację terminów i podgląd 5 najbliższych uruchomień.
  - Komenda `scenarios:dispatch-due` (uruchamiana w `schedule:run` co minutę):
    - Selekcjonuje aktywne timery z `next_run_at <= now()`.
    - Wykorzystuje atomową blokadę `Cache::lock("scenario:trigger:{$trigger->id}", 55)` zapobiegającą równoległemu odpaleniu przez wielu workerów.
    - Zapisuje unikalny klucz `idempotency_key` o formacie `trigger_{id}_{timestamp}` w tabeli `scenario_runs`.
    - Obsługuje politykę nakładania (`overlap_policy`: `skip`, `queue`, `parallel`) oraz opóźnień po awarii (`misfire_policy`).
    - W razie `consecutive_failures >= SCENARIO_TIMER_MAX_FAILURES` (5) automatycznie przestawia stan timera na `paused` i generuje zdarzenie systemowe.
- **Odrzucone alternatywy**:
  - *Generowanie zadań w kolejce z dużym delayem*: Odrzucono ze względu na brak możliwości łatwego odwołania lub dynamicznej modyfikacji timera w Redis.

---

### D4. Zarządzanie modelami LLM i zachowanie nadpisań
- **Problem**: Modele w systemie były reprezentowane częściowo jako sztywne ciągi tekstowe lub wpisy cennika. Brakowało centralnego rejestru modeli z możliwością edycji parametrów domyślnych i stawek, a automatyczna synchronizacja `listModels` mogła nadpisywać zmiany użytkownika.
- **Decyzja**:
  - Utworzenie tabeli `models` (`id`, `provider_id`, `model_key`, `display_name`, `enabled`, `capabilities`, `context_window`, `parameters`, `max_output_tokens`, `is_manual`, `overrides`, `tags`, `sort`).
  - Kolumna `overrides` (JSON) przechowuje listę nazw pól zmodyfikowanych ręcznie przez administratora. Synchronizacja z dostawcą (`listModels`) aktualizuje wyłącznie pola niewymienione w `overrides`.
  - Wprowadzenie widoku *Ustawienia AI → Modele* z opcją edycji, ręcznego dodawania modeli oraz przyciskiem „Przywróć wartości z dostawcy” (który czyści tablicę `overrides`).
  - Rozszerzenie tabeli `model_pricing` o opcjonalne pole `account_id` (cenniki per konto).
- **Odrzucone alternatywy**:
  - *Całkowity zakaz automatycznej synchronizacji po edycji*: Odrzucono, aby nowe zdolności modeli lub zmiany endpointów od dostawcy mogły być nadal aktualizowane.

---

### D5. Kaskadowy wybór modeli w formularzu agenta
- **Problem**: Użytkownik musiał wpisywać identyfikator modelu ręcznie, co prowadziło do błędów literowych i prób użycia modeli nieobsługiwanych przez wybrane konto.
- **Decyzja**:
  - Utworzenie komponentu Blade / Alpine.js `<x-model-select>`:
    - Kaskada: Runtime → Konto / Pula → Lista dostępnych modeli.
    - Wizualna prezentacja zdolności (chat, vision, tools) oraz okna kontekstu.
    - Dynamiczne pobieranie domyślnych parametrów (temperatura, max tokeny) ze zdefiniowanego modelu.
  - Reguła walidacji backendowej `ValidModelForAgent`, weryfikująca czy model istnieje w bazie, jest włączony i należy do dozwolonych modeli danego konta/puli.
- **Odrzucone alternatywy**:
  - *Pełne SPA w Livewire*: Odrzucono na rzecz lekkiego komponentu Blade + Alpine.js, spójnego z resztą architektury layoutu platformy.

---

### D6. Pełny cykl życia skilli (Edycja, Wersje i Bezpieczne Usuwanie)
- **Problem**: Skille w 1.5.0 można było jedynie dodawać. Brakowało mechanizmu edycji definicji z podbiciem SemVer oraz bezpiecznego usuwania uwzględniającego zależności.
- **Decyzja**:
  - Rozdzielenie edycji:
    - Metadane (nazwa, opis, README) -> aktualizacja bieżącego rekordu w `skills`.
    - Definicja (JSON Schema we/wy, uprawnienia `requires`, entrypoint) -> utworzenie nowego rekordu w `skill_versions` z wymaganym opisem zmian (changelog).
  - Skille integracyjne (`integration`) z obsługą `overrides`, skille systemowe modułów (`module`) z flagą `locked_by_module` (tylko do odczytu, opcja duplikacji).
  - **Wizualne oznaczanie nieużywanych skilli**:
    - Skille nieposiadające powiązań w `agent_skill` ani w grafach scenariuszy są wyróżniane w widoku listy **czerwoną flagą** (ikona 🚩, czerwony badge ostrzegawczy z tekstem „Nieużywany”).
    - **Decyzja o usunięciu pozostawiona użytkownikowi**: system nie wykonuje automatycznego kasowania nieużywanych skilli; administrator/użytkownik sam podejmuje decyzję o kliknięciu „Usuń”.
  - Usuwanie i zależności:
    - Weryfikacja powiązań w `agent_skill` oraz węzłach `skill` w scenariuszach.
    - Przy istniejących powiązaniach: blokada (HTTP 409) z listą zależności i wyborem: „Oznacz jako wycofany” (`deprecated`) lub „Odepnij od agentów i usuń”.
    - Implementacja `SoftDeletes` w modelu `Skill` z możliwością przywrócenia z kosza lub trwałego usunięcia.
    - Zachowanie czytelności historii wykonania w `skill_runs` poprzez zapis migawek `skill_name` i `skill_key`.
- **Odrzucone alternatywy**:
  - *Automatyczne czyszczenie nieużywanych skilli w tle*: Odrzucono na rzecz pełnej kontroli użytkownika (czerwona flaga informuje, ale to użytkownik decyduje).
  - *Twarde usuwanie kaskadowe (Cascade Delete)*: Odrzucono ze względu na ryzyko przerwania działających agentów i zafałszowania logów telemetrii.

---

## 2. Podsumowanie macierzy komponentów

| Obszar | Nowe / Zmienione Klasy | Migracje BD | Widoki Blade |
|---|---|---|---|
| **I18n** | `LangAuditCommand`, `I18nCompletenessTest` | Brak | Poprawki we wszystkich modułach |
| **Scenariusze (Edycja)** | `ScenariosController` (draft, restore, lock) | `scenarios` (lock metadata) | `index.blade.php`, `edit.blade.php`, `editor.blade.php` |
| **Timery** | `ScenarioSchedulerService`, `DispatchDueScenariosCommand` | `scenario_triggers`, `scenario_runs` | `scenarios/triggers.blade.php` |
| **Modele AI** | `LlmModel` (Model), `ModelsController` | `models`, `model_pricing` | `ai-settings/models.blade.php` |
| **Wybór Modeli** | `ValidModelForAgent` (Rule), `ModelSelect` | Brak | `components/model-select.blade.php` |
| **Skille** | `SkillManagerService`, `SkillsController` | `skills`, `skill_runs`, `skill_versions` | `skills/index.blade.php`, `skills/edit.blade.php` |
| **Naprawa Błędu** | `ScenariosController::store` | Brak | `scenarios/create.blade.php` |
