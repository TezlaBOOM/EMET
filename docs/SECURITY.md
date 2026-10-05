# Bezpieczeństwo i Hardening Platformy Projekt-Emet

Bezpieczeństwo danych użytkowników, sekretów API oraz izolacji uruchamianych agentów AI to kluczowy filar architektury Projekt-Emet.

---

## 1. Szyfrowanie Sekretów w Bazie Danych

Wszystkie klucze API dostawców modeli (`llm_accounts.api_key`) są szyfrowane symetrycznie algorytmem **AES-256-CBC** przy użyciu klucza aplikacji `APP_KEY`:

```php
protected function casts(): array
{
    return [
        'api_key' => 'encrypted',
    ];
}
```

- W bazie danych przechowywany jest wyłącznie zaszyfrowany ciąg payloadu z podpisem HMAC.
- Bezpośredni zrzut bazy danych (SQL dump) nie ujawnia surowych kluczy API.
- W logach systemowych i tabeli `audit_logs` klucze są maskowane (`sk-***`).

---

## 2. Model Uprawnień RBAC (Role-Based Access Control)

Dostęp do poszczególnych modułów i akcji jest kontrolowany przez pakiet **Spatie Laravel Permission**:

- **Role domyślne:**
  - `admin`: pełen dostęp do platformy, konfiguracji AI, zarządzania użytkownikami, tworzenia i usuwania instancji.
  - `operator`: dostęp do agentów, czatu, bazy pamięci, odczytu metryk i logów.
  - `user`: dostęp do czatu z przydzielonymi agentami oraz własnego profilu.
- Każda trasa chroniona jest dedykowanym middlewarem, np. `permission:integrations.manage`, `permission:ai.manage`, `permission:users.manage`.

---

## 3. Ochrona Przed SSRF i Allowlisty Domen

Agenci AI korzystający z narzędzi sieciowych (`web_search`, requesty HTTP) podlegają restrykcyjnym regułom bezpieczeństwa sieciowego:
- **Blokada adresów prywatnych:** żądania kierowane do `127.0.0.1`, `localhost`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16` oraz adresów metadanych chmurowych (`169.254.169.254`) są automatycznie blokowane.
- **Allowlisty domen:** możliwość zdefiniowania dozwolonych domen dla agentów badawczych w panelu Ustawienia AI.

---

## 4. Ochrona Przed Prompt Injection

- Treści pobierane z sieci, plików zewnętrznych oraz bazy pamięci wektorowej są oznaczane w promptcie jako niezaufany kontekst wejściowy (`<untrusted_context>`).
- Instrukcje systemowe agenta mają najwyższy priorytet i nie mogą być nadpisane przez treść dokumentów z pamięci.
- Akcje agentów o potencjalnych skutkach ubocznych (zapis do plików, uruchamianie poleceń) wymagają jawnego uprawnienia w definicji agenta.

---

## 5. Izolacja Procesów Instancji (`agenthub-runner`)

- Instancje systemd (`agenthub-hermes@`, `agenthub-openclaw@`) działają na dedykowanym koncie systemowym bez uprawnień administracyjnych (`User=www-data` lub `agenthub-runner`).
- Uprawnienia sudo/polkit są ograniczone wyłącznie do poleceń `systemctl start|stop|restart|status` dla wzorca `agenthub-*`.
- W trybie Docker kontenery uruchamiane są w odizolowanej sieci wirtualnej `agenthub-net` bez dostępu do socketu Dockera hosta.
