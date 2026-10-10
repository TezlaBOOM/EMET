# Phase 0: Technical Research & Architectural Decisions

**Feature**: `002-agenthub-v1-5`  
**Date**: 2026-10-10  
**Source**: [`Agenthub_v1.5.md`](../../Agenthub_v1.5.md)  

---

## 1. Weryfikacja i rozstrzygnięcie założeń projektowych (Z1–Z11)

| # | Założenie bazowe | Decyzja architektoniczna | Uzasadnienie i implikacje |
|---|-------------------|--------------------------|---------------------------|
| **Z1** | Ścieżka aktualizacji 1.0.x → 1.5.0 przez `update.sh` | **Przyjęte** | Aktualizacja addytywna: migracje nie usuwają danych, skrypt wykonuje automatyczny backup, weryfikację `compat-check.sh`, migracje bazy i test `agenthub:selftest`. W razie błędu działa `rollback.sh`. |
| **Z2** | „Wiedza zgromadzona” = istniejąca pamięć wektorowa (v1 §7.3) | **Przyjęte** | Zachowujemy spójność pojęciową. W UI używamy etykiety „Wiedza zgromadzona (pamięć wektorowa)”, a w kodzie i bazie operujemy na istniejących strukturach `MemoryCollections` z autoryzacją ACL per kolekcja. |
| **Z3** | Format paczki skilla (`skill.json` + `SKILL.md` + `resources/`) | **Przyjęte (autonomiczny standard paczki)** | Skill posiada deklaratywny manifest `skill.json` (schemat wejścia/wyjścia JSON Schema, deklaracja uprawnień `requires`). Adaptery runtime'ów (`HermesRuntimeAdapter`, `OpenClawRuntimeAdapter`) mapują format paczki na specyfikę danego silnika przez metodę `mapSkill(Skill $s): array`. |
| **Z4** | Oddzielne okno edytora scenariuszy | **Przyjęte** | Dedykowana trasa `/scenarios/{id}/editor` z niezależnym layoutem pełnoekranowym (bez standardowej siatki 7 bloków). Opcja „Otwórz w nowym oknie” otwiera `window.open()`, a dwukierunkowa synchronizacja stanu edycji i telemetrii odbywa się przez kanał WebSocket (Laravel Reverb). |
| **Z5** | Wybór biblioteki edytora grafu: **Drawflow** | **Przyjęte (zapisane w ADR 0001)** | Drawflow jest niezwykle lekki (~15KB), nie wymaga ciężkiego builda React/Vue, idealnie integruje się z Alpine.js, posiada natywną obsługę portów węzłów, zoom, drag-and-drop oraz serializację grafu do JSON. Alternatywy (Rete.js, React Flow) odrzucono ze względu na narzut architektoniczny i brak czystego wsparcia dla Blade/Alpine. |
| **Z6** | Wyzwalacze scenariuszy: ręczny, cron, webhook | **Przyjęte** | Trzy stabilne mechanizmy wyzwalania: formularz wejściowy w UI, harmonogram `Schedule` Laravela (cron) oraz bezpieczny publiczny endpoint webhooka z uwierzytelnianiem hashowanym tokenem (`POST /api/v1/hooks/scenarios/{token}`). |
| **Z7** | Zakres kontenerów: tylko lokalny Docker (socket), brak wolnego `exec` | **Przyjęte (twarde bezpieczeństwo)** | Zgodnie z v1 §9.2 zakazuje się otwierania interaktywnego terminala na hoście. Dozwolony jest wyłącznie lokalny socket Dockera kontrolowany przez `integration-manager` z zamkniętą białą listą poleceń (`execAllowlist()`). Zdalne klastry Docker/K8s poza zakresem 1.5.0. |
| **Z8** | Eksport konfiguracji: domyślnie bez sekretów, opcjonalnie hasło Argon2id | **Przyjęte** | Bezpieczeństwo poświadczeń: domyślny plik ZIP nie zawiera kluczy API (po imporcie konta wymagają uzupełnienia). Po zaznaczeniu opcji „Dołącz sekrety” plik `secrets.enc` szyfrowany jest algorytmem XChaCha20-Poly1305 z kluczem wyprowadzonym przez Argon2id z hasła jednorazowego. Notatki pamięci wyłączone domyślnie (opcjonalny przełącznik). |
| **Z9** | Nowy moduł Menu 1 `System` (tylko administrator) | **Przyjęte** | Nowy katalog `modules/System` z manifestem deklarującym pozycję w Menu 1. Zbiera: Eksport/Import konfiguracji, Instrukcję pierwszej konfiguracji, Dziennik zmian i Informacje o wersji. |
| **Z10** | Plik reguł dla AI: `AGENTS.md` + weryfikacja w CI | **Przyjęte** | Zasada z v1.5 §0 pkt 7–10 wprowadzona do `AGENTS.md`. Narzędzie `php artisan docs:check` włączone do pipeline'u CI i skryptu `compat-check.sh` w celu blokowania pull requestów bez aktualizacji dokumentacji. |
| **Z11** | Tryb internetu „otwarty” (`open`) | **Przyjęte** | Domyślnie wyłączony globalnie flagą `INTERNET_ALLOW_OPEN=false`. Włączenie wymaga zgody admina w konfiguracji systemowej oraz uprawnienia `agents.internet.open`. Ochrona przed SSRF (blokada adresów prywatnych RFC 1918 i link-local) działa bezwzględnie w każdym trybie. |

---

## 2. Analiza architektury komponentów

### 2.1 Bezpieczeństwo i sandbox magazynu skilli
1. **Struktura paczki**:
   - Paczka ZIP zawiera `skill.json` (metadane, schemat wejścia `input_schema` i wyjścia `output_schema`, lista wymaganych zdolności `requires: ["internet", "memory.read"]`, wersja, kompatybilność).
   - Opcjonalny plik `SKILL.md` (instrukcje operacyjne wstrzykiwane do promptu agenta).
   - Opcjonalny katalog `resources/` zawierający skrypty lub pliki pomocnicze.
2. **Ochrona przed podatnościami**:
   - **Zip-slip**: walidacja ścieżek podczas rozpakowywania paczki (`realpath` musi znajdować się wewnątrz dedykowanego katalogu docelowego w `storage/app/skills/<id>/<version>/`).
   - **Izolacja wykonawcza**: skille typu kod (`tool`, `package`, `workflow`) uruchamiane są w wydzielonym kontenerze sandbox przez demona `agenthub-runner`. Proces ten nie montuje pliku `.env` aplikacji ani socketu bazy danych. Dostęp do sieci zależy od zdolności agenta wywołującego.
   - **Weryfikacja uprawnień**: Przed przypisaniem skilla do agenta oraz w momencie wywołania weryfikowana jest reguła zgodności:
     $$\text{AgentCapabilities} \supseteq \text{SkillRequires}$$
     Próba uruchomienia skilla wymagającego `internet` przez agenta z `internet_mode=off` kończy się natychmiastowym wyjątkiem `CapabilityMismatchException`.

### 2.2 Orkiestracja czatu grupowego (Multi-Agent Chat)
1. **Zarządzanie turami i kontekstem**:
   - `GroupChatOrchestrator` implementuje maszynę stanów konwersacji.
   - **Tryb `mention`**: Wyrażenie regularne `@([a-z0-9_-]+)` wykrywa adresowanego agenta. W przypadku braku wzmianki odpowiada agent wyznaczony jako `lead`.
   - **Tryb `broadcast`**: Dysponowanie równoległych zadań wywołania modeli dla wszystkich uczestników, strumieniowanie równoległych delt odpowiedzi z identyfikatorem `participant_id`.
   - **Tryb `round_robin`**: Kolejkowanie agentów w cyklu zamkniętym z przekazywaniem transkryptu rundy do kolejnego mówcy.
   - **Tryb `moderator`**: Dedykowany agent-moderator otrzymuje podsumowanie rundy i zwraca ustrukturyzowany JSON:
     ```json
     { "next_speaker": "analyst-agent", "reason": "Potrzebna analiza danych", "is_complete": false }
     ```
2. **Ochrona przed zapętleniem i kosztami**:
   - Rejestrowanie hashy SHA-256 ostatnich odpowiedzi agentów. Wykrycie 3 identycznych odpowiedzi lub naprzemiennych repetycji skutkuje przerwaniem rundy z kodem `LOOP_DETECTED`.
   - Twardy limit `max_turns` (domyślnie 20) oraz globalny budżet `max_cost` konwersacji.
   - Odpowiedzi innych agentów są oznaczane w metadanych jako `untrusted_source`, aby zminimalizować ryzyko jailbreaku między agentami.

### 2.3 Egzekwowanie zdolności agenta (Capabilities & Memory Control)
1. **Pamięć wywołań (`stateful` vs `stateless`)**:
   - W trybie `context_mode=stateless` serwis `AgentContextBuilder` całkowicie pomija ładowanie wcześniejszych rekordów z tabeli `messages`.
   - Agent otrzymuje wyłącznie:
     1. Prompt systemowy agenta,
     2. Wstrzyknięte fragmenty wiedzy z pamięci (jeśli `memory_mode != none`),
     3. Bieżącą wiadomość użytkownika / wejście węzła.
   - Dla zewnętrznych runtime'ów (Hermes / OpenClaw) adapter tworzy efemeryczną, jednorazową sesję i usuwa ją natychmiast po zwróceniu wyniku.
2. **Internet i Egress Proxy**:
   - Wychodzące połączenia HTTP agentów przechodzą przez wewnętrzne proxy egress.
   - Proxy odczytuje token powiązany z runem agenta, pobiera `internet_mode` oraz listę dozwolonych domen.
   - Każde żądanie emituje zdarzenie telemetrii: `internet.request` lub `internet.blocked`.

### 2.4 Silnik wykonawczy scenariuszy (ScenarioEngine)
1. **Wizualny schemat blokowy (Drawflow)**:
   - Graf scenariusza serializowany jest do JSON z tablicami `nodes` i `edges`.
   - Publikacja wersji (`scenario_versions`) wymusza formalną walidację acykliczności (cykle dozwolone wyłącznie przez dedykowany węzeł `loop` ze sztywnym limitem iteracji), obecności węzła `start` i zakończenia wszystkich ścieżek węzłem `end`.
2. **Asynchroniczny silnik na Laravel Horizon**:
   - Uruchomienie scenariusza tworzy rekord `scenario_runs`.
   - Każdy węzeł grafu wykonywany jest jako niezależne zadanie w kolejce: `ExecuteScenarioNodeJob`.
   - Stan kroków zapisywany jest w `scenario_run_steps`.
   - Idempotencja: Ponowne uruchomienie kroku po awarii workera weryfikuje status zapisanego kroku i unika ponownego wywoływania efektów zewnętrznych.
   - Krok `human`: Silnik ustawia status `waiting` i publikuje powiadomienie. Wznowienie następuje po wywołaniu endpointu akceptacji przez operatora.
   - Ewaluacja warunków (`condition`): Bezpieczny parser wyrażeń (`symfony/expression-language`) z zakazem wywoływania funkcji systemowych PHP.

### 2.5 Adopcja kontenerów Docker
1. **Komunikacja z socketem**:
   - Dostęp do `unix:///var/run/docker.sock` posiada wyłącznie demon `integration-manager` (użytkownik systemowy `agenthub-runner`).
   - Aplikacja webowa komunikuje się z demonem asynchronicznie przez tabelę `provisioning_jobs` i kolejkę Redis.
2. **Brak dowolnego `exec` i auto-konfiguracja**:
   - Komendy wykonywane w kontenerze pochodzą wyłącznie ze słownika `execAllowlist()` adaptera.
   - Auto-konfiguracja tworzy snapshot bieżącej konfiguracji (`config_snapshot`). Po zapisaniu nowych ustawień i restarcie kontenera następuje test łączności (`healthProbe`). W razie braku odpowiedzi w zadanym czasie, silnik automatycznie przywraca poprzednią konfigurację ze snapshotu.

### 2.6 Kontrakt eksportu i importu konfiguracji (`ConfigSection`)
1. **Rozszerzalna architektura**:
   - Każdy moduł rejestruje implementację interfejsu `ConfigSectionInterface` w swoim manifeście `module.json`.
   - Sekcje definiują kolejność importu poprzez `dependsOn()` (np. sekcja `users` i `ai` przed `agents`, a `agents` przed `scenarios`).
2. **Szyfrowanie i bezpieczeństwo transferu**:
   - Domyślnie eksport generuje pliki JSON dla każdej sekcji z pominięciem pól zdefiniowanych w `secretFields()`.
   - W przypadku wyboru eksportu z sekretami: generowany jest plik `secrets.enc` szyfrowany za pomocą biblioteki `libsodium` (`crypto_aead_xchacha20poly1305_ietf_encrypt`) z kluczem wyprowadzonym przez `crypto_pwhash` (Argon2id) z hasła podanego w locie przez administratora.
   - Import wspiera pełny tryb `dry-run`, raportując liczbę rekordów do utworzenia, zaktualizowania i konfliktów przed fizycznym zapisem w transakcji bazodanowej.

---

## 3. Zgodność z architekturą 1.0.0 i zasada Zero-Core Modification

1. **Układ 7 bloków**:
   - Układ bloków pozostaje bez zmian. Moduł `Scenarios` rejestruje się w Menu 1, a jego pełnoekranowy edytor renderowany jest w dedykowanym widoku Blade (`layouts.editor`).
   - Moduł `System` dodaje nową pozycję w Menu 1 wyłącznie dla użytkowników z uprawnieniem administracyjnym.
2. **Kompatybilność wsteczna baz danych**:
   - Nowe tabele dodawane są w nowych migracjach.
   - Tabela `agents` otrzymuje kolumny `internet_mode` i `context_mode` z automatyczną migracją wartości `internet_enabled`:
     - `internet_enabled = true` $\rightarrow$ `internet_mode = 'allowlist'`
     - `internet_enabled = false` $\rightarrow$ `internet_mode = 'off'`
   - Kolumna `internet_enabled` pozostaje jako pole wirtualne/synchronizowane w modelu Eloquent na czas wydania 1.5.0.
