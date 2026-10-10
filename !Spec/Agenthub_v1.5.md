# AgentHub – Specyfikacja aktualizacji 1.5.0 (dla AI-dewelopera)

> **Przeznaczenie:** ten dokument jest **dodatkiem** do `Agenthub_v1.md` (wersja 1.0.0). Opisuje wyłącznie zmiany wprowadzane w wydaniu **1.5.0**. Wszystko, czego tu nie zmieniono, obowiązuje dalej wg v1. Odwołania `v1 §x.y` wskazują sekcje bazowej specyfikacji.
>
> **Zakres 1.5.0:** (1) skille – zakładka i magazyn, (2) czat z wieloma agentami, (3) scenariusze – edytor blokowy z telemetrią, (4) zdolności agenta: internet / wiedza / pamięć wywołań, (5) instrukcja pierwszej konfiguracji + reguła jej aktualizacji, (6) podłączanie i konfiguracja kontenerów w Integracjach, (7) eksport/import całej konfiguracji (admin).

---

## 0. Zasady pracy – uzupełnienie (v1 §0)

Obowiązują wszystkie zasady z v1 §0. Dodatkowo, od 1.5.0:

7. **Reguła dokumentacyjna (obowiązkowa przy każdej zmianie funkcjonalnej):** każda zmiana, która dodaje lub zmienia funkcję, ustawienie, zmienną `.env`, uprawnienie lub krok instalacji, **musi w tym samym PR** zaktualizować:
   a) `CHANGELOG.md` (v1 §20.2),
   b) fragment instrukcji pierwszej konfiguracji (sekcja 6 tego dokumentu),
   c) sekcję eksportu/importu konfiguracji, jeśli zmiana dotyczy danych konfiguracyjnych (sekcja 8).
8. Reguła z pkt 7 ma być zapisana **w repozytorium w pliku reguł dla AI** (`AGENTS.md` w katalogu głównym; `CLAUDE.md` zawiera tylko odwołanie do `AGENTS.md`) oraz w szablonie PR (`.github/pull_request_template.md` z listą kontrolną). Plik reguł zawiera też: stack, komendy testów, zakaz hardkodowania tekstów, zakaz logowania sekretów.
9. CI uruchamia `php artisan docs:check` (sekcja 6.5) i **blokuje merge**, gdy wykryje zmianę modułu bez aktualizacji dokumentacji/CHANGELOG.
10. Wszystkie nowe elementy konfiguracji muszą od razu implementować kontrakt eksportu (`ConfigSection`, sekcja 8.3) – inaczej nie przejdą testu kompletności eksportu.

---

## 1. Założenia i punkty do potwierdzenia

| # | Założenie przyjęte w tej specyfikacji | Status |
|---|----------------------------------------|--------|
| Z1 | 1.5.0 jest bezpośrednim następcą 1.0.0 (ścieżka aktualizacji 1.0.x → 1.5.0 przez `update.sh`) | **Do potwierdzenia** |
| Z2 | „Wiedza zgromadzona” = istniejąca pamięć wektorowa (v1 §7.3, §8) | Do potwierdzenia |
| Z3 | Skille mają **własny format paczki** (`skill.json` + opcjonalnie `SKILL.md`); adaptery mapują go na format runtime'u (Hermes/OpenClaw – zweryfikować w dokumentacji) | **Do potwierdzenia** (czy ma być zgodny z konkretnym standardem) |
| Z4 | „Oddzielne okno” scenariuszy = pełnoekranowy widok edytora (własny layout, bez siatki 7 bloków) + opcja „Otwórz w nowym oknie przeglądarki” | Do potwierdzenia |
| Z5 | Edytor schematu blokowego: biblioteka JS osadzana przez Alpine (rekomendacja: **Drawflow**; alternatywa Rete.js); decyzja zapisana w `docs/adr/0001-scenario-editor.md` | Do potwierdzenia |
| Z6 | Wyzwalacze scenariuszy w 1.5.0: ręczny, harmonogram (cron), webhook | Do potwierdzenia |
| Z7 | Kontenery: tylko **lokalny Docker** (socket); zdalne hosty Docker i Kubernetes poza zakresem. **Brak dowolnego terminala/exec** – tylko polecenia z białej listy adaptera (zgodnie z v1 §9.2) | **Do potwierdzenia** |
| Z8 | Eksport konfiguracji **domyślnie bez sekretów**; opcjonalnie sekrety szyfrowane hasłem. Treść pamięci (notatki) poza eksportem konfiguracji (jest osobny eksport Pamięci), opcjonalnie dołączana | Do potwierdzenia |
| Z9 | Nowy moduł Menu 1 **System** (tylko admin) zbiera: eksport/import, instrukcję pierwszej konfiguracji, aktualizacje, O aplikacji/Dziennik zmian | Przyjęte |
| Z10 | Plik reguł dla AI: `AGENTS.md` | Do potwierdzenia |
| Z11 | Tryb internetu „otwarty” (dowolne domeny publiczne) dopuszczalny, ale domyślnie wyłączony globalnie i wymaga uprawnienia | Do potwierdzenia |

---

## 2. Zmiany w układzie menu (v1 §5–6)

Układ 7 bloków **bez zmian**. Zmieniają się wyłącznie manifesty modułów:

| Menu 1 | Menu 2 (po zmianie) | Zmiana |
|--------|---------------------|--------|
| **Agenci AI** | Aktywni · Wszyscy agenci · Utwórz agenta · Szablony · **Skille** | nowa zakładka *Skille* (sekcja 3); formularz agenta zyskuje blok *Zdolności i pamięć* (sekcja 5) |
| **Czat** | Rozmowy · Nowa rozmowa · Archiwum | rozmowy grupowe z wieloma agentami (sekcja 4) |
| **Scenariusze** *(nowy)* | Lista · Uruchomienia · Szablony | edytor otwierany w osobnym oknie (sekcja 5/7) |
| **Integracje** | Hermes Agent · OpenClaw · Claude Code · Codex · Inne · **Kontenery** | nowa zakładka *Kontenery* (sekcja 7) |
| **System** *(nowy, admin)* | Eksport/Import · Instrukcja konfiguracji · Aktualizacje · O aplikacji | sekcje 6 i 8 |

Manifesty (`module.json`) zyskują opcjonalne pola:

```json
{
  "first_setup": "docs/first-setup.md",
  "config_sections": ["Modules\\Agents\\Config\\AgentsConfigSection"]
}
```

---

## 3. Skille – zakładka i magazyn

Rozszerza v1 §7.2.1. Rejestr `skills` staje się pełnym **magazynem skilli** z wersjonowaniem i własną zakładką.

### 3.1 UI: Agenci AI → Skille
- **Lista/siatka:** nazwa, typ, źródło, tagi, wersja, status, liczba agentów używających; filtry (typ, źródło, tag, status) i wyszukiwanie.
- **Szczegóły skilla:** opis (Markdown), schemat wejścia/wyjścia, historia wersji, lista agentów i scenariuszy, które go używają, przycisk **Testuj** (uruchomienie w sandboxie z przykładowym wejściem, wynik + log).
- **Dodaj skill** (kreator): (a) formularz ręczny, (b) upload paczki ZIP, (c) import z repozytorium Git (URL z allowlisty `SKILLS_GIT_ALLOWLIST`), (d) import z integracji (`listSkills()` z Hermes/OpenClaw – z podglądem i zatwierdzeniem, jak v1 §9.3), (e) import z pliku JSON/YAML.
- **Akcje:** edytuj (tworzy nową wersję), duplikuj, wycofaj (`deprecated`), usuń (soft delete; blokada gdy używany – z listą zależności), eksport paczki.
- **Przypisanie do agenta:** w formularzu agenta (wielokrotny wybór) oraz masowo z widoku skilla („Dodaj do agentów…”). Możliwość **przypięcia wersji** skilla (`pinned`) lub śledzenia najnowszej.

### 3.2 Format paczki skilla
```
<skill-key>/
├── skill.json        # manifest: key, name, description, type, version, input_schema, output_schema,
│                     # permissions[], runtime_compat[], tags[], entrypoint, checksum
├── SKILL.md          # instrukcje dla modelu (opcjonalnie)
└── resources/        # skrypty, szablony, pliki pomocnicze (opcjonalnie)
```
Typy: `tool`, `mcp`, `prompt`, `workflow`, **`package`** (nowy – paczka z zasobami). `runtime_compat` wskazuje, z którymi runtime'ami skill działa (`native`, `hermes`, `openclaw`, `claude_code`, `codex`); adapter implementuje `mapSkill(Skill $s): array`. Format wiążący dla runtime'ów zweryfikować w oficjalnej dokumentacji i zapisać w `docs/integrations/`.

### 3.3 Bezpieczeństwo skilli
- Kod ze skilli typu `tool`/`package`/`workflow` **nigdy nie jest wykonywany w procesie aplikacji** – wyłącznie w sandboxie (kontener/izolowany proces `integration-manager`, bez dostępu do sekretów hosta, z limitem czasu i zasobów i ruchem wychodzącym wg allowlisty).
- Paczki: limit rozmiaru (`SKILLS_MAX_PACKAGE_MB`), sumy kontrolne, ochrona przed zip-slip, skan struktury.
- Nowy skill ze źródła zewnętrznego ma status `pending_review`; aktywuje go użytkownik z uprawnieniem `skills.publish` po obejrzeniu podglądu (manifest + lista uprawnień, których żąda skill).
- Skill może żądać uprawnień (`internet`, `memory.read`, `memory.write`, `fs.sandbox`); agent bez odpowiedniej zdolności (sekcja 5) **nie uruchomi** skilla wymagającego tej zdolności – walidacja przy przypisaniu i przy wywołaniu.

### 3.4 Zmiany w modelu danych
| Tabela | Zmiana |
|--------|--------|
| `skills` | + `slug`, `status` (`draft`/`pending_review`/`active`/`deprecated`), `author`, `tags` (json), `readme_md`, `source_ref` (URL/ścieżka/integracja), `current_version_id`, `requires` (json: lista wymaganych zdolności) |
| `skill_versions` *(nowa)* | id, skill_id, version, definition (json), package_path, checksum, changelog, created_by, created_at |
| `agent_skill` | + `skill_version_id` (nullable = najnowsza), `enabled`, `sort` |
| `skill_runs` *(nowa)* | id, skill_id, version_id, agent_run_id, scenario_run_step_id, input_summary, status, duration_ms, error, created_at |

Przechowywanie paczek: dysk `skills` (`storage/app/skills`, konfigurowalny).

### 3.5 Uprawnienia i API
Uprawnienia: `skills.view`, `skills.create`, `skills.edit`, `skills.delete`, `skills.publish`, `skills.test`.
API: `GET/POST /api/v1/skills`, `GET/PUT/DELETE /api/v1/skills/{id}`, `GET/POST /api/v1/skills/{id}/versions`, `POST /api/v1/skills/{id}/test`, `POST /api/v1/skills/import`.

---

## 4. Czat z wieloma agentami

Rozszerza v1 §7.8 (tryb „grupowy” był tylko wspomniany – teraz jest w pełni zdefiniowany).

### 4.1 Funkcje
- Utworzenie rozmowy z 2..N agentami (`CHAT_GROUP_MAX_PARTICIPANTS`, domyślnie 8) **lub dodanie/usunięcie agenta w trakcie rozmowy** (przycisk „Uczestnicy” w nagłówku czatu). W strumieniu pojawia się komunikat systemowy („Agent X dołączył”).
- **Polityka kontekstu dla nowo dodanego agenta:** `full` (cała historia), `summary` (streszczenie wygenerowane przez wskazanego agenta/model), `last_n` (ostatnie N wiadomości), `none`. Wybierana przy dodawaniu.
- Każdy uczestnik ma wyróżnik (avatar, kolor, nazwa) przy wiadomościach; odpowiedzi kilku agentów strumieniowane **równolegle** (osobne bąbelki).
- Każdy agent respektuje **własne** ustawienia: pamięć, internet, skille, limity, kontekst (sekcja 5) – czat nie poszerza uprawnień agenta.
- Widok wywołań narzędzi/pamięci (v1 §7.8) per agent.
- Przycisk **Stop** dla całej rundy i dla pojedynczego agenta; „Pomiń agenta”; regeneracja odpowiedzi wybranego agenta.

### 4.2 Tryby orkiestracji (`conversations.orchestration`)
| Tryb | Działanie |
|------|-----------|
| `mention` *(domyślny)* | Odpowiadają tylko agenci wywołani przez `@slug`; brak wzmianki → agent domyślny (`lead`) |
| `broadcast` | Każda wiadomość użytkownika trafia do wszystkich uczestników równolegle |
| `round_robin` | Agenci odpowiadają kolejno, każdy widzi wypowiedzi poprzednich; do `max_rounds` rund |
| `moderator` | Wskazany agent-moderator zwraca decyzję JSON (`next_speaker`/`done`) i steruje kolejnością do `max_turns` |

Dialog agent↔agent odbywa się w ramach rundy, widoczny dla użytkownika.

### 4.3 Zabezpieczenia przed pętlami i kosztami
Limity per rozmowa: `max_turns` (domyślnie 20), `max_tokens_total`, `max_cost`; wykrywanie powtórzeń (ta sama treść ≥3×); przekroczenie → zatrzymanie z komunikatem. Limity agentów i kont (v1 §10.4) nadal obowiązują. Treści pochodzące od innych agentów są w kontekście oznaczane jako **niezaufane** (v1 §12 – prompt injection); narzędzia o skutkach ubocznych nadal wymagają zatwierdzenia przez człowieka.

### 4.4 Budowa kontekstu
`GroupChatOrchestrator` (moduł Chat) buduje dla każdego agenta: system prompt agenta + opis rozmowy i listę uczestników + transkrypt (wypowiedzi innych jako role `user` z prefiksem nazwy – lub natywnie, jeśli runtime wspiera wiele ról) + wiedzę z pamięci (jeśli włączona). Agenci działający w Hermes/OpenClaw/CLI otrzymują transkrypt przez `runTask()`. Przy `context_mode=stateless` (sekcja 5) agent dostaje tylko bieżącą wiadomość i bezpośrednio adresowany kontekst rundy – bez historii wcześniejszych rund.

### 4.5 Zmiany w modelu danych
| Tabela | Zmiana |
|--------|--------|
| `conversations` | + `mode` (`single`/`group`), `orchestration`, `lead_agent_id`, `moderator_agent_id`, `max_turns`, `max_rounds`, `limits` (json), `settings` (json) |
| `conversation_participants` *(nowa)* | id, conversation_id, agent_id, role (`member`/`lead`/`moderator`), join_context (`full`/`summary`/`last_n`/`none`), join_context_n, joined_at, left_at, position |
| `messages` | + `participant_id` (nullable), `turn_id`, `round`, `reply_to_message_id`, `kind` (`user`/`agent`/`system`) |

### 4.6 Realtime
Kanał prywatny `conversations.{id}`; zdarzenia: `message.delta`, `message.done`, `participant.joined|left`, `turn.started|finished`, `tool.called`, `memory.read`, `limit.reached` – każde z `participant_id`. Telemetria zasila Dashboard wg v1 §11.

### 4.7 API
`POST /api/v1/conversations` (z `participants[]`, `orchestration`), `POST/DELETE /api/v1/conversations/{id}/participants`, `POST /api/v1/conversations/{id}/messages`, `POST /api/v1/conversations/{id}/stop`. Uprawnienia: `chat.group.create`, `chat.group.manage`.

---

## 5. Zdolności agenta: internet, wiedza, pamięć wywołań

Rozszerza v1 §7.2 (pola *Dostęp do pamięci*, *Dostęp do internetu*). Do formularza agenta dochodzi blok **„Zdolności i pamięć”** z trzema niezależnymi ustawieniami.

### 5.1 Ustawienia

| Ustawienie | Wartości | Znaczenie |
|-----------|----------|-----------|
| **Internet** (`internet_mode`) | `off` / `allowlist` / `open` | Czy agent może korzystać z internetu. `allowlist` – tylko wskazane domeny (jak v1). `open` – dowolne domeny publiczne; wymaga `INTERNET_ALLOW_OPEN=true`, ustawienia globalnego i uprawnienia `agents.internet.open`. Ochrona SSRF działa zawsze |
| **Wiedza zgromadzona** (`memory_mode`, bez zmiany nazwy kolumny) | `none` / `read` / `readwrite` + kolekcje | Czy agent korzysta z wiedzy w pamięci wektorowej. W UI nazwa: „Wiedza zgromadzona (pamięć wektorowa)” |
| **Pamięć wywołań** (`context_mode`) | `stateful` / `stateless` (+ opcjonalnie `context_window_messages`) | `stateless` = **każde nowe wywołanie nie pamięta poprzednich** (brak historii rozmowy, brak poprzednich wyników; agent dostaje tylko system prompt, bieżące wejście i – jeśli włączona – wiedzę z pamięci). `stateful` = historia zgodnie z kontekstem rozmowy; opcjonalne okno ostatnich N wiadomości |

Uwaga: `stateless` dotyczy **historii dialogu/wywołań**, a nie wiedzy zgromadzonej – to dwie niezależne osie (agent może być bezstanowy i jednocześnie korzystać z wiedzy).

### 5.2 Hierarchia i nadpisywanie
- Wartości domyślne ustawia **agent** (i szablon agenta).
- **Konwersacja** (czat) i **węzeł scenariusza** mogą nadpisać ustawienia tylko w kierunku **bardziej restrykcyjnym** dla internetu i wiedzy (nie można poszerzyć uprawnień ponad konfigurację agenta). `context_mode` można nadpisać w obu kierunkach (nie jest ustawieniem bezpieczeństwa), np. węzeł scenariusza wymusza `stateless`.
- Skille wymagające zdolności, której agent nie ma (sekcja 3.3), są blokowane.

### 5.3 Egzekwowanie (nie tylko w UI)
- **Internet:** wszystkie wyjścia sieciowe agenta idą przez proxy egress z tokenem agenta; proxy sprawdza `internet_mode` i allowlistę; zdarzenia `internet.request` / `internet.blocked` trafiają do telemetrii i logów (v1 §7.7).
- **Wiedza:** dostęp wyłącznie przez `memory-service` z ACL (kolekcje agenta); `none` = brak narzędzia/wstrzykiwania wiedzy.
- **Pamięć wywołań:** `AgentContextBuilder` nie ładuje historii przy `stateless`; dla runtime'ów (Hermes/OpenClaw/CLI) adapter tworzy **nową sesję przy każdym wywołaniu** lub czyści stan. Kontrakt `capabilities()` (v1 §9.1) wzbogacony o klucze `stateless` i `internet_control`; gdy runtime nie gwarantuje zachowania, UI pokazuje ostrzeżenie, a `syncAgent()` stosuje najbliższy możliwy tryb (do zweryfikowania w dokumentacji i zapisania w `docs/integrations/`).

### 5.4 UI
Przełączniki z krótkim objaśnieniem pod każdym; na karcie agenta **podsumowanie efektywnych zdolności** (np. „Internet: allowlist · Wiedza: odczyt (2 kolekcje) · Wywołania: bez pamięci”). Te same ustawienia dostępne w szablonach agentów, w panelu konwersacji grupowej (nadpisanie) i w węźle `agent` scenariusza.

### 5.5 Zmiany w modelu danych
| Tabela | Zmiana |
|--------|--------|
| `agents` | + `internet_mode` (`off`/`allowlist`/`open`; migracja: `internet_enabled=false → off`, `true → allowlist`), `context_mode` (domyślnie `stateful`), `context_window_messages` (nullable). Kolumna `internet_enabled` pozostaje przez 1 wydanie jako przestarzała (synchronizowana) |
| `settings` | + klucz `allow_open_internet` (bool, domyślnie `false`) |

---

## 6. Instrukcja pierwszej konfiguracji

### 6.1 Cel
Przewodnik **krok po kroku „co skonfigurować i jak”** po instalacji: od zmiany hasła po pierwszego agenta, skille, scenariusz i kopię konfiguracji. Uzupełnia Setup Wizard (v1 §13.3) – wizard prowadzi przez minimum, instrukcja opisuje **pełną** konfigurację z wyjaśnieniami.

### 6.2 Forma
1. **W aplikacji:** System → Instrukcja konfiguracji. Spis kroków z paskiem postępu, każdy krok ma: cel, instrukcję (Markdown, zrzuty/diagramy opcjonalnie), przycisk **„Przejdź do ustawienia”** (deep link do właściwej zakładki), status (`do zrobienia`/`zrobione`/`pominięte`). Status wykrywany automatycznie tam, gdzie się da (np. „dodano konto dostawcy”), inaczej ręcznie.
2. **W repozytorium:** `docs/FIRST_SETUP.md` – **generowany** z tych samych fragmentów (komenda `php artisan docs:first-setup --build`), aby nie było dwóch źródeł prawdy.

### 6.3 Fragmenty modułowe
Każdy moduł dostarcza plik wskazany w `module.json` → `first_setup` (np. `modules/Agents/docs/first-setup.md`) z nagłówkiem YAML:

```yaml
---
key: agents.skills
title: Dodaj pierwszy skill
order: 340
since_version: 1.5.0
requires: [ai.accounts]        # kroki, które powinny być zrobione wcześniej
optional: true
detect: "App\\Setup\\Detectors\\HasSkill"   # opcjonalna klasa wykrywająca ukończenie
link: skills.index
---
Treść instrukcji w Markdown…
```
Rdzeń składa instrukcję z włączonych modułów, sortuje po `order`/`requires`; wyłączony moduł = jego kroki znikają. Teksty w `lang/pl` i `lang/en`.

### 6.4 Zawartość minimalna (kolejność domyślna)
1. Logowanie i zmiana hasła admina (opcjonalna, v1 A4) 2. Silnik wektorowy 3. Dostawcy i konta AI, modele, embeddingi 4. Integracje (Hermes/OpenClaw – instancje) 5. Kontenery – podłączenie i konfiguracja (sekcja 7) 6. Pamięć: kolekcje 7. Skille 8. Pierwszy agent: zdolności i pamięć (sekcja 5) 9. Czat, w tym rozmowa grupowa 10. Scenariusze 11. Użytkownicy i role 12. Backup: eksport konfiguracji (sekcja 8) 13. Aktualizacje i HTTPS.

### 6.5 Mechanizm „aktualizowana wraz ze zmianami” (reguła)
- Reguła z sekcji 0 pkt 7–9 jest zapisana w `AGENTS.md` i w szablonie PR.
- `php artisan docs:check` (uruchamiany w CI i w `compat-check.sh`) sprawdza: (a) każdy włączony moduł z uprawnieniami/ustawieniami ma `first_setup`, (b) w PR zmieniającym pliki modułu istnieje zmiana w jego fragmencie **lub** wpis `docs-not-needed: <powód>` w opisie PR, (c) `CHANGELOG.md` zawiera wpis w `[Unreleased]`, (d) `since_version` ≤ bieżąca wersja, (e) brak martwych linków `link:`.
- Przy aktualizacji (`update.sh`) nowe kroki (`since_version` > poprzednia wersja instancji) są oznaczane w UI jako **„Nowe w tej wersji”** z powiadomieniem na Dashboardzie.

### 6.6 Dane
Tabela `setup_progress` (user_id nullable = globalnie, step_key, status, completed_at, note). Uprawnienie: `system.setup.view`.

---

## 7. Integracje: podłączanie do kontenerów i ich konfiguracja

Rozszerza v1 §9 (nowa zakładka **Integracje → Kontenery**). Uzupełnia provisioning z v1 §9.5: tam AgentHub **tworzy** instancje, tu **podłącza (adoptuje) istniejące kontenery** Docker i konfiguruje je ręcznie lub automatycznie.

### 7.1 Przepływ
1. **Wykrywanie** – `integration-manager` (jedyny z dostępem do socketu Dockera) listuje kontenery; domyślnie pokazuje kandydatów rozpoznanych po sygnaturach adapterów (obraz, etykiety, porty: Hermes Agent, OpenClaw, Ollama, inne) i z etykietą `agenthub.managed=true`; przełącznik „Pokaż wszystkie”.
2. **Podłączenie (adopt)** – użytkownik wybiera kontener i **tryb**: `observe` (tylko status/logi), `configure` (konfiguracja), `managed` (konfiguracja + start/stop/restart). Opcjonalnie dołączenie kontenera do sieci `agenthub-net` (akcja `network connect`, odwracalna), aby miał dostęp do `llm-gateway` i `memory-service`.
3. **Rozpoznanie typu** – `detected_type` (`hermes`/`openclaw`/`ollama`/`other`), wersja, zgodność z macierzą `config/compat.php`.
4. **Konfiguracja** – wybór ścieżki:
   - **Ręczna:** formularz generowany z `configSchema()` adaptera, **podgląd różnic (diff)** przed zapisem, zapis + restart (opcjonalny) + weryfikacja.
   - **Automatyczna:** wybór **profilu auto-konfiguracji** (`autoconfig_profiles`); system sam wykonuje kroki z profilu.
5. **Weryfikacja** – `healthProbe()` + wiadomość testowa; rejestracja w rejestrze usług (v1 §9.5.8); widoczność na Dashboardzie.

### 7.2 Profil auto-konfiguracji
Deklaratywna lista kroków (JSON, wykonywana wyłącznie przez wbudowane akcje – bez dowolnych poleceń):

| Krok | Działanie |
|------|-----------|
| `select_model` | Wybór modelu: wskazany ręcznie / pierwszy zgodny z wymaganiami (chat, tools, embeddings) z `account_models` / rekomendowany. Opcjonalnie konto lub **pula kont** (v1 §10.4) |
| `set_endpoint` | Ustawienie adresu `llm-gateway` i `memory-service` w konfiguracji kontenera |
| `issue_token` | Wygenerowanie tokenu instancji (jak v1 §9.5.7) – kontener **nie dostaje surowych kluczy dostawców** |
| `set_env` / `write_config` | Zapis zmiennych lub pliku konfiguracji z szablonu, wyłącznie do ścieżek z `configWritablePaths()` adaptera |
| `apply_capabilities` | Przeniesienie ustawień z sekcji 5 (internet/wiedza/kontekst) i listy skilli |
| `pull_model` *(Ollama)* | Pobranie modelu przez API Ollamy (tylko model z allowlisty/wybrany przez użytkownika); utworzenie lub aktualizacja konta dostawcy `ollama` w AgentHub |
| `restart` | Restart kontenera (jeśli tryb `managed`) |
| `verify` | Health-check + test promptu; niepowodzenie → **rollback** do zapisanej kopii konfiguracji |

Profile domyślne dostarczają adaptery (np. „Hermes – standard”, „OpenClaw – standard”, „Ollama – podłącz jako dostawcę”); użytkownik może je kopiować i edytować. Całość wykonywana jako zadanie w kolejce z logiem na żywo (`provisioning_jobs`, jak v1 §9.5).

### 7.3 Rozszerzenie kontraktu adaptera (v1 §9.1, §9.5.5)
```php
public function containerSignatures(): array;                       // obraz/etykiety/porty do wykrywania
public function inspectContainer(ContainerRef $c): ContainerInfo;
public function configWritablePaths(): array;                       // biała lista ścieżek zapisu
public function execAllowlist(): array;                             // szablony poleceń dozwolonych w kontenerze
public function configureContainer(ContainerRef $c, array $config): ConfigResult;
public function autoConfigure(ContainerRef $c, AutoConfigProfile $p): ConfigResult;
public function listContainerModels(ContainerRef $c): array;        // modele dostępne w kontenerze (np. Ollama)
```

### 7.4 Bezpieczeństwo
- Dostęp do Dockera tylko dla `integration-manager` (v1 §13.5, użytkownik `agenthub-runner`). Dozwolone operacje: list, inspect, logs, start/stop/restart (tryb `managed`), `network connect/disconnect` (tylko adoptowane), kopiowanie plików konfiguracji do `configWritablePaths()`.
- **Brak dowolnego terminala i dowolnego `exec`** – tylko polecenia z `execAllowlist()` (zgodnie z v1 §9.2). Obrazy nie są pobierane spoza allowlisty z manifestów.
- Kontenery nieadoptowane są dla AgentHub **nietykalne**.
- Sekrety przekazywane przez plik/zmienną z uprawnieniami 0600, nigdy w etykietach ani w logach; przed każdą zmianą **kopia poprzedniej konfiguracji** (rollback).
- Ostrzeżenie w UI, gdy kontener jest zarządzany przez `docker compose` (odtworzenie kontenera może nadpisać zmiany).
- Uprawnienia: `integrations.containers.view`, `integrations.containers.adopt`, `integrations.containers.configure`, `integrations.containers.manage`. Pełny audyt (kto, kontener, diff konfiguracji).

### 7.5 Model danych
| Tabela | Kolumny |
|--------|---------|
| `integration_containers` *(nowa)* | id, integration_id (nullable), instance_id (nullable), container_id, name, image, image_digest, labels (json), ports (json), networks (json), detected_type, mode (`observe`/`configure`/`managed`), status, autoconfig_profile_id (nullable), auto_configure (bool), config_snapshot (enc json), last_inspected_at, last_error, adopted_by |
| `autoconfig_profiles` *(nowa)* | id, name, target_type, steps (json), is_default, created_by |
| `container_config_runs` *(nowa)* | id, container_id, profile_id (nullable), kind (`manual`/`auto`), diff (json), status, log, started_at, finished_at |

### 7.6 UI
**Integracje → Kontenery:** tabela (nazwa, obraz, typ, tryb, status, sieć, akcje) · przycisk **„Wykryj kontenery”** · kreator **„Podłącz”** (kroki 1–5 z 7.1) · szczegóły kontenera (logi na żywo, historia konfiguracji z diffem, przywróć poprzednią) · zarządzanie profilami auto-konfiguracji. W Setup Wizardzie (v1 §13.3, krok „Integracje”) opcja „Wykryj i skonfiguruj istniejące kontenery”.

---

## 8. Eksport i import całej konfiguracji (admin)

### 8.1 Zasady
- Dostępne w **System → Eksport/Import**, CLI i API; wyłącznie dla roli `admin` (uprawnienia `system.config.export`, `system.config.import`; domyślnie tylko `admin`).
- Obejmuje **konfigurację**, nie dane operacyjne. Poza zakresem: `llm_calls`, `telemetry_events`, `audit_logs`, `agent_runs`, wiadomości czatu, pliki wektorów. Treść pamięci (notatki) – opcjonalnie (`include_memory`), domyślnie wyłączona.

### 8.2 Zakres (sekcje konfiguracji)
| Sekcja | Zawartość |
|--------|-----------|
| `settings` | ustawienia systemowe, motyw/język domyślne, flagi |
| `users` | użytkownicy, role, uprawnienia (hasła jako hash; 2FA wyłączone po imporcie) |
| `ai` | dostawcy, konta (bez sekretów domyślnie), modele, cennik, pule i reguły routingu, limity, embeddingi |
| `integrations` | integracje, profile CLI, **instancje** (konfiguracja, nie dane), **kontenery i profile auto-konfiguracji** |
| `agents` | agenci (w tym zdolności z sekcji 5), szablony agentów |
| `skills` | skille wraz z wersjami i paczkami |
| `scenarios` | scenariusze, wersje, wyzwalacze |
| `memory_config` | kolekcje, uprawnienia, retencja (bez notatek, chyba że `include_memory`) |
| `modules` | lista i stan włączenia modułów, ich konfiguracja |
| `setup` | postęp instrukcji konfiguracji |

### 8.3 Kontrakt rozszerzalny
Każdy moduł rejestruje sekcje przez `module.json` → `config_sections`:

```php
interface ConfigSection
{
    public function key(): string;                       // 'agents', 'skills', ...
    public function schemaVersion(): int;                // wersja formatu sekcji
    public function dependsOn(): array;                  // kolejność importu (np. skills przed agents)
    public function secretFields(): array;               // ścieżki pól będących sekretami
    public function export(ExportOptions $o): iterable;  // strumieniowo
    public function validate(array $data): ValidationResult;
    public function plan(array $data, ImportOptions $o): ImportPlan;   // dry-run: create/update/skip/conflict
    public function import(ImportPlan $p): ImportResult;
}
```
Dzięki temu nowy moduł automatycznie uczestniczy w eksporcie. Test CI sprawdza, że **każda tabela konfiguracyjna** jest pokryta jakąś sekcją (lista wyjątków jawna).

### 8.4 Format pliku
`agenthub-config-<wersja>-<data>.zip` (opcjonalnie szyfrowany):
```
manifest.json        # app_version, schema_versions per sekcja, data, sekcje, include_secrets, include_memory,
                     # checksums (SHA-256), identyfikator instancji źródłowej
sections/<klucz>.json
assets/skills/…      # paczki skilli
secrets.enc          # tylko gdy wybrano eksport sekretów
```
Powiązania po **stabilnych kluczach** (`uuid`/`slug`), nie po autoinkrementowanych `id`.

**Sekrety:** domyślnie **pomijane** (po imporcie konta/integracje mają status „wymaga uzupełnienia poświadczeń”). Opcja „dołącz sekrety” → `secrets.enc` szyfrowany hasłem podanym przez admina (Argon2id + XChaCha20-Poly1305/libsodium); hasło nigdzie niezapisywane; ostrzeżenie w UI. Nigdy plaintext. Sekrety i `APP_KEY` nie trafiają do logów ani audytu.

### 8.5 Import
1. Upload pliku (limit `CONFIG_EXPORT_MAX_MB`) → weryfikacja sum kontrolnych, hasła i **zgodności wersji** (import z nowszej wersji aplikacji blokowany; ze starszej – migracja formatu sekcji przez `ConfigSection`).
2. **Dry-run:** raport (utworzy / zaktualizuje / pominie / konflikty) z diffem; użytkownik zaznacza sekcje i pozycje do zaimportowania.
3. **Tryb:** `merge` (dodaj i zaktualizuj, nic nie usuwaj – domyślny) · `overwrite` (zastąp wybrane sekcje) · `new_only` (tylko brakujące). Konflikty rozwiązywane regułą wybraną przez admina (zachowaj lokalne/nadpisz).
4. **Automatyczny backup** bieżącej konfiguracji przed zmianą (`storage/backups/config-<timestamp>.zip`) i możliwość **cofnięcia** ostatniego importu.
5. Wykonanie w kolejce, w kolejności `dependsOn()`, **w transakcji per sekcja**; błąd → rollback sekcji, raport.
6. Po imporcie: stany zasobów zewnętrznych nie są automatycznie uruchamiane – instancje i kontenery z `desired_state` importowane jako `stopped` (opcja „uzgodnij stan”), konta bez sekretów wyłączone do uzupełnienia, użytkownicy z wymuszonym ponownym ustawieniem 2FA. Wynik w audycie (kto, plik, sekcje, liczby).
7. Przypadek użycia „migracja na nowy serwer” opisany w `docs/UPDATE.md` (instalacja → import → uzupełnienie sekretów).

### 8.6 CLI i API
```
php artisan config:export [--sections=a,b] [--with-secrets] [--with-memory] [--output=…]
php artisan config:import <plik> [--mode=merge|overwrite|new_only] [--dry-run] [--sections=…]
POST /api/v1/system/config/export · POST /api/v1/system/config/import · GET /api/v1/system/config/imports/{id}
```

### 8.7 Dane
Tabela `config_transfers` (id, type `export`/`import`, user_id, sections (json), mode, status, summary (json), file_path, checksum, created_at, finished_at).

---

## 9. Scenariusze (edytor blokowy z telemetrią)

Nowy moduł `modules/Scenarios` (Menu 1: **Scenariusze**).

### 9.1 Cel
Wizualne definiowanie przepływów pracy wielu agentów jako **schematu blokowego**, ich uruchamianie i **podgląd na żywo z telemetrią** na samym schemacie.

### 9.2 Okno edytora
Edytor otwiera się w **osobnym oknie** (route `/scenarios/{id}/editor`, własny layout pełnoekranowy; przycisk „Otwórz w nowym oknie przeglądarki” – `window.open`, oba widoki synchronizują stan przez Reverb). Elementy:
- **Paleta węzłów** (przeciągnij i upuść), **kanwa** (zoom, przesuwanie, minimapa, siatka, undo/redo, kopiuj/wklej, autozapis wersji roboczej),
- **Panel konfiguracji węzła** (po prawej), **panel walidacji** (błędy/ostrzeżenia z podświetleniem węzła),
- **Pasek akcji:** Zapisz szkic · Opublikuj wersję · Uruchom (z danymi testowymi) · Dry-run (agenci zamockowani, bez kosztów) · Eksport/Import JSON,
- **Przełącznik trybu:** *Edycja* ↔ *Telemetria na żywo* ↔ *Odtwarzanie zakończonego uruchomienia*.

### 9.3 Typy węzłów
| Węzeł | Opis |
|-------|------|
| `start` | Wyzwalacz: ręczny (formularz wejścia), harmonogram (cron), webhook (token) |
| `agent` | Uruchomienie agenta: agent, szablon promptu z zmiennymi `{{ }}`, mapowanie wejścia/wyjścia, timeout, ponowienia, **nadpisania zdolności** (sekcja 5.2), model/konto opcjonalnie |
| `skill` | Wywołanie skilla (sekcja 3) w sandboxie |
| `memory` | Odczyt (zapytanie wektorowe) / zapis do kolekcji |
| `condition` | Rozgałęzienie wg wyrażenia (bezpieczny język wyrażeń – np. Symfony ExpressionLanguage lub JSONLogic; **zakaz `eval`**) lub decyzji agenta |
| `parallel` / `join` | Równoległe gałęzie i scalenie (strategie: wszystkie / pierwsza / większość) |
| `loop` | Pętla `foreach`/`while` z obowiązkowym limitem iteracji |
| `human` | Zatwierdzenie lub wprowadzenie danych przez użytkownika (pauza z powiadomieniem) |
| `transform` | Szablon/mapowanie danych bez wywołania modelu |
| `delay` | Opóźnienie |
| `subscenario` | Wywołanie innego opublikowanego scenariusza |
| `end` | Zakończenie (status `success`/`failed`) i wynik |

Zmienne: kontekst runu `vars` (wejście, wyniki węzłów); referencje `{{ nodes.<id>.output }}`. Szablony renderowane w sandboxie (bez wykonywania kodu).

### 9.4 Definicja grafu (JSON, wersjonowana)
```json
{
  "schema": 1,
  "nodes": [{"id":"n1","type":"start","position":{"x":0,"y":0},"data":{}},
            {"id":"n2","type":"agent","position":{"x":240,"y":0},"data":{"agent":"researcher","prompt":"…","context_mode":"stateless","timeout_s":300,"retries":1}}],
  "edges": [{"id":"e1","source":"n1","sourcePort":"out","target":"n2","targetPort":"in","condition":null}],
  "variables": {}, "settings": {"max_parallel":5,"budget":{"max_cost":5.0,"max_tokens":200000}}
}
```
Walidacja przy publikacji: dokładnie jeden `start`, wszystkie ścieżki kończą się `end`, brak węzłów nieosiągalnych, cykle tylko przez `loop`, typy portów zgodne, limit `SCENARIO_MAX_NODES` (200), istniejące i aktywne agenci/skille, zgodność zdolności (np. węzeł `skill` wymagający internetu przy agencie bez internetu = błąd).

### 9.5 Silnik wykonawczy
`ScenarioEngine` działa na kolejce (Horizon): stan runu i kroków trwały w bazie, kroki **idempotentne i wznawialne po restarcie**, pauza/wznowienie/zatrzymanie, ponowienie kroku, uruchomienie od wybranego węzła, limity równoległości (`SCENARIO_MAX_PARALLEL`), timeout runu, **budżety** (koszt/tokeny) z zatrzymaniem po przekroczeniu. Run jest przypięty do **opublikowanej wersji** scenariusza (edycja szkicu nie wpływa na trwające runy). Każdy krok agenta tworzy normalny `agent_run` (zasila Dashboard i Logi) z `scenario_run_id` i `node_id`.

### 9.6 Telemetria na schemacie
W trybie *Telemetria na żywo* (kanał prywatny `scenarios.{run_id}`, aktualizacje ≤1 s):
- **Stan węzła** kolorem i ikoną: `pending` · `running` (animacja) · `success` · `failed` · `skipped` · `waiting` (human) · `retrying`; krawędzie animowane na aktywnej ścieżce,
- **Plakietka na węźle:** agent i jego status (idle/working/error), tokeny in/out, czas, koszt, liczba wywołań narzędzi, numer próby,
- **Panel węzła (klik):** wejście i wyjście kroku, strumień zdarzeń agenta (`tool.called`, `memory.read`, `internet.request`, `llm.call` – v1 §11), użyte konto/model, błędy,
- **Pasek podsumowania runu:** status, czas, suma tokenów i kosztów, aktywne węzły, postęp (% ścieżki),
- **Oś czasu (Gantt)** kroków oraz **odtwarzanie** zakończonego runu (krok po kroku, z suwakiem),
- Agregaty per scenariusz (średni czas, koszt, wskaźnik błędów) widoczne na liście i jako widżet Dashboardu.
Zdarzenia wpisują `scenario_run_id`, `node_id` do `telemetry_events` i `llm_calls`; trace-id propagowany jak w v1 §11.

### 9.7 Model danych
| Tabela | Kolumny |
|--------|---------|
| `scenarios` | id, slug, name, description, status (`draft`/`published`/`archived`), current_version_id, created_by, soft deletes |
| `scenario_versions` | id, scenario_id, version, graph (json), draft (bool), changelog, published_by, published_at |
| `scenario_triggers` | id, scenario_id, type (`manual`/`cron`/`webhook`), config (json), token_hash, enabled, last_fired_at |
| `scenario_runs` | id, scenario_id, version_id, status, trigger_type, input (json), output (json), vars (json), started_at, finished_at, tokens_in, tokens_out, cost, error, created_by |
| `scenario_run_steps` | id, run_id, node_id, node_type, status, attempt, input, output, agent_run_id (nullable), skill_run_id (nullable), tokens_in, tokens_out, cost, started_at, finished_at, error |
| `telemetry_events`, `llm_calls`, `agent_runs` | + `scenario_run_id`, `node_id` (nullable, indeksowane) |

### 9.8 Uprawnienia i API
Uprawnienia: `scenarios.view|create|edit|publish|run|delete`. API: `/api/v1/scenarios`, `/scenarios/{id}/versions`, `POST /scenarios/{id}/run`, `/scenario-runs/{id}` (+ `/steps`, `/stop`, `/pause`, `/resume`), webhook `POST /api/v1/hooks/scenarios/{token}`.

---

## 10. Pozostałe zmiany techniczne

### 10.1 Zmienne `.env` (dodatki do v1 §21)
```dotenv
# 1.5.0
SKILLS_DISK=skills
SKILLS_MAX_PACKAGE_MB=20
SKILLS_GIT_ALLOWLIST=github.com
CHAT_GROUP_MAX_PARTICIPANTS=8
CHAT_GROUP_MAX_TURNS=20
INTERNET_ALLOW_OPEN=false
SCENARIO_MAX_NODES=200
SCENARIO_MAX_PARALLEL=5
SCENARIO_RUN_TIMEOUT=3600
CONTAINERS_DISCOVERY=true
DOCKER_HOST=unix:///var/run/docker.sock   # dostępny wyłącznie dla integration-manager
CONFIG_EXPORT_MAX_MB=200
```

### 10.2 Migracje i aktualizacja z 1.0.x
- Migracje addytywne; backfill: `agents.internet_mode`, `agents.context_mode=stateful`, `skill_versions` (wersja bieżąca każdego skilla = wersja 1), `conversations.mode=single`.
- `compat-check.sh` rozszerzony o: dostępność socketu Dockera dla `integration-manager` (jeśli `CONTAINERS_DISCOVERY=true`), zgodność modułów z `core_compat`, symulację migracji (`--pretend`), obecność nowych zmiennych.
- `update.sh` po aktualizacji uruchamia `agenthub:selftest` rozszerzony o: odczyt skilla, rozmowę grupową z 2 agentami (mock), uruchomienie prostego scenariusza (mock), eksport konfiguracji i import `--dry-run`.

### 10.3 Nowe uprawnienia (zbiorczo)
`skills.*` · `chat.group.create|manage` · `agents.internet.open` · `scenarios.*` · `integrations.containers.view|adopt|configure|manage` · `system.config.export|import` · `system.setup.view`. Dodać do `RolesAndPermissionsSeeder` (idempotentnie); `admin` – wszystkie, `operator` – `skills.view|test`, `chat.group.*`, `scenarios.view|run`, `viewer` – widoki.

### 10.4 Rejestr API (v1 §16)
Nowe zasoby: `skills`, `skill-versions`, `conversation-participants`, `scenarios`, `scenario-runs`, `integration-containers`, `autoconfig-profiles`, `system/config`. OpenAPI i testy kontraktowe aktualizowane.

---

## 11. Testy i kryteria jakości (uzupełnienie v1 §18)

- **Skille:** dodanie ręczne/ZIP/Git/z integracji, wersjonowanie i przypięcie, blokada skilla wymagającego zdolności, zip-slip, sandbox (brak dostępu do sekretów), test `pending_review`.
- **Czat grupowy:** każdy tryb orkiestracji, dołączenie/usunięcie agenta w trakcie, polityki kontekstu, limity pętli i kosztów, równoległy streaming, brak poszerzania uprawnień agenta.
- **Zdolności:** `internet_mode` (off/allowlist/open + SSRF) wymuszane na proxy; `memory_mode` wymuszane w `memory-service`; `stateless` – dowód, że drugie wywołanie nie zawiera danych z pierwszego (w tym przez adaptery); reguła „tylko zawężanie” dla nadpisań.
- **Scenariusze:** walidacja grafu (każda reguła z 9.4), wznowienie po restarcie workera, równoległość i `join`, pętle z limitem, `human` (pauza), budżety, wersje (edycja szkicu nie zmienia trwającego runu), telemetria na żywo (zdarzenia → stan węzłów), odtwarzanie runu; E2E w Playwright: zbuduj scenariusz w edytorze → opublikuj → uruchom → obserwuj telemetrię.
- **Kontenery:** wykrywanie, adopt w 3 trybach, konfiguracja ręczna z diffem, auto-konfiguracja (w tym wybór modelu, `pull_model` na mocku Ollama), rollback po nieudanym `verify`, brak możliwości `exec` poza allowlistą, brak ingerencji w kontenery nieadoptowane, brak sekretów w etykietach/logach.
- **Eksport/import:** round-trip (eksport → czysta instancja → import → **identyczny** stan konfiguracji), tryby merge/overwrite/new_only, dry-run bez zmian w bazie, rollback sekcji po błędzie, import starszego formatu, odmowa importu nowszego, szyfrowanie sekretów (zły hasło = błąd), test kompletności (każda tabela konfiguracyjna pokryta sekcją), dostęp tylko dla admina.
- **Dokumentacja:** `docs:check` – testy dodatnie i ujemne (PR bez fragmentu instrukcji ma failować); budowa `docs/FIRST_SETUP.md` deterministyczna.
- Pokrycie krytycznych ścieżek ≥ 80 %; CI blokuje merge (Pint/PHPStan/Pest/docs:check).

---

## 12. Kryteria akceptacji 1.5.0 (Definition of Done)

- [ ] Aktualizacja 1.0.x → 1.5.0 przez `update.sh` kończy się powodzeniem (w tym scenariusz nieudanej aktualizacji → rollback).
- [ ] **Skille:** zakładka *Agenci AI → Skille* działa; skill można dodać czterema metodami, wersjonować, przypisać agentom i przetestować w sandboxie.
- [ ] **Czat grupowy:** rozmowa z ≥3 agentami w każdym z 4 trybów; dodanie/usunięcie agenta w trakcie; limity chronią przed pętlą.
- [ ] **Scenariusze:** edytor blokowy w osobnym oknie; opublikowany scenariusz z agentami, warunkiem, równoległością i krokiem `human` uruchamia się, a telemetria (stany węzłów, tokeny, koszty, zdarzenia agentów) widoczna jest na schemacie na żywo i w odtwarzaniu.
- [ ] **Zdolności agenta:** internet (off/allowlist/open), wiedza (none/read/readwrite) i pamięć wywołań (stateful/stateless) konfigurowalne per agent, egzekwowane poza UI i możliwe do nadpisania tylko w sposób zawężający.
- [ ] **Instrukcja pierwszej konfiguracji:** dostępna w aplikacji i jako `docs/FIRST_SETUP.md`, generowana z fragmentów modułów; `docs:check` w CI; reguła zapisana w `AGENTS.md` i szablonie PR; po aktualizacji pokazuje „Nowe w tej wersji”.
- [ ] **Kontenery:** wykrycie, podłączenie i konfiguracja (ręczna i automatyczna, w tym wybór modelu) istniejących kontenerów Hermes/OpenClaw/Ollama z rollbackiem; brak dowolnego exec.
- [ ] **Eksport/import:** admin eksportuje i importuje całą konfigurację (dry-run, tryby, backup, cofnięcie, opcjonalne szyfrowane sekrety); round-trip daje identyczny stan.
- [ ] Nowe uprawnienia w seederach; audyt wszystkich nowych akcji; OpenAPI zaktualizowane.
- [ ] `CHANGELOG.md` zawiera wpis 1.5.0 (wzór w sekcji 14); dokumentacja (`ARCHITECTURE.md`, `MODULES.md`, `UPDATE.md`, `SECURITY.md`, `integrations/*.md`) zaktualizowana.

---

## 13. Plan realizacji (etapy po 1.0.0)

| Etap | Zakres | Rezultat |
|------|--------|----------|
| **11. Zdolności agenta + kontrakt eksportu** | Sekcja 5 (migracje, egzekwowanie, UI), interfejs `ConfigSection` i rejestracja w manifeście, `AGENTS.md`, szablon PR, szkielet `docs:check` | Fundament, na którym budują pozostałe etapy |
| **12. Skille** | Sekcja 3 | Magazyn i zakładka skilli |
| **13. Czat grupowy** | Sekcja 4 | Rozmowy wieloagentowe |
| **14. Scenariusze** | Sekcja 9 (model, silnik, edytor, telemetria) | Edytor blokowy z telemetrią |
| **15. Kontenery** | Sekcja 7 | Adopcja i auto-konfiguracja kontenerów |
| **16. Eksport/import** | Sekcja 8 (`ConfigSection` dla wszystkich modułów) | Pełna migracja konfiguracji |
| **17. Instrukcja konfiguracji + wydanie** | Sekcja 6 (agregacja fragmentów, UI, budowa `FIRST_SETUP.md`), testy E2E, hardening, aktualizacja dokumentacji, CHANGELOG | Wydanie 1.5.0 |

Fragmenty instrukcji (sekcja 6.3) i sekcje eksportu (8.3) powstają **w każdym etapie 11–16**, nie na końcu – zgodnie z regułą z sekcji 0. Po każdym etapie: testy zielone, wpis w CHANGELOG, aktualizacja dokumentacji.

---

## 14. Wzór wpisu w CHANGELOG.md

```markdown
## [1.5.0] – RRRR-MM-DD

### Dodano
- **Temat:** Skille – zakładka i magazyn
  **Opis:** Dodawanie skilli (ręcznie, ZIP, Git, import z integracji), wersjonowanie, przypisywanie do agentów, test w sandboxie.
- **Temat:** Czat z wieloma agentami
  **Opis:** Rozmowy grupowe w trybach mention/broadcast/round_robin/moderator, dodawanie agentów w trakcie rozmowy, limity pętli i kosztów.
- **Temat:** Scenariusze
  **Opis:** Edytor schematów blokowych w osobnym oknie z telemetrią agentów na żywo i odtwarzaniem uruchomień.
- **Temat:** Zdolności agenta
  **Opis:** Konfigurowalny internet (off/allowlist/open), wiedza zgromadzona oraz tryb bezstanowy wywołań.
- **Temat:** Instrukcja pierwszej konfiguracji
  **Opis:** Przewodnik w aplikacji i w docs/FIRST_SETUP.md, automatycznie składany z modułów; reguła aktualizacji w AGENTS.md i CI.
- **Temat:** Kontenery w Integracjach
  **Opis:** Wykrywanie i podłączanie istniejących kontenerów oraz ich konfiguracja ręczna lub automatyczna (w tym wybór modelu).
- **Temat:** Eksport i import konfiguracji
  **Opis:** Admin eksportuje/importuje całą konfigurację z dry-run, trybami scalania i opcjonalnie szyfrowanymi sekretami.

### Zmieniono
- **Temat:** Pole „internet” agenta
  **Opis:** `internet_enabled` zastąpione przez `internet_mode` (migracja automatyczna).

### Bezpieczeństwo
- **Temat:** Egzekwowanie zdolności agenta poza UI
  **Opis:** Proxy egress, ACL pamięci i sandbox skilli wymuszają ustawienia niezależnie od interfejsu.
```

---

## 15. Ryzyka i decyzje (uzupełnienie v1 §23)

1. **Formaty skilli i sesji w Hermes Agent/OpenClaw** – zweryfikować w oficjalnej dokumentacji (mapowanie skilli, możliwość pracy bezstanowej); do czasu weryfikacji adaptery na mocku, a UI ostrzega przy braku gwarancji `stateless`.
2. **Adopcja kontenerów = uprawnienia nad cudzymi usługami** – ograniczone do trybu wybranego przez admina, operacji z białej listy i kopii zapasowej konfiguracji; kontenery zarządzane przez compose mogą cofać zmiany.
3. **Koszty i pętle w czacie grupowym oraz scenariuszach** – obowiązkowe limity (tury, tokeny, koszt) i zatrzymanie awaryjne.
4. **Wykonywanie kodu ze skilli** – wyłącznie w sandboxie; skille z zewnątrz wymagają zatwierdzenia.
5. **Eksport sekretów** – domyślnie wyłączony; szyfrowanie hasłem; odpowiedzialność za hasło po stronie admina.
6. **Złożoność edytora blokowego** – wybór biblioteki (Z5) zapisać w ADR; granica 1.5.0: bez edycji współbieżnej wielu użytkowników (blokada edycji – ostatnia zapisana wersja szkicu, ostrzeżenie o konflikcie).
7. **Rozrost modelu `agents`** – trzy osie zdolności trzymać w jednym, jasno opisanym bloku UI i jednej klasie polityki (`AgentCapabilityPolicy`), aby egzekwowanie było w jednym miejscu.

---

## 16. Pytania otwarte do zamawiającego

1. **Numeracja:** czy 1.5.0 następuje bezpośrednio po 1.0.0, czy istnieją wydania pośrednie (1.1–1.4) z własnymi zmianami? (Z1)
2. **Format skilli:** czy ma być zgodny z konkretnym standardem (np. katalog z `SKILL.md`, format skilli Hermes/OpenClaw), czy wystarczy własny format z mapowaniem? (Z3)
3. **Pamięć wywołań:** czy „nie pamiętać poprzedniego wywołania” oznacza wyłącznie brak historii rozmowy, czy także brak zapisów agenta do pamięci wektorowej? (przyjęto: tylko historia; zapisy do wiedzy sterowane osobno przez `memory_mode`) (sekcja 5)
4. **Scenariusze:** czy „oddzielne okno” ma być pełnoekranowym widokiem, osobnym oknem przeglądarki, czy obie opcje? Które wyzwalacze są potrzebne w 1.5.0? (Z4, Z6)
5. **Kontenery:** czy potrzebny jest interaktywny terminal/dowolny `exec` w kontenerach (domyślnie: nie, ze względów bezpieczeństwa), oraz czy kontenery na **zdalnych** hostach Docker mają wejść do zakresu? (Z7)
6. **Eksport:** czy dołączać sekrety (szyfrowane hasłem) i treść pamięci, czy wystarczą opcje domyślne? (Z8)
7. **Internet „open”:** czy dopuszczamy tryb dowolnych domen publicznych, czy tylko allowlistę? (Z11)
8. **Plik reguł:** czy używane narzędzie AI czyta `AGENTS.md`, czy inną nazwę (`CLAUDE.md`, `.cursor/rules`, …)? (Z10)
