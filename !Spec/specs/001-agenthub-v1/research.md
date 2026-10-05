# Phase 0: Technical Research & Architectural Decisions

**Feature**: `001-agenthub-v1`  
**Date**: 2026-10-04  

---

## 1. Wybór technologii bazowych i architektury aplikacji

### 1.1 Backend i interfejs UI
- **Framework**: Laravel 11.x / 12.x na PHP 8.3/8.4.
  - *Dlaczego*: Ekosystem Laravela zapewnia wbudowane mechanizmy szyfrowania (`encrypted` Eloquent casts), natywny serwer WebSocket (Laravel Reverb), zaawansowane zarządzanie kolejkami asynchronicznymi (Laravel Horizon / Redis Queue) oraz elastyczny system zdarzeń.
- **Frontend**: Laravel Livewire 3 + Alpine.js + Tailwind CSS.
  - *Dlaczego*: Livewire 3 eliminuje narzut związany z osobnym buildem SPA (jak w React/Vue), zachowując pełną reaktywność w czasie rzeczywistym. Doskonale współgra z układem 7 bloków interfejsu i komponentami strumieniującymi czatu oraz logów.
- **WebSockets na żywo**: Laravel Reverb.
  - *Dlaczego*: Pierwszorzędny, lekki i niezwykle szybki serwer WebSocket napisany w PHP, wbudowany w ekosystem Laravela. Zapewnia natychmiastowe aktualizacje dashboardu, strumieniowanie telemetrii agentów oraz odpowiedzi czatu bez zewnętrznych zależności takich jak Pusher czy Soketi.

### 1.2 Przechowywanie danych i pamięć wektorowa
- **Główna baza relacyjna**: PostgreSQL 16 (lub MariaDB 10.11+ dla instalacji z `--db=mysql`).
  - Przechowuje konta użytkowników, konfiguracje dostawców AI, pule kont, metadane agentów, logi wywołań (`llm_calls`), telemetrię i audyt.
- **Magazyn wektorowy (Vector Store)**:
  - **Domyślny**: **Qdrant** (instalowany lokalnie jako usługa systemd na Debianie 13 lub kontener Docker, nasłuchujący na `127.0.0.1:6333` z kluczem API).
  - **Alternatywa**: Rozszerzenie `pgvector` w PostgreSQL (aktywowana opcją `--vector=pgvector`).
  - Abstrakcja: Interfejs `VectorStoreInterface` izoluje szczegóły silnika, pozwalając na bezszwowe przełączanie między Qdrant a pgvector.

---

## 2. Architektura LLM Gateway i zarządzanie pulami kont

### 2.1 Problem wielu kont i limitów dostawców
Wielu dostawców AI nakłada twarde limity liczby zapytań (RPM) oraz tokenów na minutę (TPM), a także zwraca błędy przeciążenia `HTTP 429 Too Many Requests`.
Architektura `llm-gateway` wprowadza:
- **`LlmAccountPool`**: Grupa kont przypisanych do danego modelu lub dostawcy.
- **Strategie doboru konta**:
  - `round_robin`: Równomierne rozłożenie obciążenia.
  - `weighted`: Proporcjonalnie do zadeklarowanych wag konta.
  - `least_used`: Wybór konta o najmniejszej liczbie zapytań/tokenów w oknie ostatnich 60 sekund.
  - `priority_fallback`: Korzystanie z konta głównego, a przy wyczerpaniu limitu przełączenie na zapasowe.
- **Dynamiczny Cooldown**: W przypadku błędu 429 (lub nagłówka `Retry-After`), konto otrzymuje status `cooldown` na czas wskazany przez dostawcę (domyślnie 60 s), a zapytanie jest automatycznie przekazywane do kolejnego konta w puli (failover).

---

## 3. Provisioning instancji Hermes Agent i OpenClaw

### 3.1 Niezależne mikroserwisy z pętlą uzgadniania stanu (Reconciliation Loop)
Każda instancja agenta (Hermes Agent / OpenClaw) działa jako **samodzielna jednostka**:
- **Tryb systemd**: Dynamicznie tworzona usługa szablonowa `agenthub-hermes@<slug>.service` z dedykowanym katalogiem `/var/lib/agenthub/instances/<slug>/` i portem z puli.
- **Tryb Docker**: Kontener zoptymalizowany pod kątem izolacji sieciowej (`agenthub-net`) i limitów zasobów.
- **Pętla Reconcile**: Harmonogram co 30 sekund weryfikuje zgodność stanu pożądanego (`desired_state`) ze stanem faktycznym (`actual_state`). W razie awarii usługi następuje próba restartu z ograniczeniem liczby prób (backoff).
- **Automatyczny Rollback**: Jeśli provisioning lub aktualizacja instancji nie przejdzie health-probe w wyznaczonym czasie (np. 60 s), proces natychmiast zatrzymuje jednostkę, przywraca poprzednią wersję/konfigurację i zwalnia zarezerwowane zasoby.

### 3.2 Bezpieczeństwo i uprawnienia wykonawcze
- Proces webowy (`www-data`) **nie posiada** bezpośrednich uprawnień roota ani uprawnień do zarządzania kontenerami Dockera.
- Komunikacja z `integration-manager` odbywa się przez kolejkę zadań w Redis lub dedykowany demon `agenthub-runner` działający z prawami ograniczonymi do allowlisty poleceń.

---

## 4. Układ 7 bloków interfejsu – Implementacja CSS Grid

```css
/* Szkielet układu 7 bloków */
.agenthub-shell {
  display: grid;
  grid-template-columns: 240px 1fr;
  grid-template-rows: 60px 48px 1fr;
  height: 100vh;
  width: 100vw;
  overflow: hidden;
}

/* Kolumna lewa */
.block-1-logo      { grid-column: 1; grid-row: 1; }
.block-2-menu1     { grid-column: 1; grid-row: 2; overflow-y: auto; }
.block-3-logout    { grid-column: 1; grid-row: 3; align-self: end; }

/* Obszar główny */
.block-4-topcenter { grid-column: 2; grid-row: 1; }
.block-5-user      { grid-column: 2; grid-row: 1; justify-self: end; }
.block-6-menu2     { grid-column: 2; grid-row: 2; border-bottom: 1px solid var(--border); }
.block-7-workspace { grid-column: 2; grid-row: 3; overflow-y: auto; }
```
Układ jest w pełni elastyczny, wspiera zwijanie lewego paska do 72 px oraz tryb mobilny (poniżej 768 px) z wysuwanym menu off-canvas.
