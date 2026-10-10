# Pierwsza Konfiguracja: Agenci AI & Skille (v1.5.0)

## Krok: Konfiguracja magazynu skilli i zdolności agentów

1. **Weryfikacja uprawnień**:
   Upewnij się, że rola użytkownika posiada uprawnienia `skills.view` oraz `skills.manage`.

2. **Dysk dyskowy skilli**:
   Upewnij się, że katalog `storage/app/skills` posiada prawa zapisu dla procesu aplikacji (`chmod 775 storage/app/skills`).

3. **Dodawanie pierwszego skilla**:
   Przejdź do zakładki **Agenci AI → Skille** i kliknij **Dodaj skill**.
   - Możesz przesłać paczkę archiwum `.zip` zawierającą manifest `skill.json`.
   - Każda paczka jest automatycznie weryfikowana pod kątem sumy kontrolnej SHA-256 oraz bezpieczeństwa ścieżek (Zip-Slip).

4. **Weryfikacja kompatybilności zdolności**:
   Przed przypisaniem skilla do agenta system sprawdza zgodność wymagań (`requires`):
   - Jeśli skill wymaga dostępu do sieci (`internet`), agent musi posiadać `internet_mode` ustawiony na `allowlist` lub `open`.
   - Jeśli skill wymaga pamięci wektorowej, agent musi mieć przypiętą kolekcję wektorową.
