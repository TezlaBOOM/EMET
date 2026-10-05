# Projekt-Emet – Dokumentacja Techniczna v1.0.0

Witaj w oficjalnej dokumentacji platformy **Projekt-Emet** – zunifikowanego systemu orkiestracji agentów AI, wielomodelowej bramy LLM, semantycznej pamięci wektorowej i automatycznego provisioningu instancji.

---

## Spis treści dokumentacji

1. [Instalacja i pierwsze uruchomienie (INSTALL.md)](INSTALL.md)
   - Wariant kontenerowy (Docker All-in-one z `scripts/install.sh`)
   - Wariant natywny dla Debian 13 (Apache + PHP-FPM 8.4 + systemd z `scripts/install-debian13.sh`)
   - Kreator pierwszego uruchomienia (Setup Wizard)
   - Diagnostyka selftest (`php artisan agenthub:selftest`)
2. [Architektura platformy i przepływy danych (ARCHITECTURE.md)](ARCHITECTURE.md)
   - 7-blokowy layout interfejsu użytkownika
   - Podsystem bramy modeli AI (LLM Gateway) i strategie routingu
   - Pamięć wektorowa i graf asocjacji (Qdrant & PgVector)
   - Telemetria, rollupy i websocket Reverb
3. [System modułów i rozszerzalność (MODULES.md)](MODULES.md)
   - Budowa i struktura modułu
   - Manifest `module.json` i integracja z Menu 1 / Menu 2
   - Przestrzenie nazw widoków i tras
   - Referencyjny moduł `modules/Hello/`
4. [Bezpieczeństwo i hardening (SECURITY.md)](SECURITY.md)
   - Model ról i uprawnień RBAC
   - Szyfrowanie kluczy API (`encrypted` cast i `APP_KEY`)
   - Ochrona przed SSRF, allowlisty domen wychodzących i ochrona przed prompt injection
   - Uprawnienia sandboxingu procesów `agenthub-runner`
5. [Integracje z runtime'ami agentów](integrations/hermes.md)
   - [Hermes Agent (integrations/hermes.md)](integrations/hermes.md)
   - [OpenClaw (integrations/openclaw.md)](integrations/openclaw.md)
   - Claude Code CLI & Codex CLI
6. [System automatycznych aktualizacji](../CHANGELOG.md)
   - Test zgodności środowiska `scripts/compat-check.sh`
   - Proces aktualizacji `scripts/update.sh`
   - Awaryjne przywracanie stanu `scripts/rollback.sh`
