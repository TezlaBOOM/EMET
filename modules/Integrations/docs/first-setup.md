# Pierwsza Konfiguracja: Integracje i Kontenery Docker (v1.5.0)

## Krok: Konfiguracja adapterów integracji i kontenerów Docker

1. **Wymagane uprawnienia**:
   - `integrations.manage` – wykrywanie, adopcja i konfiguracja kontenerów Docker oraz instancji zewnętrznych.

2. **Dostęp do Docker Socket**:
   - AgentHub komunikuje się z demonem Docker przez dedykowany socket: `unix:///var/run/docker.sock` (konfiguracja: `DOCKER_SOCKET_PATH`).
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
