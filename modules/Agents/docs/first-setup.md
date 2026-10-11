# Pierwsza Konfiguracja: Agenci AI & Skille (v1.5.7)

## Krok: Konfiguracja magazynu skilli, zdolności agentów oraz wyboru modeli AI

1. **Weryfikacja uprawnień**:
   - `skills.view` – przeglądanie listy i szczegółów skilli.
   - `skills.manage` – dodawanie nowych skilli.
   - `skills.edit` – edycja metadanych i kodu definicji skilla (v1.5.7).
   - `skills.delete` – przenoszenie skilla do kosza (soft-delete) oraz przywracanie (v1.5.7).
   - `skills.purge` – trwałe usuwanie skilla z bazy danych i magazynu (v1.5.7).

2. **Dysk dyskowy skilli**:
   Upewnij się, że katalog `storage/app/skills` posiada prawa zapisu dla procesu aplikacji (`chmod 775 storage/app/skills`).

3. **Dodawanie i import skilla**:
   Przejdź do zakładki **Agenci AI → Skille** i kliknij **Dodaj skill**.
   - Możesz przesłać paczkę archiwum `.zip` zawierającą manifest `skill.json` lub utworzyć skill manualnie.
   - Każda paczka jest automatycznie weryfikowana pod kątem sumy kontrolnej SHA-256 oraz bezpieczeństwa ścieżek (Zip-Slip).

4. **Wersjonowanie i edycja skilli (v1.5.7)**:
   - Edycja metadanych (nazwa, opis, tagi) nie inkrementuje wersji semantycznej.
   - Edycja definicji technicznej (`content`, `entrypoint`, `schema`) wymaga wskazania typu podbicia wersji SemVer (`patch`, `minor`, `major`) oraz wpisania notatki zmian (changelog).
   - Historia wersji archiwalnych jest trwale rejestrowana w tabeli `skill_versions`.

5. **Czerwona flaga 🚩 dla nieużywanych skilli & manualne usuwanie (v1.5.7)**:
   - Skille, które nie są przypisane do żadnego agenta ani nie występują w węzłach grafów scenariuszy, są w widoku listy automatycznie oznaczane czerwoną flagą (🚩 badge „Nieużywany”).
   - Zgodnie z zasadą bezpieczeństwa, system **nie usuwa automatycznie nieużywanych skilli w tle** — decyzja o usunięciu pozostaje całkowicie w rękach użytkownika.
   - Próba usunięcia skilla używanego przez aktywnych agentów lub scenariusze zostaje zablokowana z kodem HTTP 409 i czytelną listą zależności.
   - Usunięte skille trafiają do Kosza (`deleted_at`), skąd mogą zostać w każdej chwili przywrócone lub trwale usunięte przez administratora (`skills.purge`).

6. **Kaskadowy wybór modelu w konfiguracji agenta (v1.5.7)**:
   - Formularze tworzenia i edycji agenta wykorzystują komponent kaskadowy `<x-model-select>`:
     Wybór środowiska wykonawczego (Runtime: Ollama, Hermes, OpenClaw, Brama LLM) → Wybór konta/puli dostawcy → Filtrowana lista modeli.
   - Reguła walidacyjna `ValidModelForAgent` weryfikuje dostępność i stan włączenia wybranego modelu.
   - W przypadku wyłączenia lub usunięcia modelu z rejestru, powiązani agenci są w panelu oznaczani widoczną etykietą ostrzegawczą „Model niedostępny”.
