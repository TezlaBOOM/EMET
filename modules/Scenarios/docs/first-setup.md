# Pierwsza Konfiguracja: Scenariusze Wizualne (v1.5.0)

## Krok: Konfiguracja edytora blokowego i silnika wykonawczego

1. **Wymagane uprawnienia**:
   - `scenarios.view` – przeglądanie listy scenariuszy i historii runów.
   - `scenarios.manage` – tworzenie, edycja i publikowanie grafów w edytorze Drawflow.
   - `scenarios.run` – ręczne wyzwalanie uruchomień testowych scenariuszy.

2. **Edytor w osobnym oknie**:
   - Bezpośredni URL do edytora: `/scenarios/{id}/editor`.
   - Edytor obsługuje 12 typów węzłów: `start`, `agent`, `skill`, `memory`, `condition`, `parallel`, `join`, `loop`, `human`, `transform`, `delay`, `end`.
   - Zabezpieczenie `SCENARIO_MAX_NODES=200` chroni przed przeciążeniem przeglądarki i serwera.

3. **Wyzwalacze i webhooki**:
   - Scenariusze mogą być uruchamiane ręcznie, harmonogramem cron oraz webhookami HTTPS:
     `POST /api/v1/hooks/scenarios/{token}`.
   - Token webhooka jest haszowany funkcją SHA-256 w bazie danych.
