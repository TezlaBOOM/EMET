---
key: ai.accounts_and_guardrails
title: Dostawcy AI, Pule Smart Routing, Semantic Cache i Guardrails
order: 200
since_version: 1.6.0
optional: false
link: ai-settings.index
---

## Konfiguracja Bramy Modeli (LLM Gateway v1.6.0)

1. **Dostawcy i Konta Modeli (`Ustawienia AI → Konta modeli`)**:
   - Dodaj klucze API dla dostawców chmurowych (OpenAI, Anthropic, Google Gemini, OpenRouter) lub skonfiguruj lokalne serwery inferencyjne (**Ollama**, **LM Studio**).
2. **Inteligentny Routing Złożoności (`Ustawienia AI → Pule kont i routing`)**:
   - Strategia `complexity_aware` analizuje złożoność promptu (długość kontekstu, obecność kodu/SQL, narzędzia `tools`, słowa kluczowe wnioskowania) i automatycznie kieruje proste zapytania do darmowych modeli lokalnych lub *Flash/Mini*, rezerwując modele flagowe dla trudnych zadań.
3. **Semantyczna Pamięć Podręczna (`Ustawienia AI → Cache & Guardrails`)**:
   - Włącz `LLM_SEMANTIC_CACHE_ENABLED=true`, aby powtarzalne lub semantycznie zbieżne zapytania (próg podobieństwa domyślnie `0.95`) zwracały natychmiastową odpowiedź bez kosztu tokenów.
4. **Bramka AI Guardrails & DLP (`Ustawienia AI → Cache & Guardrails`)**:
   - Warstwa wejściowa automatycznie wykrywa próby *Prompt Injection / Jailbreak* (`block` / `sanitize` / `audit`).
   - Warstwa wyjściowa DLP pseudonimizuje dane wrażliwe (PESEL, e-mail, karty płatnicze, IBAN, klucze API, telefony) przed wysłaniem do dostawcy chmurowego i odtwarza je w odpowiedzi końcowej.

