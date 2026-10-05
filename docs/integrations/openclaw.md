# Integracja: OpenClaw

**OpenClaw** to otwarty framework agentowy przeznaczony do automatyzacji zadań webowych, pobierania danych, wieloetapowego scrapingu oraz interakcji z aplikacjami internetowymi.

---

## 1. Architektura Integracji

Integracja z platformą Projekt-Emet realizowana jest przez adapter `App\Services\IntegrationManager\Adapters\OpenClawAdapter`:
- **Komunikacja:** protokół REST API i streaming zdarzeń.
- **Domyślny port:** alokowany dynamicznie z puli `8100–8900`.
- **Tryby pracy:** `systemd` (proces lokalny) lub `docker` (kontener z przeglądarką Chromium headless).

---

## 2. Automatyczny Provisioning

Nową instancję OpenClaw można utworzyć z panelu **Integracje → OpenClaw** lub przez CLI:

```bash
php artisan integrations:instance:create openclaw --name="WebCrawler" --mode=docker
```

System alokuje unikalny port, przygotowuje manifest i plik konfiguracyjny, weryfikuje gotowość usługi i zgłasza gotowość w dashboardzie.

---

## 3. Bezpieczeństwo Instancji OpenClaw

- Dostęp do sieci zewnętrznej realizowany jest przez proxy filtrujące z ochroną przed SSRF.
- Zablokowane są wywołania do prywatnych adresów IP sieci lokalnej oraz metadanych instancji.
- Wszystkie akcje crawlingu są rejestrowane w tabeli `telemetry_events` i widoczne w czasie rzeczywistym na Dashboardzie.
