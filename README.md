# Projekt-Emet – Zunifikowana Platforma Orkiestracji Agentów AI

**Projekt-Emet** to zaawansowana, modularna platforma webowa oparta o **Laravel 13**, integrująca autonomicznych agentów AI, wielomodelową bramę LLM, semantyczną pamięć wektorową oraz automatyczny provisioning instancji agentowych.

---

## Główne Możliwości

- **Wielomodelowa Brama LLM (Gateway):** obsługa dostawców OpenAI, Anthropic Claude, Google Gemini, Ollama, OpenRouter; pule kont z algorytmami routingu (*Round-Robin*, *Weighted*, *Least-Used*, *Priority Fallback*) oraz dynamicznym cooldownem na błędy 429 i failoverem.
- **Zunifikowany Czat & Agenci AI:** konfiguracja promptów systemowych, temperatur, umiejętności (*web search*, *memory search*, *file editor*), streaming odpowiedzi przez Server-Sent Events (SSE) i pływający widżet w Bloku 7.
- **Pamięć Semantyczna (Memory Service):** integracja z bazą wektorową Qdrant oraz PgVector, automatyczny chunker tekstu i Markdown, wizualizacja grafu asocjacji w stylu Obsidian na Canvasie.
- **Automatyczny Provisioning Instancji:** obsługa runtime'ów Hermes Agent, OpenClaw, Claude Code CLI, Codex CLI w trybie systemd i Docker z dynamiczną alokacją portów (8100–8900) i automatycznym rollbackiem przy awariach.
- **Telemetria & Reaktywny Dashboard:** preagregowane rollupy (godzinowe i dzienne), transmisje WebSocket w czasie rzeczywistym przez Laravel Reverb, analiza kosztów i latencji (TTFT, p50, p95).
- **Setup Wizard & Instalatory:** 7-etapowy kreator konfiguracji, instalator kontenerowy (`scripts/install.sh`) i instalator natywny dla Debian 13 (`scripts/install-debian13.sh`).
- **System Aktualizacji i Zgodności:** test zgodności `scripts/compat-check.sh`, skrypt aktualizacji `scripts/update.sh` z automatycznym backupem oraz `scripts/rollback.sh`.
- **Pełna Lokalizacja (I18n):** 100% interfejsu w językach polskim (domyślny) i angielskim.

---

## Szybki Start

### 1. Uruchomienie w środowisku Docker
```bash
git clone https://github.com/TezlaBOOM/EMET.git agenthub && cd agenthub
./scripts/install.sh
```

### 2. Instalacja natywna (Debian 13)
```bash
git clone https://github.com/TezlaBOOM/EMET.git agenthub && cd agenthub
sudo ./scripts/install-debian13.sh --db=pgsql --vector=qdrant
```

### 3. Kontener Proxmox LXC (Debian 12 / Ubuntu 24.04 / 22.04)
```bash
# Opcja A: Szybka instalacja jednym poleceniem wewnątrz nowego kontenera LXC:
curl -fsSL https://raw.githubusercontent.com/TezlaBOOM/EMET/main/scripts/install-lxc.sh | bash

# Opcja B: Ręczne sklonowanie i uruchomienie:
git clone https://github.com/TezlaBOOM/EMET.git /var/www/agenthub && cd /var/www/agenthub
sudo ./scripts/install-lxc.sh
# Szczegółowy przewodnik: docs/PROXMOX_LXC.md
```

Domyślne dane administratora:
- **E-mail:** `admin@admin.lan`
- **Hasło:** `admin`

---

## Diagnostyka i Testy

```bash
# Diagnostyka stanu platformy
php artisan agenthub:selftest

# Sprawdzenie aktualizacji
php artisan agenthub:check-update

# Uruchomienie pełnego pakietu testów regresyjnych
php artisan test
```

---

## Dokumentacja Szczegółowa

Kompletna dokumentacja znajduje się w katalogu `docs/`:
- [Przewodnik Instalacji (docs/INSTALL.md)](docs/INSTALL.md)
- [Architektura Systemu (docs/ARCHITECTURE.md)](docs/ARCHITECTURE.md)
- [Rozszerzalność i Moduły (docs/MODULES.md)](docs/MODULES.md)
- [Bezpieczeństwo i Hardening (docs/SECURITY.md)](docs/SECURITY.md)
- [Integracja Hermes Agent (docs/integrations/hermes.md)](docs/integrations/hermes.md)
- [Integracja OpenClaw (docs/integrations/openclaw.md)](docs/integrations/openclaw.md)

---

## Licencja

Projekt-Emet jest oprogramowaniem open-source udostępnianym na licencji MIT.
