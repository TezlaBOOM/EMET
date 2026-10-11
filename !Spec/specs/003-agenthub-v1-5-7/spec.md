# Feature Specification: AgentHub Platform v1.5.7 (Aktualizacja 1.5.7)

**Feature Name**: `003-agenthub-v1-5-7`  
**Source Specification**: [`Agenthub_v1.5.7.md`](../../Agenthub_v1.5.7.md) (uzupełnienie [`Agenthub_v1.5.md`](../../Agenthub_v1.5.md) oraz [`AGENT_HUB_SPEC_1.md`](../../AGENT_HUB_SPEC_1.md))  
**Status**: Ready for Planning & Implementation  

---

## 1. Cel i zakres biznesowy

Wydanie **1.5.7** platformy **AgentHub** to wydanie stabilizacyjno-rozwojowe po wersji 1.5.0, skupione na spójności interfejsu (i18n), pełnym cyklu życia scenariuszy (edycja, wersjonowanie i elastyczne harmonogramy), granularnym zarządzaniu modelami AI i ich powiązaniach z agentami, pełnym cyklu życia skilli (edycja, wersje, bezpieczne usuwanie) oraz eliminacji krytycznego błędu przekierowania do edytora scenariuszy.

Główne filary wydania:
1. **Spójność językowa i audyt I18n**:
   - Usunięcie surowego klucza `agent.sub_skill` (oraz `agents.sub_skills`) i nadanie zakładce czytelnej etykiety **„Skille”** (PL) / **„Skills”** (EN).
   - Globalny audyt kluczy tłumaczeń w aplikacji, eliminacja nieprzetłumaczonych ciągów w Menu 1, Menu 2, nagłówkach, formularzach i powiadomieniach.
   - Narzędzie audytu CLI `php artisan lang:audit` oraz test integralności `I18nCompletenessTest`.
2. **Pełna edycja scenariuszy**:
   - Edycja danych podstawowych (nazwa, opis, slug, tagi, status) na liście i w szczegółach.
   - Edycja grafu Drawflow w trybie bezpiecznego szkicu (draft) – opublikowana wersja działa nieprzerwanie do momentu zatwierdzenia.
   - Blokada współbieżnej edycji z informacją o aktywnym użytkowniku.
   - Przywracanie wcześniejszych wersji, duplikowanie i bezpieczne usuwanie z kontrolą zależności.
3. **Przyjazne harmonogramy scenariuszy (Timery)**:
   - Wielość timerów na jeden scenariusz z przyjazną konfiguracją bez konieczności znajomości składni cron.
   - 6 typów: `once` (jednorazowy), `interval` (co ile: minuty/godziny/dni), `daily` (codziennie), `weekly` (dni tygodnia), `monthly` (dzień miesiąca), `cron` (zaawansowane wyrażenie).
   - Obsługa stref czasowych (`Europe/Warsaw`), okna ważności (`starts_at`, `ends_at`, `max_runs`), wejścia statycznego JSON, polityk nakładania (`skip`, `queue`, `parallel`) oraz polityk opóźnień (`skip`, `run_once`).
   - Bezpieczny silnik harmonogramu (`scenarios:dispatch-due`) z blokadą atomową `Cache::lock`, unikalnym kluczem idempotentności i auto-pauzą po N awariach (`SCENARIO_TIMER_MAX_FAILURES=5`).
4. **Edycja i konfiguracja modeli LLM**:
   - Tabela `models` i dedykowany widok *Ustawienia AI → Modele*.
   - Konfiguracja parametrów domyślnych (temperatura, top_p, max_tokens, stop sequences), okna kontekstu, zdolności (chat, vision, tools, embeddings) i cenników (z historią i nadpisaniem per konto).
   - Trwałość ręcznych nadpisań (`models.overrides`) po ponownym pobraniu modeli przez `listModels`. Przycisk ręcznego dodawania modeli.
5. **Kaskadowy wybór modelu w formularzu agenta**:
   - Koniec z ręcznym wpisywaniem modeli – zunifikowany komponent `<x-model-select>`.
   - Kaskadowe filtrowanie: Runtime → Konto / Pula kont → Model (tylko włączone modele o wymaganych zdolnościach).
   - Walidacja serwerowa `ValidModelForAgent` w formularzu i API.
6. **Zarządzanie skillami (edycja, wersjonowanie i bezpieczne usuwanie)**:
   - Rozróżnienie edycji metadanych (bez zmiany wersji) od edycji definicji/schematu (nowa wersja SemVer + changelog).
   - Podmiana paczki (upload ZIP) z walidacją sum kontrolnych i bezpieczeństwa (zip-slip).
   - Skille integracyjne (`integration`) z obsługą nadpisań; skille systemowe modułów (`module`) tylko do odczytu z opcją duplikacji.
   - **Oznaczanie nieużywanych skilli czerwoną flagą**: Skille, które nie są przypisane do żadnego agenta ani scenariusza, są wizualnie wyróżniane na liście czerwoną flagą (ikona 🚩 / etykieta „Nieużywany”).
   - **Usunięcie wyłącznie decyzją użytkownika**: System nie usuwa nieużywanych skilli samoczynnie w tle – decyzja o usunięciu pozostaje całkowicie w rękach użytkownika, który po analizie flagi decyduje o manualnym usunięciu.
   - Blokada usuwania używanych skilli z prezentacją listy zależności (agenci i scenariusze).
   - Soft delete (kosz) z możliwością przywrócenia, manualnego trwałego usunięcia (`skills.purge`) i zachowaniem migawek w historii `skill_runs`.
7. **Naprawa błędu „Utwórz i przejdź do edycji”**:
   - Identyfikacja i usunięcie przyczyny błędu 500 / braku grafu w nowo utworzonym scenariuszu.
   - Atomowe tworzenie scenariusza i wersji roboczej z poprawnym grafem domyślnym (`start` → `end`).
   - Defensywne ładowanie edytora Drawflow przy pustym/uszkodzonym grafie.

---

## 2. Wymagania funkcjonalne (Functional Requirements)

### Obszar 1: Tłumaczenia i Audyt I18n
- **FR-001**: Etykieta podmenu Skilli w module `Agents` MUSI pochodzić z klucza tłumaczenia i wyświetlać „Skille” (PL) oraz „Skills” (EN).
- **FR-002**: System MUSI udostępniać komendę CLI `php artisan lang:audit`, wykrywającą brakujące tłumaczenia, puste wartości oraz surowe klucze w szablonach Blade i manifestach modułów.
- **FR-003**: Weryfikacja `php artisan docs:check` MUSI integrować audyt tłumaczeń, blokując merge w przypadku braków w `lang/pl` lub `lang/en`.

### Obszar 2: Edycja Scenariuszy
- **FR-004**: System MUSI umożliwiać edycję metadanych scenariusza (nazwa, opis, slug, status, limity wykonania) z walidacją unikalności slugu.
- **FR-005**: Edycja opublikowanego scenariusza MUSI operować na wersji roboczej (szkicu), nie wpływając na działanie wersji aktywnej do momentu publikacji.
- **FR-006**: System MUSI zapobiegać współbieżnej edycji tego samego scenariusza przez wielu użytkowników, wyświetlając blokadę z informacją o edytującym.
- **FR-007**: Użytkownik z uprawnieniem `scenarios.edit` MUSI mieć możliwość przywrócenia dowolnej wcześniejszej wersji scenariusza jako nowego szkicu.
- **FR-008**: Usunięcie scenariusza z aktywnymi timerami lub trwającymi uruchomieniami MUSI być blokowane z czytelnym komunikatem i listą zależności.

### Obszar 3: Timery Scenariuszy
- **FR-009**: Scenariusz MUSI mieć możliwość zdefiniowania wielu niezależnych timerów o typach: `once`, `interval`, `daily`, `weekly`, `monthly`, `cron`.
- **FR-010**: Formularz konfiguracji timera MUSI dynamicznie wyliczać i prezentować podgląd 5 kolejnych terminów uruchomienia w strefie `Europe/Warsaw`.
- **FR-011**: Silnik harmonogramu `scenarios:dispatch-due` MUSI zapobiegać podwójnym uruchomieniom przy użyciu blokady atomowej `Cache::lock` oraz unikalnego indeksu `(trigger_id, scheduled_for)`.
- **FR-012**: System MUSI respektować politykę nakładania (`overlap_policy`: `skip`, `queue`, `parallel`) oraz politykę opóźnień (`misfire_policy`: `skip`, `run_once`).
- **FR-013**: Po przekroczeniu `SCENARIO_TIMER_MAX_FAILURES` (domyślnie 5) kolejnych błędów timer MUSI zostać automatycznie zapauzowany (`state=paused`), a zdarzenie odnotowane w logach i telemetrii.

### Obszar 4: Edycja Modeli LLM
- **FR-014**: W module `AiSettings` MUSI znajdować się zakładka *Modele* prezentująca listę modeli z podziałem na dostawców, zdolności i status włączenia.
- **FR-015**: Administrator MUSI mieć możliwość edycji nazwy wyświetlanej, parametrów domyślnych, zdolności, limitów tokenów oraz stawek cennika modelu.
- **FR-016**: Ręcznie zmodyfikowane pola modelu MUSI chronić mechanizm `overrides`, zapobiegający ich nadpisaniu podczas automatycznej synchronizacji `listModels`.
- **FR-017**: Usunięcie lub wyłączenie modelu przypisanego do aktywnych agentów MUSI być blokowane lub wymagać potwierdzenia z listą podmiotów zależnych.

### Obszar 5: Kaskadowy Wybór Modeli
- **FR-018**: Formularz tworzenia i edycji agenta MUSI wykorzystywać komponent `<x-model-select>`, uniemożliwiający wprowadzanie wolnego tekstu dla modelu podstawowego i zapasowego.
- **FR-019**: Lista modeli w `<x-model-select>` MUSI dynamicznie filtrować się na podstawie wybranego środowiska uruchomieniowego (runtime) oraz konta lub puli kont.
- **FR-020**: Żądania zapisu agenta MUSI weryfikować reguła `ValidModelForAgent`, odrzucająca modele nieaktywne lub niedostępne w danej puli/koncie.

### Obszar 6: Cykl Życia Skilli
- **FR-021**: Edycja metadanych skilla (nazwa, opis, README) NIE MOŻE podbijać wersji, natomiast edycja definicji (schemat we/wy, uprawnienia, entrypoint) MUSI tworzyć nową wersję SemVer z wymaganym opisem zmian.
- **FR-022**: Skille zaimportowane z zewnętrznych integracji (`integration`) MUSZĄ umożliwiać edycję lokalną z zachowaniem nadpisań w polu `overrides`.
- **FR-023**: Skille, które NIE są powiązane z żadnym agentem (`agent_skill`) ani nie występują w żadnym scenariuszu, MUSZĄ być prezentowane na liście z wyraźnym oznaczeniem **czerwoną flagą** (ikona 🚩, styl ostrzegawczy i badge „Nieużywany”).
- **FR-024**: System NIE MOŻE automatycznie usuwać nieużywanych skilli w tle – decyzja o usunięciu pozostaje całkowicie w gestii użytkownika, inicjowana manualnie z poziomu UI lub API.
- **FR-024A**: Usunięcie skilla używanego przez agentów lub scenariusze MUSI zwracać błąd HTTP 409 z listą powiązań oraz opcją dezaktywacji (`deprecated`) lub odpięcia od agentów.
- **FR-024B**: Usunięte skille MUSZĄ trafiać do kosza (soft delete) z możliwością przywrócenia lub manualnego trwałego usunięcia (`skills.purge`).

### Obszar 7: Naprawa Kreatora Scenariuszy
- **FR-025**: Operacja `ScenariosController::store` MUSI być wykonywana w atomowej transakcji bazodanowej, tworząc poprawny rekord scenariusza oraz wersję roboczą z minimalnym grafem (`start` → `end`).
- **FR-026**: Edytor Drawflow (`/scenarios/{id}/editor`) MUSI defensywnie obsługiwać brak lub uszkodzenie struktury grafu, uniemożliwiając wystąpienie błędu HTTP 500.

---

## 3. Wymagania niefunkcjonalne (Non-Functional Requirements)

- **NFR-001 (Wydajność harmonogramu)**: Wykonanie komendy `scenarios:dispatch-due` przy 1000 aktywnych timerów nie może przekraczać 2 sekund.
- **NFR-002 (Idempotentność)**: Żaden timer nie może wygenerować podwójnego uruchomienia dla tego samego zaplanowanego terminu, nawet przy równoległym wywołaniu workerów.
- **NFR-003 (I18n & Dostępność)**: 100% widocznych elementów interfejsu w języku polskim i angielskim; zero surowych kluczy `^[a-z_]+(\.[a-z_]+)+$`.
- **NFR-004 (Zgodność wsteczna)**: Migracje bazodanowe muszą być addytywne i bezpieczne dla istniejących wdrożeń 1.5.0; dotychczasowe wpisy `scenario_triggers.type='cron'` muszą zostać zmigrowane bez przerw w działaniu.
- **NFR-005 (Bezpieczeństwo paczek)**: Upload nowych wersji skilli podlega rygorystycznej walidacji ochrony przed atakiem Zip-Slip i ograniczeniu rozmiaru.

---

## 4. Scenariusze użytkownika (User Stories)

### US-001: Płynne tworzenie i edycja nowego scenariusza
Jako **operator systemu**, chcę kliknąć „Nowy scenariusz”, wpisać nazwę i kliknąć „Utwórz i przejdź do edytora”, aby natychmiast otworzył się edytor z węzłem Start i End, bez błędów serwera.

### US-002: Harmonogramowanie automatycznych raportów
Jako **administrator**, chcę skonfigurować timer typu `interval` („co 30 minut”) dla scenariusza analizy logów, aby raporty generowały się automatycznie i niezawodnie, a w razie awarii timer nie zapętlał błędów.

### US-003: Wygodna selekcja modeli w kreatorze agenta
Jako **twórca agentów**, chcę wybierać modele z przejrzystej listy rozwijanej prezentującej tylko te modele, które są aktywne na przydzielonym koncie lub puli, aby uniknąć pomyłek w nazewnictwie modeli.

### US-004: Bezpieczne zarządzanie nieużywanymi skillami
Jako **administrator / deweloper skilli**, chcę widzieć na liście skilli czerwoną flagę przy nieużywanych skillach (brak powiązań z agentami i scenariuszami), aby mieć pełną kontrolę nad czystością systemu i samodzielnie decydować o ich manualnym usunięciu.

---

## 5. Kryteria akceptacji (Definition of Done)

- [ ] Pełna spójność językowa: `php artisan lang:audit` zwraca 0 braków, Menu 2 w module `Agents` wyświetla „Skille”.
- [ ] Utworzenie nowego scenariusza i przejście do edytora kończy się sukcesem (brak błędu 500, test regresyjny zielony).
- [ ] Edycja opublikowanego scenariusza tworzy wersję roboczą, a wersje archiwalne można przywracać.
- [ ] Timery scenariuszy działają dla wszystkich 6 typów harmonogramu, zapobiegają podwójnym odpaleniom i poprawnie przeliczają strefę czasową.
- [ ] Zakładka *Ustawienia AI → Modele* umożliwia pełną edycję parametrów i cenników modeli z zachowaniem nadpisań w `overrides`.
- [ ] Formularz agenta używa komponentu `<x-model-select>` i waliduje dostępność modelu po stronie serwera.
- [ ] Nieużywane skille są wyróżnione na liście **czerwoną flagą** (🚩), a ich usunięcie nie odbywa się automatycznie, lecz pozostaje wyłączną decyzją użytkownika.
- [ ] Skille wspierają wersjonowanie przy edycji definicji, soft delete z koszem i blokadę usuwania przy istniejących zależnościach.
- [ ] Aktualizacja z bazy 1.5.0 przebiega bezbłędnie poprzez `scripts/update.sh` i migracje addytywne.
- [ ] `php artisan docs:check` przechodzi bez zastrzeżeń.
- [ ] Zestaw testów Pest / PHPUnit osiąga 100% powodzenia przy pokryciu krytycznych ścieżek ≥ 80%.
