# Implementation Tasks: AgentHub Platform v1.5.7 (Aktualizacja 1.5.7)

**Feature**: `003-agenthub-v1-5-7`  
**Plan**: [`plan.md`](./plan.md) | **Spec**: [`spec.md`](./spec.md) | **Constitution**: [`.specify/memory/constitution.md`](../../.specify/memory/constitution.md)  

---

## Przegląd etapów implementacji (Etapy 18–25)

| Etap | Nazwa etapu | Zakres zadań | Rezultat |
|:---:|---|---|---|
| **Etap 18** | Naprawa błędu kreatora scenariuszy | T133 – T137 | Płynne tworzenie scenariusza, brak błędu 500, unikalny slug, defensywny edytor Drawflow |
| **Etap 19** | Spolszczenia & audyt I18n | T138 – T143 | Etykieta „Skille”, komenda `lang:audit`, 0 surowych kluczy w UI, test integralności |
| **Etap 20** | Rejestr i edycja modeli AI | T144 – T150 | Tabela `models`, widok *Ustawienia AI → Modele*, edycja parametrów i cenników, ochrona `overrides` |
| **Etap 21** | Kaskadowy wybór modelu w agencie | T151 – T155 | Komponent `<x-model-select>`, reguła `ValidModelForAgent`, ostrzeżenia o modelach nieaktywnych |
| **Etap 22** | Cykl życia skilli (Edycja, Czerwona flaga & Usuwanie) | T156 – T162 | SemVer definicji, flaga 🚩 dla nieużywanych, manualne usuwanie, soft-delete i blokada zależności |
| **Etap 23** | Edycja scenariuszy & wersjonowanie grafu | T163 – T167 | Edycja metadanych, szkice robocze, przywracanie wersji i blokada współbieżnej edycji |
| **Etap 24** | Timery i harmonogramy scenariuszy | T168 – T173 | 6 typów harmonogramów, strefa `Europe/Warsaw`, `scenarios:dispatch-due`, blokada atomowa i auto-pauza |
| **Etap 25** | Stabilizacja, audyt & wydanie v1.5.7 | T174 – T177 | `agenthub:selftest`, aktualizacja `docs/`, podbicie do `1.5.7` (`VERSION` i `config`), `CHANGELOG.md` |

---

## Etap 18: Naprawa błędu kreatora scenariuszy

- [X] **T133** Test regresyjny: utworzenie `tests/Feature/Scenarios/ScenarioCreateAndEditTest.php` odtwarzającego błąd 500 / kolizję slugu i brak wersji roboczej przy tworzeniu scenariusza.
- [X] **T134** Przepisanie metody `store` w `app/Http/Controllers/Scenarios/ScenariosController.php`: otoczenie transakcją `DB::transaction()`, bezpieczne generowanie unikalnego slugu, atomowa inicjalizacja wersji roboczej z grafem `start` → `end`.
- [X] **T135** Defensywne ładowanie grafu w `modules/Scenarios/resources/views/editor.blade.php`: obsługa braku lub uszkodzenia schematu węzłów bez wywoływania błędów JS/Blade.
- [X] **T136** Spolszczenie formularza tworzenia scenariusza w `modules/Scenarios/resources/views/create.blade.php`: usunięcie hardkodowanych napisów i powiązanie z `lang/pl/scenarios.php`.
- [X] **T137** Weryfikacja testu regresyjnego `ScenarioCreateAndEditTest` i potwierdzenie zielonego przejścia tworzenia scenariusza.

---

## Etap 19: Spolszczenia & audyt I18n

- [X] **T138** Aktualizacja manifestu `modules/Agents/module.json`: zamiana etykiety podmenu z `agents.sub_skills` na `modules.agents.submenu.skills`.
- [X] **T139** Dodanie tłumaczeń `modules.agents.submenu.skills` w `lang/pl/modules.php` („Skille”) i `lang/en/modules.php` („Skills”).
- [X] **T140** Implementacja komendy konsolowej `App\Console\Commands\LangAuditCommand` (`php artisan lang:audit`) weryfikującej kompletność plików językowych i wykrywającej surowe klucze.
- [X] **T141** Audyt i uzupełnienie brakujących kluczy tłumaczeń w `lang/pl/` i `lang/en/` dla menu, tabel, nagłówków, formularzy i komunikatów.
- [X] **T142** Implementacja testu integralności tłumaczeń `tests/Feature/I18nCompletenessTest.php`.
- [X] **T143** Integracja audytu `lang:audit` z weryfikatorem `php artisan docs:check` w `app/Console/Commands/DocsCheckCommand.php`.

---

## Etap 20: Rejestr i edycja modeli AI

- [X] **T144** Migracja bazy danych: utworzenie tabeli `models` (provider_id, model_key, display_name, enabled, capabilities, context_window, parameters, max_output_tokens, is_manual, overrides, tags, sort).
- [X] **T145** Migracja bazy danych rozszerzająca `model_pricing` o kolumnę `account_id` (cennik per konto).
- [X] **T146** Utworzenie modelu Eloquent `App\Models\LlmModel` z relacjami, rzutowaniami JSON (`capabilities`, `parameters`, `overrides`, `tags`) i metodami pomocniczymi.
- [X] **T147** Implementacja serwisu `App\Services\LlmGateway\ModelManagementService` realizującego `ModelManagementInterface` z zachowywaniem nadpisań `overrides` podczas synchronizacji z dostawcą.
- [X] **T148** Utworzenie kontrolera `App\Http\Controllers\AiSettings\ModelsController` oraz tras w `modules/AiSettings/routes/web.php`.
- [X] **T149** Implementacja widoku *Ustawienia AI → Modele* (`modules/AiSettings/resources/views/models.blade.php`) z tabelą modeli, panelem edycji parametrów i cenników oraz ręcznym dodawaniem modelu.
- [X] **T150** Testy integracyjne zarządzania modelami: `tests/Feature/AiSettings/ModelManagementTest.php`.

---

## Etap 21: Kaskadowy wybór modelu w agencie

- [X] **T151** Implementacja reguły walidacyjnej `App\Rules\ValidModelForAgent` sprawdzającej poprawność, stan włączenia i powiązanie modelu z kontem/pulą.
- [X] **T152** Utworzenie komponentu Blade / Alpine.js `resources/views/components/model-select.blade.php` z kaskadą: Runtime → Konto/Pula → Model oraz prezentacją zdolności.
- [X] **T153** Integracja komponentu `<x-model-select>` w formularzu tworzenia (`modules/Agents/resources/views/create.blade.php`) i edycji agenta.
- [X] **T154** Aktualizacja `AgentsController` o regułę `ValidModelForAgent` oraz oznaczanie agentów z nieaktywnymi modelami etykietą ostrzegawczą („Model niedostępny”).
- [X] **T155** Testy walidacji modelu agenta: `tests/Feature/Agents/AgentModelValidationTest.php`.

---

## Etap 22: Cykl życia skilli (Edycja, Czerwona flaga & Usuwanie)

- [X] **T156** Migracje bazy danych dla skilli: dodanie `deleted_at`, `deleted_by`, `overrides`, `locked_by_module` do tabeli `skills`, migawki w `skill_runs`, `deleted_at` w `skill_versions`.
- [X] **T157** Nowe uprawnienia w `RolesAndPermissionsSeeder`: `skills.edit`, `skills.delete`, `skills.purge`.
- [X] **T158** Implementacja serwisu `App\Services\Skills\SkillLifecycleService` realizującego `SkillLifecycleInterface` z metodami `isUnused()`, `checkDependencies()`, `safeDelete()`, `restoreFromTrash()`, `purgePermanently()`.
- [X] **T159** Aktualizacja widoku listy skilli `modules/Agents/resources/views/skills/index.blade.php`: oznaczenie nieużywanych skilli **czerwoną flagą** (🚩 badge „Nieużywany”), brak automatycznego usuwania (decyzja użytkownika), filtr „Kosz”.
- [X] **T160** Implementacja widoku edycji skilla `modules/Agents/resources/views/skills/edit.blade.php` z podziałem na metadane i definicję (SemVer + changelog).
- [X] **T161** Endpointy w `SkillsController`: edycja z wersją, soft-delete z blokadą HTTP 409 i listą powiązań, przywracanie i trwałe usuwanie (`skills.purge`).
- [X] **T162** Testy cyklu życia skilli, flagowania i zależności: `tests/Feature/Skills/SkillLifecycleTest.php`.

---

## Etap 23: Edycja scenariuszy & wersjonowanie grafu

- [X] **T163** Migracja bazy danych dodająca kolumny blokady współbieżnej edycji `locked_by`, `locked_at` do tabeli `scenarios`.
- [X] **T164** Utworzenie widoku edycji metadanych scenariusza `modules/Scenarios/resources/views/edit.blade.php`.
- [X] **T165** Implementacja wersjonowania szkiców w `ScenariosController`: edycja opublikowanego scenariusza tworzy/otwiera szkic; publikacja weryfikuje graf i atomowo podmienia wersję.
- [X] **T166** Mechanizm blokady współbieżnej edycji w `editor.blade.php`: ostrzeżenie o edytującym użytkowniku i opcja przejęcia blokady dla admina; akcja przywracania wersji archiwalnej (`restore`).
- [X] **T167** Testy edycji scenariuszy i wersjonowania: `tests/Feature/Scenarios/ScenarioEditAndVersioningTest.php`.

---

## Etap 24: Timery i harmonogramy scenariuszy

- [X] **T168** Migracje bazy danych: rozszerzenie `scenario_triggers` o 6 typów harmonogramu (`once`, `interval`, `daily`, `weekly`, `monthly`, `cron`) i metadane; unikalny `idempotency_key` w `scenario_runs`.
- [X] **T169** Implementacja `App\Services\Scenarios\ScenarioSchedulerService` realizującego `ScenarioSchedulerInterface`: kalkulacja terminów w `Europe/Warsaw`, podgląd 5 terminów.
- [X] **T170** Implementacja komendy `App\Console\Commands\DispatchDueScenariosCommand` (`php artisan scenarios:dispatch-due`) z blokadą atomową `Cache::lock`, unikalnym kluczem idempotentności i auto-pauzą po 5 awariach.
- [X] **T171** Rejestracja komendy `scenarios:dispatch-due` w schedulerze konsolowym w `routes/console.php`.
- [X] **T172** Widok konfiguracji timerów w scenariuszu `modules/Scenarios/resources/views/triggers/index.blade.php` z kalkulatorem 5 najbliższych terminów i przełącznikiem stanu.
- [X] **T173** Testy jednostkowe i integracyjne silnika harmonogramu: `tests/Feature/Scenarios/ScenarioSchedulerTest.php`.

---

## Etap 25: Stabilizacja, audyt & wydanie v1.5.7

- [X] **T174** Rozszerzenie diagnostyki `php artisan agenthub:selftest` oraz `compat-check.sh` o testy nowych modułów i weryfikację działania schedulera.
- [X] **T175** Aktualizacja dokumentacji technicznej: `docs/ARCHITECTURE.md`, `docs/UPDATE.md` oraz instrukcji `modules/*/docs/first-setup.md`.
- [X] **T176** Zaktualizowanie numeru wersji projektu do `1.5.7` w pliku `VERSION` oraz `config/agenthub.php` i rejestracja sekcji `[1.5.7]` w `CHANGELOG.md`.
- [X] **T177** Pełna weryfikacja jakościowa: formatowanie Pint, testy spójności `php artisan docs:check` i uruchomienie kompletnego zestawu testów `php artisan test`.
