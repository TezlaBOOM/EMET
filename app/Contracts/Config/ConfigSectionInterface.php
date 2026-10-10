<?php

declare(strict_types=1);

namespace App\Contracts\Config;

/**
 * Kontrakt rozszerzalnej sekcji eksportu i importu konfiguracji AgentHub.
 * Każdy moduł rejestruje swoje sekcje konfiguracyjne w module.json.
 */
interface ConfigSectionInterface
{
    /**
     * Unikalny klucz identyfikujący sekcję konfiguracji (np. 'agents', 'skills', 'ai').
     */
    public function key(): string;

    /**
     * Wersja schematu danych sekcji (inkrementowana przy zmianach struktury).
     */
    public function schemaVersion(): int;

    /**
     * Lista kluczy innych sekcji, od których zależy ta sekcja (kolejność importu).
     *
     * @return array<int, string>
     */
    public function dependsOn(): array;

    /**
     * Lista ścieżek do pól zawierających sekrety (klucze API, hasła, tokeny),
     * które domyślnie są wykluczane z eksportu lub szyfrowane hasłem.
     *
     * @return array<int, string>
     */
    public function secretFields(): array;

    /**
     * Strumieniowy eksport danych danej sekcji do struktury tablicowej.
     *
     * @param  array<string, mixed>  $options  Opcje eksportu (include_secrets, include_memory itp.)
     * @return iterable<string, mixed>
     */
    public function export(array $options): iterable;

    /**
     * Walidacja poprawności schematu importowanych danych przed wykonaniem planu.
     *
     * @param  array<string, mixed>  $data
     * @return array{is_valid: bool, errors: array<int, string>}
     */
    public function validate(array $data): array;

    /**
     * Generowanie planu importu (dry-run): lista rekordów do utworzenia, modyfikacji,
     * pominięcia oraz wykryte konflikty.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options  (mode: merge|overwrite|new_only)
     * @return array{
     *     create: array<int, mixed>,
     *     update: array<int, mixed>,
     *     skip: array<int, mixed>,
     *     conflicts: array<int, mixed>
     * }
     */
    public function plan(array $data, array $options): array;

    /**
     * Fizyczne zaaplikowanie zatwierdzonego planu importu w transakcji bazodanowej.
     *
     * @param  array<string, mixed>  $plan
     * @return array{success: bool, imported_count: int, updated_count: int, errors: array<int, string>}
     */
    public function import(array $plan): array;
}
