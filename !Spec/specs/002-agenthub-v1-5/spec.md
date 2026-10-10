# Feature Specification: AgentHub Platform v1.5 (Aktualizacja 1.5.0)

**Feature Name**: `002-agenthub-v1-5`  
**Source Specification**: [`Agenthub_v1.5.md`](../../Agenthub_v1.5.md) (rozszerzenie [`AGENT_HUB_SPEC_1.md`](../../AGENT_HUB_SPEC_1.md))  
**Status**: Ready for Planning & Implementation  

---

## 1. Cel i zakres biznesowy

Wydanie **1.5.0** platformy **AgentHub** stanowi bezpośrednie rozwinięcie wersji bazowej 1.0.0. Wprowadza kluczowe rozszerzenia orkiestracji, modularności i bezpieczeństwa w 7 głównych obszarach funkcjonalnych:

1. **Magazyn i zarządzanie Skillami (Skills Store & Sandbox)**:
   - Przekształcenie rejestru skilli w pełnoprawny magazyn z dedykowaną zakładką w *Agenci AI → Skille*.
   - Wersjonowanie skilli, przypinanie wersji per agent, instalacja z 4 źródeł (ręcznie, ZIP, Git URL, import z Hermes/OpenClaw).
   - Ścisła izolacja wykonawcza: skille wykonywane wyłącznie w procesie/kontenerze sandbox (zero dostępu do sekretów aplikacji).

2. **Czat grupowy z wieloma agentami (Multi-Agent Chat)**:
   - Rozszerzenie modułu czatu o rozmowy z 2..N agentami (domyślnie do 8 uczestników).
   - 4 tryby orkiestracji wypowiedzi: `@mention`, `broadcast`, `round_robin`, `moderator`.
   - Dynamiczne dołączanie/odłączanie agentów w trakcie konwersacji z polityką przekazywania kontekstu (`full`, `summary`, `last_n`, `none`).
   - Równoległe strumieniowanie odpowiedzi i twarde limity kosztowe/pętli.

3. **Wizualny edytor scenariuszy z telemetrią na żywo (Visual Scenario Engine)**:
   - Nowy moduł `modules/Scenarios` z dedykowanym, pełnoekranowym edytorem blokowym otwieranym w osobnym oknie/karcie.
   - 12 typów węzłów (m.in. `start`, `agent`, `skill`, `memory`, `condition`, `parallel`, `join`, `loop`, `human`, `end`).
   - Asynchroniczny, idempotentny silnik wykonawczy na Horizonie z obsługą pauzowania, wznawiania i budżetów kosztowych.
   - Telemetria na żywo rysowana bezpośrednio na krawędziach i bloczkach schematu (WebSocket) oraz odtwarzacz historycznych runów.

4. **Granularne zdolności agenta (Capabilities & Memory Control)**:
   - Trzy niezależne osie kontroli w formularzu agenta:
     - **Internet** (`off`, `allowlist`, `open` – chroniony przez egress proxy i ochronę SSRF),
     - **Wiedza zgromadzona** (`none`, `read`, `readwrite` z ACL per kolekcja wektorowa),
     - **Pamięć wywołań** (`stateful` vs `stateless` – wymuszona bezstanowość kolejnych wywołań).
   - Zasada jednokierunkowego zawężania uprawnień przy nadpisywaniu w czacie i scenariuszach.

5. **Integracje: Adopcja i konfiguracja kontenerów Docker**:
   - Nowa zakładka *Integracje → Kontenery* umożliwiająca wykrywanie działających kontenerów lokalnego Dockera (Hermes, OpenClaw, Ollama).
   - Trzy tryby adopcji: `observe`, `configure`, `managed`.
   - Profile automatycznej konfiguracji (`autoconfig_profiles`) – m.in. auto-wybór modelu, rejestracja tokenu instancji, rollback w razie błędu health-checka.
   - Ścisły brak dowolnego terminala/exec – tylko polecenia ze zdefiniowanej allowlisty adaptera.

6. **Eksport i import całej konfiguracji platformy (Config Transfer)**:
   - Nowy moduł administracyjny *System → Eksport/Import*.
   - Rozszerzalny kontrakt `ConfigSection` dla wszystkich modułów biznesowych.
   - Format ZIP z sumami kontrolnymi SHA-256, opcjonalnym szyfrowaniem sekretów (Argon2id + XChaCha20-Poly1305) i automatycznym backupem przed importem.
   - Tryby importu (`merge`, `overwrite`, `new_only`) z pełnym raportem dry-run.

7. **Instrukcja pierwszej konfiguracji i automatyczna weryfikacja dokumentacji**:
   - Przewodnik krok po kroku w aplikacji (*System → Instrukcja konfiguracji*) oraz automatycznie generowany dokument `docs/FIRST_SETUP.md`.
   - Modułowe fragmenty `first_setup` w `module.json` ze śledzeniem postępu.
   - Narzędzie CI `php artisan docs:check` blokujące merge w razie braku aktualizacji dokumentacji i CHANGELOG przy zmianach w kodzie.

---

## 2. Aktorzy i scenariusze użytkownika (User Stories & Testing)

### User Story 1 - Bezpieczny magazyn i testowanie skilli (Priority: P1)
Jako inżynier AI chcę przeglądać, dodawać i wersjonować skille narzędziowe w dedykowanej zakładce oraz testować je w bezpiecznym sandboxie, aby moi agenci mogli bezpiecznie wykonywać operacje zewnętrzne bez ryzyka wycieku kluczy API aplikacji.

**Niezależny test**:
Możliwość wgrania paczki ZIP ze skillem, sprawdzenie poprawności sum kontrolnych, uruchomienie testu w sandboxie i przypięcie wybranej wersji skilla do agenta.

**Kryteria akceptacji**:
1. **Given** administrator wchodzi w *Agenci AI → Skille*, **When** klika *Dodaj skill* i wgrywa paczkę ZIP lub podaje repozytorium Git, **Then** skill zostaje zapisany ze statusem `pending_review` i wersją `1.0.0`.
2. **Given** skill wymagający zdolności `internet`, **When** użytkownik próbuje przypisać go do agenta z `internet_mode=off`, **Then** system blokuje przypisanie z czytelnym ostrzeżeniem o braku uprawnień.
3. **Given** zarejestrowany skill, **When** użytkownik klika *Testuj*, **Then** kod wykonuje się w izolowanym kontenerze/procesie sandbox bez zmiennych środowiskowych aplikacji.

---

### User Story 2 - Narzucenie bezstanowości i kontrola egress agenta (Priority: P1)
Jako administrator platformy chcę wymusić na wybranym agencie tryb `stateless` oraz ścisłą allowlistę domen internetowych, aby zapobiec wyciekowi danych między kolejnymi zapytaniami oraz atakom typu prompt injection / SSRF.

**Niezależny test**:
Wykonanie dwóch następujących po sobie wywołań agenta w trybie `stateless` i weryfikacja, że drugie wywołanie nie posiada w promptzie ani metadanych historii pierwszego wywołania.

**Kryteria akceptacji**:
1. **Given** agent z `context_mode=stateless`, **When** użytkownik wysyła drugą wiadomość w wątku, **Then** `AgentContextBuilder` przekazuje do modelu wyłącznie bieżącą wiadomość, prompt systemowy oraz wiedzę z pamięci (brak historii tur).
2. **Given** agent z `internet_mode=allowlist` i domeną `api.github.com`, **When** agent próbuje wykonać zapytanie do `malicious-domain.com`, **Then** proxy egress natychmiast odrzuca żądanie, a zdarzenie `internet.blocked` trafia do logów telemetrii.

---

### User Story 3 - Czat grupowy wielu agentów z orkiestracją (Priority: P2)
Jako deweloper chcę utworzyć konwersację z trzema agentami o różnych specjalizacjach w trybie `round_robin` lub `moderator`, aby wspólnie rozwiązywali złożone zadania analityczne.

**Niezależny test**:
Utworzenie konwersacji z agentami A, B i C, wysłanie zapytania i zaobserwowanie naprzemiennych, strumieniowanych odpowiedzi z zachowaniem limitu tur.

**Kryteria akceptacji**:
1. **Given** czat grupowy w trybie `mention`, **When** użytkownik pisze `@researcher sprawdź dane`, **Then** odpowiada tylko agent researcher; brak `@` kieruje zapytanie do agenta domyślnego (`lead`).
2. **Given** czat grupowy, **When** użytkownik dodaje nowego agenta w trakcie rozmowy z polityką `summary`, **Then** nowy uczestnik otrzymuje wygenerowane podsumowanie dotychczasowej rozmowy i pojawia się komunikat systemowy.
3. **Given** konwersacja grupowa osiągająca `max_turns=20` lub limit powtórzeń treści, **Then** orkiestrator natychmiast przerywa pętlę i wyświetla stosowny komunikat w oknie czatu.

---

### User Story 4 - Projektowanie i uruchamianie scenariuszy blokowych (Priority: P2)
Jako analityk chcę w osobnym pełnoekranowym oknie zaprojektować schemat blokowy łączący wywołania agentów, warunki logiczne, wywołania skilli i krok akceptacji przez człowieka (`human`), a następnie obserwować jego wykonanie na żywo z telemetrią.

**Niezależny test**:
Otwarcie edytora, ułożenie grafu `start -> agent -> condition -> end`, publikacja wersji, uruchomienie i obserwacja animacji stanów węzłów w kanwie.

**Kryteria akceptacji**:
1. **Given** edytor scenariusza, **When** użytkownik tworzy węzeł `condition`, **Then** warunki są ewaluowane w bezpiecznym sandboxie wyrażeń (bez dostępu do php `eval`).
2. **Given** opublikowany scenariusz z krokiem `human`, **When** wykonanie dojdzie do tego kroku, **Then** silnik wstrzymuje bieg (status `waiting`), wysyła powiadomienie i czeka na akceptację operatora.
3. **Given** trwający run scenariusza, **When** operator ogląda schemat, **Then** węzły aktualizują kolory, tokeny i czasy na żywo przez kanał Reverb `scenarios.{run_id}`.

---

### User Story 5 - Adopcja i auto-konfiguracja kontenerów Docker (Priority: P3)
Jako administrator chcę wykryć działający lokalnie kontener Ollama lub Hermes Agent, podłączyć go w trybie `managed` i automatycznie skonfigurować endpointy oraz modele bez ręcznej edycji plików.

**Niezależny test**:
Uruchomienie skanowania kontenerów, wybór kontenera Ollama, zastosowanie profilu auto-konfiguracji i potwierdzenie pomyślnego health-checka.

**Kryteria akceptacji**:
1. **Given** lista wykrytych kontenerów, **When** admin wybiera kontener i tryb `configure`, **Then** system generuje formularz na podstawie `configSchema()` adaptera z podglądem diffa przed zapisem.
2. **Given** profil auto-konfiguracji, **When** nastąpi błąd weryfikacji po restarcie kontenera, **Then** system automatycznie przywraca kopię poprzedniej konfiguracji (rollback) i raportuje błąd w audycie.
3. **Given** nieadoptowane kontenery hosta, **Then** AgentHub traktuje je jako nietykalne i nie pozwala na żadną modyfikację.

---

### User Story 6 - Eksport i import całej konfiguracji (Priority: P3)
Jako administrator chcę wyeksportować całą konfigurację systemu do pliku ZIP (z opcjonalnym szyfrowaniem sekretów hasłem) i zaimportować ją na czystej instancji z raportem dry-run.

**Niezależny test**:
Wykonanie `php artisan config:export`, następnie na czystej bazie `php artisan config:import --dry-run` oraz `--mode=merge`, z potwierdzeniem identyczności ustawień.

**Kryteria akceptacji**:
1. **Given** eksport konfiguracji, **When** admin wybierze dołączenie sekretów, **Then** plik `secrets.enc` jest zaszyfrowany kluczem wyprowadzonym z hasła (Argon2id + XChaCha20-Poly1305), a hasło nie jest nigdzie zapisywane.
2. **Given** import pliku ze starszej wersji, **When** uruchomiony jest proces importu, **Then** zarejestrowane sekcje `ConfigSection` dokonują automatycznej migracji schematu, pomijając dane operacyjne (logi, wiadomości czatu).
3. **Given** import konfiguracji, **Then** system automatycznie wykonuje backup lokalny w `storage/backups/config-<timestamp>.zip` przed wprowadzeniem zmian.

---

### User Story 7 - Instrukcja konfiguracji i egzekwowanie reguły dokumentacji (Priority: P4)
Jako deweloper i administrator chcę mieć zawsze aktualną instrukcję pierwszej konfiguracji w UI i w `docs/FIRST_SETUP.md`, a w procesie CI automatyczną blokadę pull requestów, które modyfikują kod bez aktualizacji dokumentacji.

**Niezależny test**:
Uruchomienie `php artisan docs:check` na branchu z nową funkcjonalnością bez wpisu w `first-setup.md` i weryfikacja, że komenda kończy się kodem błędu.

**Kryteria akceptacji**:
1. **Given** włączone moduły platformy, **When** uruchomiona zostaje komenda `php artisan docs:first-setup --build`, **Then** plik `docs/FIRST_SETUP.md` zostaje wygenerowany deterministycznie ze wszystkich fragmentów modułów.
2. **Given** zmiana w module bez aktualizacji dokumentacji, **When** CI uruchamia `php artisan docs:check`, **Then** proces kończy się błędem i blokuje merge.

---

## 3. Wymagania funkcjonalne (Functional Requirements)

- **FR-001**: System MUSI udostępniać w Menu 1 zakładkę *Agenci AI → Skille* z obsługą wyszukiwania, filtrowania, szczegółów i historii wersji.
- **FR-002**: System MUSI wspierać importowanie skilli z archiwów ZIP, repozytoriów Git z allowlisty, import z adapterów runtime'ów oraz formularz ręczny.
- **FR-003**: System MUSI uniemożliwić wykonanie kodu skilli w procesie głównym aplikacji – wykonywanie wyłącznie w izolowanym środowisku sandbox z limitami zasobów i filtrowanym ruchem sieciowym.
- **FR-004**: System MUSI walidować zgodność wymaganych przez skill zdolności (`requires`) ze zdolnościami agenta przed przypisaniem i przed wywołaniem.
- **FR-005**: Czat MUSI umożliwiać konwersacje grupowe z 2..8 agentami z 4 trybami orkiestracji (`mention`, `broadcast`, `round_robin`, `moderator`).
- **FR-006**: System MUSI egzekwować twarde limity tur (`max_turns`), tokenów i kosztów per konwersacja grupowa oraz wykrywać powtarzające się pętle dialogowe.
- **FR-007**: System MUSI zapewniać w formularzu agenta niezależną konfigurację `internet_mode` (`off`/`allowlist`/`open`), `memory_mode` (`none`/`read`/`readwrite`) oraz `context_mode` (`stateful`/`stateless`).
- **FR-008**: System MUSI wymuszać regułę, że nadpisanie zdolności agenta w czacie lub scenariuszu może wyłącznie zawężać uprawnienia (nie wolno ich rozszerzać).
- **FR-009**: System MUSI udostępniać pełnoekranowy edytor scenariuszy na trasie `/scenarios/{id}/editor` z 12 typami węzłów i możliwością otwarcia w nowym oknie przeglądarki.
- **FR-010**: Silnik `ScenarioEngine` MUSI wykonywać scenariusze asynchronicznie na kolejce, zapisywać stan kroków jako idempotentny i wznawialny po restarcie workera.
- **FR-011**: Telemetria scenariuszy MUSI być strumieniowana na żywo do edytora przez Reverb (stany węzłów, tokeny, koszty, zdarzenia agentów) oraz umożliwiać odtwarzanie zakończonych runów krok po kroku.
- **FR-012**: Moduł *Integracje → Kontenery* MUSI wykrywać lokalne kontenery Dockera i umożliwiać ich podłączenie w trybach `observe`, `configure` lub `managed`.
- **FR-013**: System MUSI zabraniać uruchamiania dowolnych komend i interaktywnego terminala w kontenerach – dozwolone są wyłącznie polecenia z `execAllowlist()` adaptera.
- **FR-014**: System MUSI wspierać profile auto-konfiguracji kontenerów z automatycznym rollbackiem w razie niepowodzenia health-checka.
- **FR-015**: Moduł *System → Eksport/Import* MUSI zapewniać pełny eksport i import konfiguracji w oparciu o kontrakt `ConfigSection` z pominięciem danych operacyjnych.
- **FR-016**: Eksport sekretów MUSI być domyślnie wyłączony, a po wybraniu szyfrowany hasłem podanym przez administratora (Argon2id + XChaCha20-Poly1305).
- **FR-017**: Import konfiguracji MUSI oferować tryb dry-run z wizualnym diffem oraz automatyczny backup przed wprowadzeniem zmian.
- **FR-018**: System MUSI udostępniać instrukcję pierwszej konfiguracji w UI ze śledzeniem postępu oraz komendę CLI `docs:check` weryfikującą aktualność dokumentacji w CI.

---

## 4. Kluczowe encje danych (Key Entities)

- **Skill & SkillVersion**: Reprezentacja metadanych skilla, manifestu, schematów wejścia/wyjścia oraz wersjonowanych paczek z sumami SHA-256.
- **Conversation & ConversationParticipant**: Konwersacja jedno- lub wieloagentowa z trybem orkiestracji, limitami i listą uczestników z przypisaną polityką kontekstu (`join_context`).
- **Scenario, ScenarioVersion, ScenarioTrigger, ScenarioRun & ScenarioRunStep**: Struktura grafu scenariusza blokowego (węzły, krawędzie, zmienne), wyzwalacze (ręczny, cron, webhook) oraz trwały stan wykonania runów i kroków.
- **IntegrationContainer & AutoConfigProfile**: Zmapowane kontenery Docker z metadanymi, trybem adopcji, snapshotem konfiguracji oraz profile deklaratywnych kroków auto-konfiguracji.
- **ConfigTransfer**: Historia i stan operacji eksportu/importu konfiguracji (typ, tryb, sekcje, status, sumy kontrolne, backup).
- **SetupProgress**: Rejestr ukończenia kroków instrukcji pierwszej konfiguracji per krok i użytkownik/globalnie.

---

## 5. Kryteria sukcesu (Success Criteria)

- **SC-001**: Aktualizacja instancji 1.0.x do 1.5.0 przy użyciu `scripts/update.sh` przebiega bezbłędnie z zachowaniem 100% dotychczasowych danych i automatycznym backfillem nowych kolumn.
- **SC-002**: Uruchomienie skilla w sandboxie nie posiada dostępu do zmiennych `.env` ani bazy danych aplikacji, a czas wykonania nie przekracza zadanego timeoutu.
- **SC-003**: Czat z 3 agentami w trybie `round_robin` wykonuje równe tury bez zapętlania i przerywa dialog po osiągnięciu `max_turns`.
- **SC-004**: Wywołanie agenta w trybie `context_mode=stateless` w 100% przypadków nie przekazuje kontekstu wcześniejszych wiadomości.
- **SC-005**: Zbudowanie i uruchomienie scenariusza z 5 węzłami w edytorze blokowym aktualizuje statusy węzłów na żywo w czasie poniżej 1 sekundy od zdarzenia.
- **SC-006**: Awaria workera w trakcie wykonywania kroku scenariusza pozwala na wznowienie runu od ostatniego niedokończonego kroku bez duplikacji efektów ubocznych.
- **SC-007**: Auto-konfiguracja kontenera z błędnym endpointem wycofuje zmiany do snapshotu początkowego w czasie poniżej 15 sekund.
- **SC-008**: Eksport konfiguracji, zresetowanie bazy i import wygenerowanego pliku przywraca identyczny stan konfiguracji (round-trip test 100% zgodności).
- **SC-009**: Komenda `php artisan docs:check` poprawnie wykrywa brakujący wpis w dokumentacji i zwraca kod błędu różny od 0.
- **SC-010**: Pokrycie testami automatycznymi (Pest) dla nowych modułów i serwisów wynosi minimum 80%, a PHPStan przechodzi na poziomie minimum 6.

---

## 6. Założenia (Assumptions)

- **A-001**: Podstawowe środowisko deweloperskie i produkcyjne posiada PHP 8.3/8.4, Laravel 11/12, Redis, PostgreSQL (lub MySQL) oraz Reverb.
- **A-002**: Do zarządzania kontenerami wykorzystywany jest lokalny socket Dockera (`unix:///var/run/docker.sock`), do którego dostęp ma wyłącznie proces runnera (`integration-manager`).
- **A-003**: Wsparcie dla edytora schematów blokowych opiera się na bibliotece Drawflow zintegrowanej z Alpine.js bez zewnętrznych frameworków SPA.
- **A-004**: Eksport sekretów wymaga podania hasła przez użytkownika; bez hasła sekrety są pomijane, a zaimportowane konta wymagają uzupełnienia poświadczeń.
- **A-005**: Zgodnie z zasadą zero-core modification, nowe funkcje tworzone są jako moduły (`modules/Scenarios`, `modules/System`) lub rozszerzenia istniejących manifestów.
