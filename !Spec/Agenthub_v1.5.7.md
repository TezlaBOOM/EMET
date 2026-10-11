# AgentHub – Update 1.5.7 (dokumentacja dla AI-dewelopera)

> **Przeznaczenie:** dodatek do `Agenthub_v1.md` (1.0.0) i `Agenthub_v1.5.md` (1.5.0). Opisuje wyłącznie zmiany wydania **1.5.7**: poprawki, spolszczenia i drobne rozszerzenia istniejących modułów. Wszystko, czego tu nie zmieniono, obowiązuje wg poprzednich specyfikacji. Odwołania: `v1 §x` = Agenthub_v1.md, `v1.5 §x` = Agenthub_v1.5.md.
>
> **Zakres 1.5.7:**
> 1. Zmiana nazwy zakładki `agent.sub_skill` na „Skille” oraz spolszczenie wszystkich surowych kluczy tłumaczeń.
> 2. Edycja scenariuszy.
> 3. Timery scenariuszy (kiedy / co ile się aktywują).
> 4. Edycja ustawień dodanych modeli.
> 5. Wybór modelu z listy istniejących podczas tworzenia (i edycji) agenta.
> 6. Edycja i usuwanie skilli.
> 7. Naprawa błędu po „Utwórz i przejdź do edycji” w nowym scenariuszu.

---

## 0. Zasady pracy (obowiązują jak w v1 §0 i v1.5 §0)

- Przed zmianą przeczytaj istniejący kod modułów `Agents`, `Scenarios`, `AiSettings`, `Chat` – **nie przepisuj ich od zera**, rozszerzaj.
- **Reguła dokumentacyjna (v1.5 §0 pkt 7–9):** w tym samym PR aktualizuj `CHANGELOG.md`, fragment instrukcji pierwszej konfiguracji (`first_setup`) tam, gdzie zmienia się sposób konfiguracji (pkt 3, 4, 5), oraz sekcje eksportu konfiguracji (`ConfigSection`) tam, gdzie zmieniają się dane konfiguracyjne (pkt 3, 4). `php artisan docs:check` musi przechodzić.
- Każda zmiana = test (Pest). **Błąd z pkt 6 naprawiamy od testu regresyjnego**, który najpierw failuje.
- Wszystkie teksty UI po polsku przez `lang/pl` i `lang/en`; zakaz hardkodowania stringów.
- Nie zmieniaj nazw kolumn ani tras publicznego API bez migracji i aliasu zgodności wstecznej.

### Założenia i punkty do potwierdzenia
| # | Założenie | Status |
|---|-----------|--------|
| Z1 | 1.5.7 jest wydaniem poprawkowym po 1.5.x (ścieżka: 1.5.0…1.5.6 → 1.5.7 przez `update.sh`) | Do potwierdzenia |
| Z2 | `agent.sub_skill` to **surowy klucz tłumaczenia widoczny w interfejsie** (zakładka w Agenci AI → Skille). Docelowa etykieta: **„Skille”** (PL) / **„Skills”** (EN) | Do potwierdzenia |
| Z3 | Strefa czasowa harmonogramów: domyślnie `Europe/Warsaw`, konfigurowalna globalnie (`APP_TIMEZONE`) i per timer | Przyjęte |
| Z4 | Lista modeli w formularzu agenta = modele włączone (`models.enabled`) dostępne na wybranym koncie/puli (`account_models`) | Przyjęte |
| Z5 | Usunięcie skilla używanego przez agentów/scenariusze jest **domyślnie blokowane** (z listą zależności); alternatywą jest „Wycofaj” lub świadome odpięcie od agentów. Usunięcie = soft delete z koszem i możliwością przywrócenia | Do potwierdzenia |

---

## 1. Zmiana nazwy zakładki i spolszczenie kluczy

### 1.1 Wymagania
1. Zakładka (Menu 2 modułu Agenci AI), która dziś wyświetla się jako `agent.sub_skill`, ma mieć etykietę **„Skille”** (EN: „Skills”). Zmiana w manifeście modułu (`module.json` → `menu.submenu[].label`), np. na klucz `modules.agents.submenu.skills`, oraz w plikach `lang/pl` i `lang/en`. Trasa i uprawnienia (`skills.*`) **bez zmian**.
2. **Audyt całej aplikacji:** znajdź wszystkie miejsca, w których użytkownik widzi surowy klucz tłumaczenia (wzorzec `^[a-z_]+(\.[a-z_]+)+$` zamiast tekstu) lub etykiety angielskie/techniczne w interfejsie PL. Dotyczy: Menu 1, Menu 2, nagłówków, przycisków, etykiet formularzy, komunikatów walidacji, pustych stanów, powiadomień, tytułów kart i tabel.
3. Dla każdego znalezionego klucza: dodaj brakujące wpisy w `lang/pl` (po polsku) i `lang/en`; zastąp klucze zahardkodowane lub błędnie nazwane. Nazwy kluczy porządkuj hierarchicznie (`modules.<modul>.<sekcja>.<element>`); stare klucze usuń lub pozostaw jako alias tylko, jeśli używa ich zewnętrzny moduł.
4. Terminologia (stała, w całym UI): *skill* → **Skill/Skille**, *agent* → **Agent/Agenci**, *scenario* → **Scenariusz/Scenariusze**, *timer/schedule* → **Harmonogram/Timer**, *run* → **Uruchomienie**, *pool* → **Pula kont**, *account* → **Konto**, *provider* → **Dostawca**, *memory* → **Pamięć**, *instance* → **Instancja**, *container* → **Kontener**. Nazwy własne (Hermes Agent, OpenClaw, Claude Code, Codex, Qdrant) bez tłumaczenia.

### 1.2 Zabezpieczenie przed powrotem problemu
- Test `tests/Feature/I18nCompletenessTest`: (a) każdy klucz użyty w `__()`, `@lang`, `trans()` i w manifestach modułów istnieje w `lang/pl` **i** `lang/en`; (b) brak pustych wartości; (c) w `lang/pl` brak wartości identycznych z kluczem.
- Komenda `php artisan lang:audit` (używana w CI i w `docs:check`) wypisuje brakujące/nieużywane klucze; CI blokuje merge przy brakach.
- Test E2E (Playwright): przejście przez wszystkie pozycje Menu 1 i Menu 2 w PL – żaden element nie pasuje do wzorca surowego klucza.

---

## 2. Edycja scenariuszy

Uzupełnia v1.5 §9. Dziś scenariusz można utworzyć i zbudować w edytorze; brakuje pełnego **edytowania** istniejących scenariuszy.

### 2.1 Zakres edycji
| Co | Gdzie | Zachowanie |
|----|-------|------------|
| Dane podstawowe (nazwa, opis, slug, tagi, status) | Scenariusze → Lista → **Edytuj** (formularz/panel) | Zmiana slugu wymaga unikalności; zmiana nazwy nie przerywa trwających uruchomień |
| Graf (schemat blokowy) | Przycisk **Edytuj schemat** (otwiera edytor, v1.5 §9.2) | Edycja zawsze na **wersji roboczej** (szkic) |
| Harmonogramy / timery | Zakładka **Harmonogram** w edycji (sekcja 3) | Dodawanie, zmiana, włączanie/wyłączanie, usuwanie |
| Ustawienia wykonania (limity równoległości, budżet, timeout) | Zakładka **Ustawienia** | Zapisywane w `graph.settings` wersji roboczej |
| Duplikuj / archiwizuj / usuń | Menu akcji w wierszu | Usuń = soft delete, z blokadą przy aktywnych uruchomieniach lub aktywnych timerach (z listą zależności) |

### 2.2 Zasady wersjonowania przy edycji
- Edycja **opublikowanego** scenariusza tworzy/otwiera **szkic** kolejnej wersji; opublikowana wersja działa bez zmian do momentu **Opublikuj** (v1.5 §9.5: run jest przypięty do wersji).
- Jeśli szkic już istnieje, edytor go otwiera (nie tworzy nowego). Przy próbie jednoczesnej edycji przez dwóch użytkowników: blokada edycji z komunikatem „Edytuje: <użytkownik>” i możliwością przejęcia (admin) – zgodnie z v1.5 §15 pkt 6.
- Przy publikacji walidacja grafu (v1.5 §9.4); błędy podświetlają węzły.
- Możliwość **przywrócenia** wcześniejszej wersji (tworzy nowy szkic z jej treści) oraz porównania metadanych wersji (data, autor, changelog).
- Każda edycja audytowana (v1 §7.7): kto, co, diff metadanych.

### 2.3 UI
Na liście scenariuszy kolumny: nazwa, status, aktualna wersja, następne uruchomienie (z timerów), ostatnie uruchomienie, akcje (**Edytuj · Edytuj schemat · Uruchom · Duplikuj · Archiwizuj · Usuń**). Widok szczegółów scenariusza z zakładkami: *Przegląd* · *Schemat* · *Harmonogram* · *Wersje* · *Uruchomienia* · *Ustawienia*.

### 2.4 Uprawnienia i API
Uprawnienia: `scenarios.edit` (dane, graf, harmonogram), `scenarios.publish`, `scenarios.delete`. API: `PUT /api/v1/scenarios/{id}`, `PUT /api/v1/scenarios/{id}/draft`, `POST /api/v1/scenarios/{id}/versions/{version}/restore`, `POST /api/v1/scenarios/{id}/duplicate`.

---

## 3. Timery scenariuszy (kiedy / co ile)

Rozszerza wyzwalacz `cron` z v1.5 §9.3 i tabelę `scenario_triggers` (v1.5 §9.7) o **przyjazne harmonogramy** konfigurowane bez znajomości składni cron.

### 3.1 Typy harmonogramu
Scenariusz może mieć **wiele timerów** (każdy z własną nazwą, wejściem i stanem włączenia):

| Typ | Pola | Przykład |
|-----|------|----------|
| `once` – jednorazowo | data i godzina | „2026-11-02 08:00” |
| `interval` – **co ile** | liczba + jednostka (minuty/godziny/dni), opcjonalny początek | „co 15 minut”, „co 6 godzin” |
| `daily` – codziennie | godzina (opcjonalnie wiele godzin), opcjonalnie tylko dni robocze | „codziennie o 07:30” |
| `weekly` – **kiedy** w tygodniu | dni tygodnia + godzina | „pn, śr, pt o 09:00” |
| `monthly` | dzień miesiąca (lub „ostatni dzień”) + godzina | „1. dnia miesiąca o 06:00” |
| `cron` (zaawansowany) | wyrażenie cron (5 pól) z walidacją | `*/10 8-18 * * 1-5` |

### 3.2 Pola wspólne każdego timera
- `name`, `enabled` (przełącznik), **strefa czasowa** (domyślnie `Europe/Warsaw`, obsługa zmiany czasu zimowy/letni),
- **okno obowiązywania:** `starts_at`, `ends_at` (opcjonalne), `max_runs` (opcjonalny limit uruchomień),
- **wejście statyczne** (`input` – JSON przekazywany do węzła `start`), z podglądem i walidacją względem formularza wejścia scenariusza,
- **polityka nakładania** (`overlap_policy`): `skip` (domyślnie – pomiń, gdy poprzedni run trwa), `queue` (kolejkuj), `parallel` (uruchom równolegle),
- **polityka zaległych** (`misfire_policy`): `skip` (domyślnie), `run_once` (nadrób jednokrotnie) – działa po przestoju aplikacji,
- **podgląd „następne 5 uruchomień”** liczony na żywo w formularzu; czytelny opis słowny („Co 15 minut, od 08:00 do 18:00, pn–pt”),
- akcja **„Uruchom teraz”** (niezależnie od harmonogramu) i **„Wstrzymaj wszystkie”** dla scenariusza.

### 3.3 Silnik harmonogramu
- Komenda `scenarios:dispatch-due` uruchamiana przez scheduler Laravela **co minutę** (cron `schedule:run` istnieje już w instalatorach, v1 §13.4); wybiera timery z `enabled=1` i `next_run_at <= now()`.
- **Brak podwójnych odpaleń:** blokada atomowa per timer (`Cache::lock`) oraz **klucz idempotentności** `(trigger_id, zaplanowany_czas)` z unikalnym indeksem w `scenario_runs.idempotency_key`.
- Po odpaleniu `next_run_at` jest obliczany na nowo (biblioteka obsługująca cron i strefy, np. `dragonmantank/cron-expression` + Carbon; dla `interval` liczone od zaplanowanego, nie rzeczywistego czasu, aby nie dryfować).
- Tylko **opublikowane** scenariusze są odpalane; scenariusz bez opublikowanej wersji → timer nieaktywny z komunikatem.
- Uruchomienie z timera ma `trigger_type=schedule`, `trigger_id` i zaplanowany czas w metadanych runu (widoczne w Uruchomieniach, Logach i na Dashboardzie).
- Błąd odpalenia (np. niepoprawny input, budżet przekroczony) → wpis w logach systemowych, `last_error` na timerze, **automatyczne wstrzymanie** po `N` kolejnych porażkach (`SCENARIO_TIMER_MAX_FAILURES`, domyślnie 5) z powiadomieniem.
- Wygaśnięcie (`ends_at`/`max_runs`) → timer przechodzi w stan `finished`.

### 3.5 Zmiany w modelu danych
| Tabela | Zmiana |
|--------|--------|
| `scenario_triggers` | + `name`, `schedule_type` (`once`/`interval`/`daily`/`weekly`/`monthly`/`cron`), `schedule` (json: parametry typu), `timezone`, `starts_at`, `ends_at`, `max_runs`, `runs_count`, `input` (json), `overlap_policy`, `misfire_policy`, `next_run_at` (indeks), `last_run_at`, `last_run_id`, `consecutive_failures`, `last_error`, `state` (`active`/`paused`/`finished`/`error`). Dotychczasowe wpisy `type=cron` migrowane do `schedule_type=cron` |
| `scenario_runs` | + `trigger_id` (nullable), `scheduled_for` (nullable), `idempotency_key` (unikalny, nullable) |

### 3.6 UI
Edycja scenariusza → zakładka **Harmonogram:** tabela timerów (nazwa, opis słowny, następne uruchomienie, ostatnie uruchomienie/wynik, stan, przełącznik, akcje) + kreator „Dodaj timer” (wybór typu → pola → podgląd 5 najbliższych terminów → zapis). W edytorze (v1.5 §9.2) węzeł `start` pokazuje listę timerów i skrót „Dodaj timer”. Lista scenariuszy pokazuje „następne uruchomienie”.

### 3.7 Uprawnienia, API, eksport
Uprawnienie `scenarios.schedule` (tworzenie/zmiana timerów; uruchamianie bez nadzoru → domyślnie `admin`, `operator`). API: `GET/POST /api/v1/scenarios/{id}/triggers`, `PUT/DELETE /api/v1/scenario-triggers/{id}`, `POST /api/v1/scenario-triggers/{id}/pause|resume|fire`. Timery wchodzą do sekcji `scenarios` eksportu konfiguracji (v1.5 §8.2); po imporcie są **wstrzymane** (do ręcznego włączenia).

### 3.8 `.env`
```dotenv
APP_TIMEZONE=Europe/Warsaw
SCENARIO_TIMER_MAX_FAILURES=5
```

---

## 4. Edycja ustawień dodanych modeli

Rozszerza v1 §10.3 (zakładka **Ustawienia AI → Modele**). Dziś modele można włączać/wyłączać; brakuje edycji ich ustawień.

### 4.1 Edytowalne ustawienia modelu
| Pole | Opis |
|------|------|
| Nazwa wyświetlana (`display_name`) | Dowolna, widoczna na listach wyboru |
| Klucz modelu (`model_key`) | Edytowalny **tylko dla modeli dodanych ręcznie**; dla wykrytych przez `listModels` – tylko do odczytu |
| Włączony (`enabled`) | Przełącznik; wyłączony model znika z list wyboru, ale istniejące przypisania są oznaczone ostrzeżeniem |
| Zdolności (`capabilities`) | chat, embeddings, vision, tools (wielokrotny wybór) |
| Okno kontekstu (`context_window`), maks. tokenów odpowiedzi | Liczby z walidacją |
| **Parametry domyślne** (`parameters`) | temperatura, top_p, max_tokens, stop sequences itp.; używane, gdy agent nie nadpisuje |
| Cennik | wejście/wyjście za 1 mln tokenów, waluta, `valid_from` (`model_pricing`, nowy wpis zamiast nadpisania historii); opcjonalne nadpisanie per konto (v1 §10.4) |
| Dostępność per konto | przełączniki kont w `account_models` |
| Tagi / kolejność | porządek na listach wyboru |

### 4.2 Zasady
- **Nadpisania nie giną przy synchronizacji:** pola zmienione ręcznie zapisujemy w `models.overrides` (json); ponowne `listModels` aktualizuje tylko pola niezmienione ręcznie. Przycisk **„Przywróć wartości z dostawcy”** czyści nadpisania.
- Zmiana wpływająca na agentów (wyłączenie modelu, usunięcie zdolności `tools`/`chat`) pokazuje przed zapisem **listę agentów i scenariuszy, których dotyczy**; usunięcie modelu używanego przez agentów jest zablokowane (zaproponuj zamiennik z listy).
- Walidacja po stronie serwera (zakresy parametrów zgodne z możliwościami dostawcy); komunikaty po polsku.
- Audyt zmian (diff przed/po), uprawnienie `ai.models.edit`.
- Przycisk **„Dodaj model ręcznie”** (dostawca, klucz, nazwa, zdolności) – dla modeli niewykrytych automatycznie.

### 4.3 Zmiany w modelu danych
| Tabela | Zmiana |
|--------|--------|
| `models` | + `parameters` (json), `max_output_tokens` (nullable), `is_manual` (bool), `overrides` (json), `tags` (json), `sort` (int) |
| `model_pricing` | + `account_id` (nullable – cennik per konto) |

### 4.4 UI
Ustawienia AI → Modele: tabela (dostawca, nazwa, klucz, zdolności, kontekst, status, liczba agentów) + wiersz → **Edytuj** (panel boczny/modal z polami z 4.1). Pole z oznaczeniem „zmienione ręcznie” i przyciskiem przywracania wartości. Zmiany wchodzą do sekcji `ai` eksportu konfiguracji (v1.5 §8.2).

---

## 5. Wybór modelu z listy podczas tworzenia agenta

Dotyczy formularza **Agenci AI → Utwórz agenta** i edycji agenta (v1 §7.2).

### 5.1 Zachowanie
- Pola **Model** i **Model zapasowy** są **listami wyboru** (z wyszukiwaniem), wypełnianymi istniejącymi modelami z `models` – **brak wpisywania wolnego tekstu**.
- Kolejność wyboru (kaskada): **Runtime** → **Konto lub pula kont** → **Model**. Lista modeli filtruje się do:
  - modeli `enabled=1` z odpowiednią zdolnością (`chat`; `tools`, jeśli agent ma skille wymagające narzędzi – z podpowiedzią dlaczego model jest niedostępny),
  - modeli dostępnych na wybranym koncie (`account_models`); dla puli kont – modeli dostępnych **na co najmniej jednym koncie puli** (z informacją „dostępny na X z Y kont”; gateway pomija konta bez modelu),
  - dla runtime'u Natywny – wszystkie włączone modele; dla Claude Code/Codex – modele wspierane przez dany profil/adapter.
- Elementy listy pogrupowane według dostawcy; wiersz: nazwa wyświetlana, dostawca, okno kontekstu, znaczniki zdolności (chat/vision/tools), orientacyjny koszt.
- Po wyborze modelu formularz **podpowiada parametry domyślne** z ustawień modelu (sekcja 4) i pozwala je nadpisać.
- **Pusty stan:** gdy brak modeli – komunikat „Brak dostępnych modeli” z linkami do *Ustawienia AI → Konta* i *Modele*.
- Zmiana konta/puli lub runtime'u po wyborze modelu: jeśli model nie jest już dostępny, pole jest czyszczone z komunikatem.
- Ten sam komponent używany w: edycji agenta, szablonach agentów, węźle `agent` scenariusza (nadpisanie modelu, v1.5 §9.3) i ustawieniach embeddingów – jeden komponent Blade/Livewire `<x-model-select>`.

### 5.2 Walidacja po stronie serwera
Reguła `ValidModelForAgent`: model istnieje, jest włączony, ma wymaganą zdolność i jest dostępny dla wybranego konta/puli/runtime'u; w przeciwnym razie błąd walidacji (po polsku). Dotyczy też API (`POST/PUT /api/v1/agents`). Zapis tekstu spoza listy jest odrzucany.

### 5.3 Migracja istniejących agentów
Agenci z modelem, który nie istnieje lub jest wyłączony, **nie są usuwani ani modyfikowani automatycznie** – w UI dostają znacznik „Model niedostępny” i wymagają wyboru nowego przy najbliższej edycji; lista takich agentów w *Logi → Systemowe* po aktualizacji.

---

## 5A. Edycja i usuwanie skilli

Uzupełnia v1.5 §3 (zakładka **Agenci AI → Skille**). Dziś skill można dodać, wersjonować i przypisać; ta sekcja domyka pełne **edytowanie i usuwanie** z poziomu interfejsu i API.

### 5A.1 Edycja skilla
Dostępna z listy (akcja **Edytuj**) i ze szczegółów skilla.

| Co | Zachowanie |
|----|------------|
| Dane opisowe: nazwa, opis, tagi, README/`SKILL.md` (edytor Markdown) | Zmiana **nie tworzy** nowej wersji (dotyczy metadanych) |
| Definicja: schemat wejścia/wyjścia (JSON Schema), wymagane uprawnienia (`requires`), parametry, entrypoint, `runtime_compat` | Zmiana **tworzy nową wersję** skilla; obowiązkowe pole *Opis zmiany* (changelog); podpowiedź numeru SemVer (patch/minor/major) |
| Paczka (`package`): manifest `skill.json`, `SKILL.md`, zasoby | Edycja plików tekstowych w edytorze lub **podmiana paczki** (upload ZIP); walidacja jak przy dodawaniu (limit rozmiaru, zip-slip, sumy kontrolne) |
| Klucz (`key`) i typ (`type`) | **Tylko do odczytu** po utworzeniu (zmiana wymaga duplikacji skilla) |
| Status (`draft`/`active`/`deprecated`) | Zmiana z poziomu edycji; `deprecated` ukrywa skill z list wyboru, ale nie psuje istniejących przypisań |

Zasady:
- **Źródło skilla decyduje o zakresie edycji:** `manual`, ZIP, Git – pełna edycja; `integration` (zaimportowany z Hermes/OpenClaw) – edycja dozwolona, a ręczne zmiany są zapisywane jako nadpisania (`overrides`) i **nie giną przy ponownej synchronizacji** (jak modele, sekcja 4.2), z przyciskiem „Przywróć wartości ze źródła”; `module` (z manifestu modułu) – **tylko do odczytu**, dostępna akcja **„Duplikuj jako własny”**.
- **Wpływ na agentów i scenariusze:** przed zapisem nowej wersji UI pokazuje listę agentów i scenariuszy używających skilla. Agenci z **przypiętą wersją** (`pinned`) pozostają na starej; pozostali przechodzą na nową. Jeśli zmiana (np. nowe wymagane uprawnienie `internet`) sprawiłaby, że agent straci możliwość użycia skilla (v1.5 §3.3, §5), UI wyświetla ostrzeżenie z nazwami agentów.
- **Zatwierdzanie:** nowa wersja jest od razu aktywna, jeśli autor ma `skills.publish`; w przeciwnym razie otrzymuje status `pending_review`, a poprzednia wersja pozostaje aktywna do zatwierdzenia.
- **Wersje:** podgląd historii, **przywrócenie** wcześniejszej wersji (tworzy nową wersję z jej treści), usunięcie starej wersji (tylko niebędącej bieżącą ani przypiętą przez agenta/scenariusz).
- **Test po edycji:** przycisk **Zapisz i przetestuj** uruchamia skill w sandboxie z przykładowym wejściem (v1.5 §3.1).
- **Równoczesna edycja:** blokada optymistyczna (porównanie `updated_at`/numeru wersji); konflikt → komunikat „Skill został zmieniony przez innego użytkownika” z możliwością odświeżenia.
- Audyt: kto, co, diff metadanych i definicji (v1 §7.7); uprawnienie `skills.edit`.

### 5A.2 Usuwanie skilla
- Akcja **Usuń** w wierszu listy i w szczegółach oraz **usuwanie masowe** (zaznaczenie wierszy). Okno potwierdzenia pokazuje nazwę, liczbę wersji i **listę zależności**: agenci (`agent_skill`), scenariusze (węzły `skill` w wersjach roboczych i opublikowanych), ostatnie uruchomienia.
- **Skill używany jest blokowany** przed usunięciem (Z5). Użytkownik ma opcje: **Wycofaj** (`deprecated`, zalecane), **Odepnij od agentów i usuń** (usuwa wpisy `agent_skill`; nie dotyczy skilli użytych w scenariuszach – te trzeba najpierw poprawić), albo anulować. Skill użyty w opublikowanym scenariuszu nie może zostać usunięty, dopóki scenariusz go nie przestanie używać.
- **Nieużywane skille oznaczane czerwoną flagą**: skille, które nie są przypisane do żadnego agenta ani scenariusza, są w widoku listy oznaczane **czerwoną flagą** (ikona 🚩, badge ostrzegawczy „Nieużywany”). **Usunięcie pozostaje w pełni decyzją użytkownika** – system nie kasuje ich automatycznie w tle.
- **Soft delete** (kosz): skill znika z list i z wyborów, ale można go **przywrócić**. Filtr **„Usunięte”** na liście z akcjami **Przywróć** i **Usuń trwale** (tylko `admin`/`skills.purge`; kasuje wersje i pliki paczki z dysku `skills`).
- Historia uruchomień (`skill_runs`) jest zachowana: dodajemy migawkę nazwy i klucza (`skill_name`, `skill_key`), aby wpisy były czytelne po usunięciu.
- Przy przywróceniu kolidujący `key`/`slug` (zajęty przez nowy skill) → komunikat z propozycją zmiany nazwy.
- Audyt usunięcia/przywrócenia/trwałego usunięcia; uprawnienie `skills.delete`.

### 5A.3 Model danych, API, uprawnienia
| Element | Zmiana |
|---------|--------|
| `skills` | + `deleted_at`, `deleted_by` (soft deletes), `overrides` (json), `locked_by_module` (bool, dla źródła `module`) |
| `skill_runs` | + `skill_name`, `skill_key` (migawki); `skill_id` nullable po trwałym usunięciu |
| `skill_versions` | + `deleted_at` (usuwanie starych wersji) |

API: `PUT /api/v1/skills/{id}` (metadane i nowa wersja), `DELETE /api/v1/skills/{id}` (soft delete; `409 Conflict` z listą zależności, gdy skill używany), `POST /api/v1/skills/{id}/restore`, `DELETE /api/v1/skills/{id}?force=1` (trwałe), `POST /api/v1/skills/bulk-delete`, `DELETE /api/v1/skills/{id}/versions/{version}`.
Uprawnienia: `skills.edit`, `skills.delete`, `skills.purge` (nowe; dodać do `RolesAndPermissionsSeeder` – `admin` wszystkie, `operator` tylko `skills.edit`). `.env`: `SKILLS_TRASH_RETENTION_DAYS=30`.
Eksport konfiguracji (v1.5 §8): sekcja `skills` pomija skille w koszu; import ignoruje usunięte.

### 5A.4 UI
Lista skilli: kolumna akcji (**Szczegóły · Edytuj · Duplikuj · Wycofaj · Usuń**), masowe zaznaczanie, filtr „Usunięte”. Formularz edycji z zakładkami *Dane* · *Definicja* · *Paczka/README* · *Wersje* · *Użycie* (lista agentów i scenariuszy). Wszystkie teksty po polsku w `lang/pl` i `lang/en` (sekcja 1).

---

## 6. Naprawa błędu: „Utwórz i przejdź do edycji” w nowym scenariuszu

### 6.1 Objaw
Nowy scenariusz → uzupełnienie formularza → przycisk **„Utwórz i przejdź do edycji”** → błąd (zamiast otwarcia edytora).

### 6.2 Procedura dla AI (obowiązkowa kolejność)
1. **Odtwórz błąd** lokalnie (przeglądarka + `storage/logs/laravel.log`, kod odpowiedzi HTTP, błąd Livewire/JS w konsoli). Zapisz dokładny komunikat i stos w opisie PR. **Nie zgaduj** – ustal przyczynę z logów.
2. **Napisz test regresyjny**, który failuje: Feature (Pest) – utworzenie scenariusza i podążenie za przekierowaniem do edytora, plus test E2E (Playwright) ścieżki „Nowy scenariusz → Utwórz i przejdź do edycji → edytor się ładuje”.
3. Napraw **przyczynę źródłową** (nie maskuj wyjątku).
4. Sprawdź powiązane ścieżki: „Utwórz” (bez przejścia), duplikowanie, import JSON, tworzenie ze szablonu.

### 6.3 Najbardziej prawdopodobne przyczyny do sprawdzenia
(Hipotezy z architektury v1.5 – zweryfikuj, nie zakładaj.)
- Przekierowanie do trasy edytora `/scenarios/{id}/editor` z niewłaściwym parametrem (np. `slug` zamiast `id` lub odwrotnie; niedopasowany route-model-binding), albo nieistniejąca trasa/nazwa trasy.
- Po utworzeniu `scenarios` **brak wersji roboczej** (`scenario_versions` z `draft=true`) i `current_version_id=null` → edytor ładuje `graph` z `null` i rzuca wyjątek.
- Pusty/niepoprawny domyślny `graph` (JSON nie spełnia schematu; brak węzłów `start`/`end`) lub walidacja grafu uruchamiana już przy tworzeniu szkicu.
- Tworzenie scenariusza i wersji bez transakcji (częściowy zapis) albo kolizja unikalnego `slug`.
- Brak uprawnienia (`scenarios.edit`) lub policy odrzucająca właśnie utworzony zasób; błąd uprawnień zwracany jako 500.
- Brakujące klucze tłumaczeń lub nieprawidłowy cast `json`/`array` w modelu `ScenarioVersion`.
- Zdarzenie realtime/Reverb rzucające wyjątek podczas tworzenia.

### 6.4 Wymagane zachowanie po naprawie
- Utworzenie scenariusza **atomowo** (transakcja) tworzy: rekord `scenarios` (status `draft`), `scenario_versions` z wersją roboczą i **domyślnym poprawnym grafem** (węzeł `start` → węzeł `end`), ustawia wskaźnik wersji roboczej.
- Edytor otwiera się na szkicu bez błędów, także dla scenariusza bez żadnych węzłów poza domyślnymi.
- Błędy walidacji formularza (np. zajęty slug) wyświetlane przy polach po polsku; nieoczekiwany wyjątek → czytelny komunikat + wpis w logu, **bez białego ekranu 500**.
- Edytor zabezpieczony przed `null` graph (defensywne ładowanie: brak grafu = domyślny graf + ostrzeżenie w logu).

---

## 7. Testy i kryteria jakości

- **Spolszczenia:** `I18nCompletenessTest`, `lang:audit`, E2E przejścia po menu (sekcja 1.2).
- **Edycja scenariuszy:** edycja metadanych, szkic nad opublikowaną wersją (trwający run nie zmienia się), przywracanie wersji, blokada edycji, blokada usunięcia z aktywnymi timerami/uruchomieniami, audyt.
- **Timery:** obliczanie `next_run_at` dla każdego typu (w tym granice zmiany czasu w `Europe/Warsaw`), brak podwójnego odpalenia (dwa równoległe `dispatch-due`), polityki `overlap` i `misfire`, `ends_at`/`max_runs`, auto-wstrzymanie po N porażkach, scenariusz bez opublikowanej wersji, import → timery wstrzymane, podgląd 5 terminów zgodny z silnikiem.
- **Modele:** edycja pól, nadpisania zachowane po `listModels`, „Przywróć wartości z dostawcy”, blokada usunięcia używanego modelu, lista agentów dotkniętych zmianą, cennik z historią i nadpisaniem per konto.
- **Wybór modelu w agencie:** filtrowanie kaskadowe (runtime → konto/pula → model), filtr zdolności, pusty stan, czyszczenie pola po zmianie konta, odrzucenie wartości spoza listy (UI i API), komponent `<x-model-select>` w węźle scenariusza.
- **Skille – edycja i usuwanie:** edycja metadanych (bez nowej wersji) vs definicji (nowa wersja + changelog), pełna edycja dla `manual`/ZIP/Git, nadpisania dla `integration` zachowane po synchronizacji, `module` tylko do odczytu + duplikacja, przypięta wersja agenta bez zmian, ostrzeżenie o utracie zdolności, `pending_review` bez `skills.publish`, blokada optymistyczna, przywracanie wersji; usuwanie: `409` przy zależnościach (agenci i scenariusze), odpięcie od agentów, soft delete + przywrócenie + trwałe usunięcie (pliki paczki znikają), czyszczenie kosza po retencji, zachowanie historii `skill_runs`, uprawnienia `skills.edit|delete|purge`.
- **Błąd z sekcji 6:** test regresyjny failujący przed poprawką i zielony po niej + E2E.
- Pokrycie krytycznych ścieżek ≥ 80 %; CI blokuje merge (Pint, PHPStan, Pest, `docs:check`, `lang:audit`).

---

## 8. Aktualizacja z 1.5.x

- Migracje addytywne: `scenario_triggers`, `scenario_runs`, `models`, `model_pricing` (sekcje 3.5, 4.3). Backfill: istniejące wyzwalacze `cron` → `schedule_type=cron`, `state=active`; modele wykryte → `is_manual=false`, ręcznie dodane → `true`.
- Migracje skilli (sekcja 5A.3): `deleted_at`/`deleted_by`/`overrides`/`locked_by_module` w `skills`, migawki w `skill_runs`; backfill `locked_by_module=true` dla skilli ze źródłem `module`; nowe uprawnienia w seederze.
- `compat-check.sh`: weryfikacja nowych migracji (`--pretend`), obecności `APP_TIMEZONE` i działania schedulera (cron `schedule:run`); ostrzeżenie, gdy scheduler nie działa (timery nie będą odpalane).
- `agenthub:selftest` rozszerzony o: utworzenie scenariusza z przejściem do edytora, odpalenie timera `interval` (mock), edycję modelu, walidację wyboru modelu w agencie.
- Dokumentacja do aktualizacji: `docs/ARCHITECTURE.md` (silnik harmonogramu), `docs/UPDATE.md`, instrukcja pierwszej konfiguracji (kroki: modele – edycja ustawień; scenariusze – timery; agent – wybór modelu), `docs/integrations/*.md` bez zmian.

---

## 9. Kryteria akceptacji 1.5.7 (Definition of Done)

- [ ] Zakładka dawniej wyświetlana jako `agent.sub_skill` nazywa się **„Skille”** (PL) / „Skills” (EN); w całej aplikacji nie ma widocznych surowych kluczy tłumaczeń; `lang:audit` i `I18nCompletenessTest` zielone.
- [ ] Scenariusz można **edytować** (dane, schemat, ustawienia, harmonogram) – edycja opublikowanego scenariusza działa na szkicu, wersje można przywracać.
- [ ] Do scenariusza można dodać **wiele timerów** typów: jednorazowy, co N minut/godzin/dni, codziennie, tygodniowo, miesięcznie, cron; z podglądem 5 najbliższych terminów, strefą czasową, oknem obowiązywania, polityką nakładania; odpalają się punktualnie i **nie dublują**.
- [ ] Ustawienia dodanych modeli (nazwa, zdolności, kontekst, parametry domyślne, cennik, dostępność per konto) są **edytowalne**, a ręczne zmiany przetrwają synchronizację z dostawcą.
- [ ] W formularzu agenta modele (główny i zapasowy) wybiera się **z listy** istniejących modeli filtrowanej kontem/pulą/zdolnościami; wartości spoza listy są odrzucane (UI i API).
- [ ] **Skille można edytować** (metadane, definicja z nową wersją, paczka) i **usuwać** (soft delete z koszem, przywracanie, trwałe usunięcie, usuwanie masowe); skill używany przez agentów lub scenariusze jest chroniony listą zależności; historia uruchomień zachowana.
- [ ] „Utwórz i przejdź do edycji” w nowym scenariuszu otwiera edytor bez błędu; test regresyjny (Feature + E2E) w repozytorium.
- [ ] Aktualizacja 1.5.x → 1.5.7 przez `update.sh` kończy się powodzeniem (w tym rollback przy błędzie).
- [ ] `CHANGELOG.md` zawiera wpis 1.5.7 (sekcja 10); fragmenty `first_setup` i sekcje eksportu zaktualizowane; `docs:check` zielony.

---

## 10. Wpis do CHANGELOG.md

```markdown
## [1.5.7] – RRRR-MM-DD

### Dodano
- **Temat:** Edycja scenariuszy
  **Opis:** Edycja danych, schematu i ustawień scenariusza; edycja opublikowanego scenariusza na szkicu z przywracaniem wersji.
- **Temat:** Timery scenariuszy
  **Opis:** Harmonogramy: jednorazowy, co N minut/godzin/dni, codziennie, tygodniowo, miesięcznie i cron; strefa czasowa, okno obowiązywania, polityki nakładania i zaległych uruchomień, podgląd najbliższych terminów.
- **Temat:** Edycja ustawień modeli
  **Opis:** Nazwa, zdolności, okno kontekstu, parametry domyślne, cennik i dostępność per konto; ręczne zmiany zachowywane przy synchronizacji z dostawcą.
- **Temat:** Wybór modelu z listy w formularzu agenta
  **Opis:** Modele wybierane z listy istniejących, filtrowanej według konta/puli, zdolności i runtime'u; walidacja po stronie serwera.
- **Temat:** Edycja i usuwanie skilli
  **Opis:** Edycja metadanych i definicji (z nową wersją i przywracaniem), edycja paczki; usuwanie z kontrolą zależności, kosz z przywracaniem, trwałe i masowe usuwanie.

### Zmieniono
- **Temat:** Nazwa zakładki skilli
  **Opis:** `agent.sub_skill` zastąpiono etykietą „Skille”; spolszczono wszystkie surowe klucze tłumaczeń w interfejsie; dodano kontrolę kompletności tłumaczeń w CI.

### Naprawiono
- **Temat:** Błąd po „Utwórz i przejdź do edycji” w nowym scenariuszu
  **Opis:** Tworzenie scenariusza atomowo tworzy wersję roboczą z poprawnym grafem domyślnym; edytor otwiera się bez błędu; dodano test regresyjny.
```

---

## 11. Plan realizacji (etapy)

| Etap | Zakres | Rezultat |
|------|--------|----------|
| **A. Błąd scenariusza** | Sekcja 6 (reprodukcja → test → poprawka) | Działające „Utwórz i przejdź do edycji” |
| **B. Spolszczenia** | Sekcja 1 (zmiana nazwy zakładki, audyt, `lang:audit`, testy) | UI bez surowych kluczy |
| **C. Modele** | Sekcje 4 i 5 (edycja modeli, `<x-model-select>`, walidacja, migracja agentów) | Edycja modeli i wybór z listy |
| **C2. Skille** | Sekcja 5A (edycja, wersje, usuwanie, kosz, uprawnienia) | Pełne zarządzanie skillami |
| **D. Edycja scenariuszy** | Sekcja 2 | Pełna edycja i wersje |
| **E. Timery** | Sekcja 3 (silnik, UI, API) | Harmonogramy scenariuszy |
| **F. Wydanie** | Sekcje 7–10: testy E2E, `update.sh`, dokumentacja, CHANGELOG | Wydanie 1.5.7 |

Po każdym etapie: testy zielone, wpis w `[Unreleased]` CHANGELOG, aktualizacja `first_setup` i sekcji eksportu tam, gdzie dotyczy.

---

## 12. Pytania otwarte

1. **Wersjonowanie:** czy 1.5.7 następuje po 1.5.6 (istnieją wydania 1.5.1–1.5.6), czy po 1.5.0? (Z1)
2. **Nazwa zakładki:** czy etykieta ma brzmieć „Skille”, czy dokładnie „Skill”? Czy `agent.sub_skill` to faktycznie surowy klucz widoczny w UI? (Z2)
3. **Błąd scenariusza:** jaki dokładnie komunikat/kod błędu pojawia się po „Utwórz i przejdź do edycji” (ekran błędu 500, komunikat Livewire, wpis z `laravel.log`)? Sekcja 6 opisuje procedurę i hipotezy, ale dokładna przyczyna wymaga logu.
4. **Timery:** czy potrzebne są powiadomienia (e-mail/panel) o nieudanym odpaleniu poza auto-wstrzymaniem?
