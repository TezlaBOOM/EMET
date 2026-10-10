## Opis zmian (Summary)

<!-- Krótki opis wprowadzonych zmian i powiązanie ze specyfikacją / issue -->

## Lista kontrolna reguły dokumentacyjnej (v1.5 §0)

- [ ] Zaktualizowano sekcję `[Unreleased]` w pliku `CHANGELOG.md`
- [ ] Zaktualizowano lub utworzono fragment `first-setup.md` w odpowiednich modułach (`modules/<Module>/docs/first-setup.md`)
- [ ] Zaktualizowano klasę `ConfigSectionInterface` modułu w przypadku zmian w schemacie danych
- [ ] Zaktualizowano dokumentację techniczną w `docs/` oraz w manifeście `module.json`
- [ ] Uruchomiono i zweryfikowano test spójności dokumentacji: `php artisan docs:check`
- [ ] Testy jednostkowe i integracyjne przechodzą na zielono (`php artisan test`)
- [ ] Kod sformatowany zgodnie ze standardem: `vendor/bin/pint --format agent`
