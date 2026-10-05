# Integracja: Hermes Agent

**Hermes Agent** (Nous Research) to zaawansowany silnik agentowy zoptymalizowany pod kątem autonomicznego planowania, wywoływania narzędzi oraz dynamicznego rozumowania.

---

## 1. Architektura Integracji

Integracja z platformą Projekt-Emet realizowana jest przez adapter `App\Services\IntegrationManager\Adapters\HermesAdapter`:
- **Komunikacja:** protokół REST API (porty 8100–8900).
- **Tryby uruchomienia:**
  - `systemd`: jednostka `agenthub-hermes@<nazwa>.service` z plikiem zmiennych środowiskowych `/etc/agenthub/instances/<nazwa>.env`.
  - `docker`: kontener oparty o oficjalny obraz Hermes Agent z mostkiem sieciowym.

---

## 2. Automatyczny Provisioning

Aby utworzyć nową instancję Hermes Agent:
1. Przejdź do modułu **Integracje → Hermes Agent**.
2. Kliknij przycisk **Zainstaluj instancję**.
3. Podaj unikalną nazwę i wybierz tryb uruchomienia (`systemd` lub `docker`).
4. System automatycznie:
   - Alokuje wolny port TCP z puli `8100–8900`.
   - Renderuje pliki konfiguracyjne.
   - Uruchamia usługę.
   - Przeprowadza health probe (`GET /health`).
   - Rejestruje instancję w bazie `integration_instances` ze statusem `running`.

W przypadku niepowodzenia testu zdrowia, system automatycznie wykonuje **rollback** i przywraca stan pierwotny.

---

## 3. Monitorowanie i Health-Check

Pętla uzgadniania stanu (`agenthub:reconcile-instances`) co 30 sekund odpytuje endpoint zdrowia instancji. Statusy:
- `running` (zielony) – usługa odpowiada prawidłowo.
- `degraded` (żółty) – latencja przekracza progi tolerancji lub pojedyncze błędy.
- `error` (czerwony) – brak odpowiedzi HTTP na przydzielonym porcie.
