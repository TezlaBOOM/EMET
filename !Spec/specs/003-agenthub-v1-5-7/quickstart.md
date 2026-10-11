# Quickstart & Verification Guide: AgentHub v1.5.7

**Feature**: `003-agenthub-v1-5-7`  
**Date**: 2026-10-11  

Przewodnik weryfikacji end-to-end dla wydania **1.5.7**.

---

## 1. Wymagania wstępne i przygotowanie środowiska

Przed przystąpieniem do testów upewnij się, że migracje zostały zaaplikowane, a scheduler Laravela jest aktywny:

```bash
# 1. Aplikacja nowych migracji (timery, models, soft-deletes dla skilli)
php artisan migrate

# 2. Rejestracja nowych uprawnień w seederze
php artisan db:seed --class=RolesAndPermissionsSeeder --force

# 3. Wyczyszczenie pamięci podręcznej
php artisan optimize:clear
```

---

## 2. Scenariusze weryfikacyjne

### Scenariusz 1: Audyt I18n i brak surowych kluczy w interfejsie
```bash
# Uruchomienie komendy audytu tłumaczeń
php artisan lang:audit

# Weryfikacja testem automatycznym
php artisan test --filter=I18nCompletenessTest
```
**Oczekiwany rezultat**:
- Raport `lang:audit` nie wykazuje brakujących kluczy ani pustych stringów w `lang/pl` i `lang/en`.
- Zakładka w *Agenci AI* wyświetla poprawnie „Skille” (PL) lub „Skills” (EN), a nie `agent.sub_skill`.

---

### Scenariusz 2: Test naprawy kreatora nowego scenariusza
```bash
# Uruchomienie testu regresyjnego tworzenia scenariusza
php artisan test --filter=ScenarioCreateAndEditTest
```
**Oczekiwany rezultat**:
- Wysłanie żądania `POST /scenarios` z danymi nowego scenariusza atomowo tworzy rekord scenariusza oraz wersję roboczą z grafem `start` → `end`.
- Przekierowanie do `/scenarios/{id}/editor` otwiera edytor Drawflow ze statusem HTTP 200, bez błędu 500 w `storage/logs/laravel.log`.

---

### Scenariusz 3: Test harmonogramu (Timerów) i brak podwójnych odpaleń
```bash
# 1. Uruchomienie testów jednostkowych i integracyjnych silnika harmonogramu
php artisan test --filter=ScenarioSchedulerTest

# 2. Ręczne wywołanie dyspozytora zadań schedulera
php artisan scenarios:dispatch-due --verbose
```
**Oczekiwany rezultat**:
- Wyliczone terminów `next_run_at` dla typów `interval`, `daily`, `weekly`, `monthly`, `cron` uwzględniają strefę `Europe/Warsaw`.
- Równoległe wywołanie `scenarios:dispatch-due` blokuje wyścig dzięki `Cache::lock` i generuje tylko jeden wpis `scenario_runs` z unikalnym `idempotency_key`.

---

### Scenariusz 4: Test edycji modeli AI i trwałości nadpisań (Overrides)
```bash
# Test integracyjny zarządzania modelami i zachowywania overrides
php artisan test --filter=ModelManagementTest
```
**Oczekiwany rezultat**:
- Modyfikacja parametrów modelu w *Ustawienia AI → Modele* zapisuje zmienione klucze w polu `overrides`.
- Ponowne wywołanie synchronizacji modeli z dostawcą (`listModels`) aktualizuje pola dostawcy, lecz nie nadpisuje pól użytkownika z tablicy `overrides`.

---

### Scenariusz 5: Test kaskadowego wyboru modelu w formularzu Agenta
```bash
# Test walidacji modelu agenta
php artisan test --filter=AgentModelValidationTest
```
**Oczekiwany rezultat**:
- Próba zapisu agenta z modelem wyłączonym lub niedostępnym na wybranym koncie/puli zostaje odrzucona przez regułę `ValidModelForAgent` z czytelnym komunikatem błędu po polsku.

---

### Scenariusz 6: Test oznaczania nieużywanych skilli (czerwona flaga) i manualnego usuwania
```bash
# Test wykrywania nieużywanych skilli, czerwonej flagi oraz blokady usuwania przy zależnościach
php artisan test --filter=SkillLifecycleTest
```
**Oczekiwany rezultat**:
- Skille niepowiązane z żadnym agentem ani scenariuszem otrzymują w UI wskaźnik **czerwonej flagi** (🚩) i status „Nieużywany”.
- System nie usuwa nieużywanych skilli samoczynnie – usunięcie jest możliwe wyłącznie poprzez manualną akcję użytkownika.
- Próba usunięcia skilla aktywnie używanego zwraca kod HTTP 409 z listą powiązań.
- Usunięty manualnie przez użytkownika skill trafia do kosza (`deleted_at`), skąd może zostać przywrócony lub trwale usunięty (`skills.purge`).

---

## 3. Kompletna weryfikacja jakościowa (CI Gate)

Przed zakończeniem prac i wystawieniem PR uruchom pełny zestaw testów spójności:

```bash
# 1. Weryfikacja reguły dokumentacyjnej
php artisan docs:check

# 2. Formatowanie kodu standardem Pint
vendor/bin/pint --format agent

# 3. Pełny pakiet testów
php artisan test --compact
```
Wszystkie testy muszą zakończyć się statusem PASS.
