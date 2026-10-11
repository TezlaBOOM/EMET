# Implementation Plan: AgentHub Platform v1.5.7 (Aktualizacja 1.5.7)

**Branch**: `003-agenthub-v1-5-7` | **Date**: 2026-10-11 | **Spec**: [`spec.md`](./spec.md)  
**Input**: Specyfikacja aktualizacji [`Agenthub_v1.5.7.md`](../../Agenthub_v1.5.7.md) rozwijająca [`Agenthub_v1.5.md`](../../Agenthub_v1.5.md) oraz [`AGENT_HUB_SPEC_1.md`](../../AGENT_HUB_SPEC_1.md)  

---

## 1. Summary

Celem wdrożenia jest implementacja wydania **1.5.7** platformy **AgentHub**. Jest to wydanie stabilizacyjno-rozwojowe po wersji 1.5.0, skupiające się na:
1. **Naprawie krytycznego błędu kreatora scenariuszy**: Usunięcie błędu unikalności slugu i braku transakcji podczas akcji „Utwórz i przejdź do edytora”, wraz z defensywnym ładowaniem edytora Drawflow.
2. **Pełnej spójności językowej i audycie I18n**: Usunięcie surowego klucza `agent.sub_skill` (zmiana na „Skille”/„Skills”), audyt całej aplikacji komendą `php artisan lang:audit` oraz testem `I18nCompletenessTest`.
3. **Zarządzaniu modelami LLM**: Utworzenie tabeli `models`, widoku *Ustawienia AI → Modele*, edycji parametrów domyślnych i cenników oraz mechanizmu ochrony ręcznych nadpisań (`models.overrides`) przed resetem podczas synchronizacji z dostawcą.
4. **Kaskadowym wyborze modelu w agencie**: Zastąpienie wolnego tekstu komponentem `<x-model-select>` z dynamicznym filtrowaniem (Runtime → Konto/Pula → Model) i walidacją serwerową `ValidModelForAgent`.
5. **Cyklu życia i bezpiecznym usuwaniu skilli**: Edycja metadanych vs definicji (z nową wersją SemVer i changelogiem), oznaczanie nieużywanych skilli czerwoną flagą (🚩) z pozostawieniem usunięcia wyłącznie użytkownikowi, soft delete (`deleted_at`), blokada usuwania używanych skilli (z listą powiązanych agentów i scenariuszy) oraz migawki w `skill_runs`.
6. **Pełnej edycji scenariuszy**: Edycja metadanych i ustawień, edycja schematu na wersji roboczej (szkicu), przywracanie wersji archiwalnych oraz blokada współbieżnej edycji z opcją przejęcia dla administratora.
7. **Przyjaznych harmonogramach (Timerach)**: Rozszerzenie tabeli `scenario_triggers` o 6 typów (`once`, `interval`, `daily`, `weekly`, `monthly`, `cron`), kalkulacja terminów w strefie `Europe/Warsaw`, silnik `scenarios:dispatch-due` z blokadą atomową `Cache::lock`, kluczem idempotentności `(trigger_id, scheduled_for)` i auto-pauzą po 5 awariach.
8. **Finalizacji wydania i synchronizacji wersji w dashboardzie**: Zaktualizowanie numeru wersji projektu do `1.5.7` w pliku `VERSION` oraz `config/agenthub.php`, wpis w `CHANGELOG.md`, aktualizacja dokumentacji i weryfikator `php artisan docs:check`.

Prace realizowane są w 7 logicznych etapach (Etapy 18–24) kontynuujących wdrożenie 1.5.0, zgodnie z [Konstytucją Projektu](../../.specify/memory/constitution.md) oraz regułami dokumentacyjnymi v1.5 §0.

---

## 2. Technical Context

- **Język i środowisko wykonawcze**: PHP 8.3 / PHP 8.4
- **Framework backendowy**: Laravel 11.x / 12.x / 13.x
- **Warstwa frontendowa**: Laravel Blade + Alpine.js + Tailwind CSS (zgodnie z 7-blokowym layoutem UI)
- **Silnik schematów blokowych**: Drawflow (integracja w komponencie Alpine.js)
- **Harmonogram i zadania w tle**: Laravel Scheduler (`schedule:run`) + Laravel Horizon / Redis
- **Kalkulacja terminów czasowych**: `Carbon` (strefa `Europe/Warsaw`) + `dragonmantank/cron-expression`
- **Baza danych**: MariaDB 10.11+ / PostgreSQL 16 (oraz SQLite w testach)
- **Formatowanie i analiza kodu**: PSR-12, Laravel Pint, PHPStan
- **Testowanie**: Pest / PHPUnit (testy jednostkowe, integracyjne i regresyjne)
- **Narzędzia walidacji**: `php artisan lang:audit`, `php artisan docs:check`

---

## 3. Constitution Check

*GATE: Weryfikacja zgodności z Konstytucją Projektu.*

| Reguła konstytucyjna | Status | Mechanizm wdrożenia w planie 1.5.7 |
|---|:---:|---|
| **I. Praca etapowa (Etapy 18–24)** | PASS | Plan podzielony na 7 ściśle testowalnych etapów (A–F) z jasnymi kryteriami DoD. |
| **II. Kontrakty & brak zgadywania API** | PASS | Nowe kontrakty: `ScenarioSchedulerInterface`, `ModelManagementInterface`, `SkillLifecycleInterface`. |
| **III. Testy & jakość kodu** | PASS | Każda zmiana poparta testem (Pest/PHPUnit). Błąd scenariusza naprawiany od testu regresyjnego. |
| **IV. Bezpieczeństwo i poufność** | PASS | Zero sekretów w logach, ochrona przed Zip-Slip w paczkach skilli, uprawnienia RBAC (`skills.purge`, `scenarios.schedule`). |
| **V. Modularność bez modyfikacji rdzenia** | PASS | Zmiany realizowane w ramach istniejących modułów `Scenarios`, `Agents`, `AiSettings` i `System`. |
| **VI. 100% lokalizacji (I18n)** | PASS | Dedykowany etap spolszczeń, komenda `lang:audit`, zakaz surowych kluczy i hardkodowania tekstów. |
| **VII. Reguła dokumentacyjna (v1.5 §0)** | PASS | Każdy PR aktualizuje `CHANGELOG.md`, `first-setup.md`, sekcje `ConfigSection` oraz wersję w dashboardzie (`VERSION` i `config/agenthub.php`). `docs:check` egzekwowane w CI. |

---

## 4. Project Structure

### Dokumentacja (SpecKit)
```text
!Spec/specs/003-agenthub-v1-5-7/
├── spec.md                          # Specyfikacja wymagań biznesowych i technicznych
├── plan.md                          # Niniejszy plan wdrożenia
├── research.md                      # Wyniki badań architektonicznych i podjęte decyzje
├── data-model.md                    # Model danych, zmiany w tabelach i maszyny stanów
├── quickstart.md                    # Przewodnik weryfikacji krok po kroku
├── contracts/                       # Nowe interfejsy PHP
│   ├── ScenarioSchedulerInterface.php
│   ├── ModelManagementInterface.php
│   └── SkillLifecycleInterface.php
├── checklists/
│   └── requirements.md              # Lista kontrolna jakości specyfikacji
└── tasks.md                         # Zadania implementacyjne (generowane przez /speckit-tasks)
```

### Kod źródłowy w repozytorium
```text
app/
├── Console/Commands/
│   ├── LangAuditCommand.php         # php artisan lang:audit
│   ├── DispatchDueScenariosCommand.php # php artisan scenarios:dispatch-due
│   └── PurgeSkillsTrashCommand.php  # php artisan skills:purge-trash
├── Contracts/
│   ├── Scenarios/ScenarioSchedulerInterface.php
│   ├── Llm/ModelManagementInterface.php
│   └── Skills/SkillLifecycleInterface.php
├── Http/Controllers/
│   ├── Scenarios/ScenariosController.php  # Naprawa store, edycja, wersje, lock
│   ├── Scenarios/ScenarioTriggersController.php # Zarządzanie timerami
│   ├── AiSettings/ModelsController.php    # Zarządzanie modelami LLM
│   ├── Agents/SkillsController.php        # Edycja, wersje i soft-delete skilli
│   └── Agents/AgentsController.php        # Kaskadowy wybór modelu
├── Models/
│   ├── LlmModel.php                 # Centralny model dla tabeli models
│   ├── ScenarioTrigger.php          # Rozszerzenie o typy harmonogramu
│   ├── Scenario.php                 # Blokady współbieżnej edycji
│   ├── Skill.php                    # SoftDeletes, overrides, relacje
│   └── SkillRun.php                 # Migawki skill_name, skill_key
├── Rules/
│   └── ValidModelForAgent.php       # Walidacja kaskadowego wyboru modelu
└── Services/
    ├── Scenarios/ScenarioSchedulerService.php
    ├── LlmGateway/ModelManagementService.php
    └── Skills/SkillLifecycleService.php

modules/
├── Scenarios/
│   ├── resources/views/
│   │   ├── create.blade.php         # i18n + obsługa formularza
│   │   ├── edit.blade.php           # Nowy widok edycji metadanych scenariusza
│   │   ├── editor.blade.php         # Defensywne ładowanie + lock bar
│   │   └── triggers/index.blade.php # Tabela timerów i kreator harmonogramu
│   └── routes/web.php
├── AiSettings/
│   ├── resources/views/models.blade.php # Tabela modeli, edycja, cenniki
│   └── routes/web.php
└── Agents/
    ├── resources/views/
    │   ├── create.blade.php         # Komponent <x-model-select>
    │   ├── edit.blade.php           # Komponent <x-model-select>
    │   └── skills/                  # Edycja wersji, kosz, modal zależności
    └── module.json                  # Poprawka klucza modules.agents.submenu.skills

resources/views/components/
└── model-select.blade.php           # Zunifikowany kaskadowy selektor modeli

lang/
├── pl/                              # Spolszczenia wszystkich kluczy i modułów
└── en/                              # Tłumaczenia angielskie
```

---

## 5. Complexity Tracking

Brak odstępstw od Konstytucji Projektu. Architektura bazuje na istniejącym systemie modułów, mechanizmie kolejkowym Laravela oraz standardzie PSR-12.

---

## 6. Szczegółowy harmonogram etapów (Roadmap)

### Etap 18 (Etap A): Naprawa błędu kreatora scenariuszy
- **Cel**: Wyeliminowanie błędu HTTP 500 po naciśnięciu „Utwórz i przejdź do edytora” oraz zapewnienie odporności edytora na uszkodzone grafy.
- **Kroki**:
  1. Napisanie testu regresyjnego `ScenarioCreateAndEditTest` odtwarzającego błąd kolizji slugu i braku wersji roboczej.
  2. Przepisanie `ScenariosController::store`: transakcja atomowa `DB::transaction()`, bezpieczne generowanie unikalnego slugu, inicjalizacja wersji ze schematem węzłów `start` i `end`.
  3. Zabezpieczenie `editor.blade.php` przed brakiem węzłów grafu (defensywny fallback).
  4. Dodanie tłumaczeń i usunięcie zahardkodowanych napisów w `modules/Scenarios/resources/views/create.blade.php`.
- **DoD**: Test regresyjny przechodzi na zielono; nowe scenariusze tworzą się poprawnie i otwierają edytor w przeglądarce bez błędów.

### Etap 19 (Etap B): Spolszczenia i narzędzie audytu I18n
- **Cel**: Wyeliminowanie surowych kluczy w całym UI, zmiana etykiety na „Skille” oraz automatyczny strażnik kompletności językowej.
- **Kroki**:
  1. Aktualizacja `modules/Agents/module.json`: zamiana surowego identyfikatora na `modules.agents.submenu.skills` oraz zdefiniowanie w `lang/pl` i `lang/en`.
  2. Implementacja komendy `php artisan lang:audit`, wykrywającej brakujące klucze, puste wartości i surowe identyfikatory we wszystkich szablonach i manifestach.
  3. Audyt i uzupełnienie brakujących tłumaczeń w Menu 1, Menu 2, nagłówkach, powiadomieniach i formularzach.
  4. Utworzenie testu automatycznego `tests/Feature/I18nCompletenessTest` i wpięcie audytu do `php artisan docs:check`.
- **DoD**: Komenda `lang:audit` i test `I18nCompletenessTest` zwracają 0 błędów; interfejs jest w 100% spójny językowo.

### Etap 20 (Etap C): Centralny rejestr i edycja modeli AI
- **Cel**: Umożliwienie pełnej konfiguracji parametrów modeli, stawek cennika i zachowania ręcznych modyfikacji.
- **Kroki**:
  1. Migracja bazodanowa: utworzenie tabeli `models` oraz rozszerzenie `model_pricing` o kolumnę `account_id`.
  2. Utworzenie modelu `App\Models\LlmModel` z relacjami, rzutowaniami JSON i metodami pomocniczymi.
  3. Implementacja kontraktu `ModelManagementInterface` w `ModelManagementService`: obsługa pola `overrides` chroniącego ręczne zmiany przed nadpisaniem przez `listModels`.
  4. Utworzenie widoku *Ustawienia AI → Modele* (`models.blade.php`) z edycją parametrów, cenników oraz przyciskiem ręcznego dodawania modeli.
  5. Testy jednostkowe i integracyjne: `tests/Feature/AiSettings/ModelManagementTest.php`.
- **DoD**: Modele są w pełni edytowalne w UI; synchronizacja z dostawcą nie nadpisuje pól zdefiniowanych przez użytkownika.

### Etap 21 (Etap C2): Kaskadowy wybór modelu w formularzu agenta
- **Cel**: Wyeliminowanie wpisywania modeli z palca i zapewnienie pełnej spójności konfiguracji agenta.
- **Kroki**:
  1. Implementacja zunifikowanego komponentu `<x-model-select>` w Blade + Alpine.js z podziałem na dostawców, filtrowaniem kaskadowym (Runtime → Konto/Pula → Model) i prezentacją zdolności.
  2. Implementacja reguły walidacyjnej `ValidModelForAgent` sprawdzającej istnienie, stan włączenia i powiązanie z kontem/pulą.
  3. Integracja komponentu w formularzach tworzenia i edycji agenta, szablonach agentów oraz w węźle `agent` scenariusza.
  4. Oznaczenie istniejących agentów z nieistniejącymi modelami etykietą ostrzegawczą („Model niedostępny”) bez ich automatycznego kasowania.
  5. Testy walidacji: `tests/Feature/Agents/AgentModelValidationTest.php`.
- **DoD**: Nie można zapisać agenta z błędnym/nieistniejącym modelem; wybór w UI jest intuicyjny i kaskadowy.

### Etap 22 (Etap D): Pełne zarządzanie skillami (edycja, wersjonowanie i usuwanie)
- **Cel**: Bezpieczny cykl życia skilli: wersjonowanie definicji, zachowanie nadpisań skilli integracyjnych, oznaczanie nieużywanych skilli czerwoną flagą (🚩) z pozostawieniem usunięcia użytkownikowi, soft-delety i ochrona przed usunięciem używanych skilli.
- **Kroki**:
  1. Migracje bazy danych: dodanie `deleted_at`, `deleted_by`, `overrides`, `locked_by_module` do tabeli `skills`, migawki w `skill_runs`, `deleted_at` w `skill_versions`.
  2. Nowe uprawnienia w `RolesAndPermissionsSeeder`: `skills.edit`, `skills.delete`, `skills.purge`.
  3. Implementacja serwisu `SkillLifecycleService` realizującego kontrakt `SkillLifecycleInterface` (w tym metody `isUsed()` i `getUsageSummary()`).
  4. Widoki listy i edycji skilla (`modules/Agents/resources/views/skills/`):
     - Wyraźne wyróżnienie nieużywanych skilli (brak agentów i scenariuszy) **czerwoną flagą** (ikona 🚩, badge `text-rose-600 bg-rose-50 border-rose-200` z opisem „Nieużywany”).
     - Brak automatycznego czyszczenia nieużywanych skilli – system pozostawia decyzję o usunięciu wyłącznie użytkownikowi.
     - Edycja z podziałem na metadane (bez zmiany wersji) i definicję (nowa wersja SemVer + changelog).
  5. Obsługa usuwania: weryfikacja powiązań z agentami i scenariuszami; blokada HTTP 409 z listą powiązań przy próbie usunięcia używanego skilla; filtr „Kosz” z akcją przywracania i trwałego usunięcia.
  6. Testy cyklu życia i flagowania: `tests/Feature/Skills/SkillLifecycleTest.php`.
- **DoD**: Nieużywane skille są oznaczone na liście czerwoną flagą; system nie usuwa ich automatycznie; usunięcie inicjowane jest manualnie przez użytkownika; skill powiązany z agentem nie może zostać przypadkowo skasowany.

### Etap 23 (Etap E): Edycja scenariuszy i wersjonowanie grafu
- **Cel**: Pełna obsługa edycji danych scenariusza, bezpieczna edycja schematu na wersji roboczej i blokada współbieżności.
- **Kroki**:
  1. Migracja tabeli `scenarios`: dodanie `locked_by` i `locked_at`.
  2. Nowe widoki edycji metadanych scenariusza (`modules/Scenarios/resources/views/edit.blade.php`).
  3. Logika wersjonowania w `ScenariosController`: edycja opublikowanego scenariusza tworzy lub otwiera szkic (draft); publikacja weryfikuje graf i atomowo podmienia aktywną wersję.
  4. Mechanizm blokady współbieżnej edycji: wyświetlanie bannera z informacją o edytującym użytkowniku oraz przyciskiem przejęcia dla administratora.
  5. Opcja przywrócenia wcześniejszej wersji (`POST /api/v1/scenarios/{id}/versions/{version}/restore`).
  6. Testy edycji scenariuszy: `tests/Feature/Scenarios/ScenarioEditAndVersioningTest.php`.
- **DoD**: Edycja scenariusza nie zakłóca trwających procesów; wersje robocze są izolowane; blokada edycji działa bezbłędnie.

### Etap 24 (Etap F): Timery i elastyczne harmonogramy scenariuszy
- **Cel**: Umożliwienie definiowania wielu przyjaznych timerów per scenariusz z gwarancją punktualności i braku podwójnych odpaleń.
- **Kroki**:
  1. Migracje bazodanowe: rozszerzenie `scenario_triggers` o 6 typów harmonogramu i metadane wykonania; rozszerzenie `scenario_runs` o unikalny indeks `idempotency_key`.
  2. Implementacja `ScenarioSchedulerService` realizującego `ScenarioSchedulerInterface`: kalkulacja terminów w `Europe/Warsaw`, podgląd 5 najbliższych terminów.
  3. Komenda `php artisan scenarios:dispatch-due`: dyspozycja zadań, blokada atomowa `Cache::lock`, unikalny klucz idempotentności, obsługa polityk nakładania i auto-pauza po 5 awariach (`SCENARIO_TIMER_MAX_FAILURES`).
  4. Wpięcie komendy do schedulera w `routes/console.php` (`$schedule->command('scenarios:dispatch-due')->everyMinute()`).
  5. Widok konfiguracji timerów w scenariuszu (`triggers/index.blade.php`) z kalkulatorem najbliższych terminów na żywo.
  6. Testy harmonogramu: `tests/Feature/Scenarios/ScenarioSchedulerTest.php`.
- **DoD**: Timery odpalają się zgodnie z harmonogramem bez podwójnych uruchomień; auto-pauza chroni system przed lawiną błędów.

### Etap 25 (Etap G): Stabilizacja, audyt i wydanie v1.5.7
- **Cel**: Zwieńczenie prac, aktualizacja dokumentacji, weryfikacja instalatorów i oficjalne podbicie wersji w dashboardzie.
- **Kroki**:
  1. Rozszerzenie `agenthub:selftest` o testy nowych funkcji (utworzenie scenariusza, walidacja wyboru modelu, timer mock).
  2. Aktualizacja skryptu `compat-check.sh` o weryfikację rozszerzeń i schedulera.
  3. Aktualizacja dokumentacji: `docs/ARCHITECTURE.md`, `docs/UPDATE.md`, instrukcja pierwszej konfiguracji w `modules/*/docs/first-setup.md`.
  4. Zaktualizowanie numeru wersji projektu do `1.5.7` w pliku `VERSION` oraz `config/agenthub.php`.
  5. Uzupełnienie `CHANGELOG.md` o pełną sekcję wydania `[1.5.7]`.
  6. Uruchomienie `php artisan docs:check` oraz pełnego pakietu testów `php artisan test`.
- **DoD**: Dashboard w lewym panelu wyświetla `v1.5.7`; `docs:check` i 100% testów przechodzi pomyślnie.
