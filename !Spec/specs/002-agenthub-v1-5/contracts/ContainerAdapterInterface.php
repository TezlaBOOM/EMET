<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

/**
 * Rozszerzenie kontraktu adaptera integracji o obsługę adopcji
 * i bezpiecznej konfiguracji kontenerów Docker (v1.5.0).
 */
interface ContainerAdapterInterface
{
    /**
     * Sygnatury wykrywania kontenera powiązanego z danym adapterem (obrazy, etykiety, porty).
     *
     * @return array<string, mixed>
     */
    public function containerSignatures(): array;

    /**
     * Inspekcja stanu, wersji i konfiguracji podłączonego kontenera.
     *
     * @return array{
     *     name: string,
     *     image: string,
     *     status: string,
     *     ports: array<int, int>,
     *     detected_type: string,
     *     is_compatible: bool,
     *     details: array<string, mixed>
     * }
     */
    public function inspectContainer(string $containerId): array;

    /**
     * Zamknięta biała lista ścieżek wewnątrz kontenera, do których wolno zapisywać pliki konfiguracyjne.
     *
     * @return array<int, string>
     */
    public function configWritablePaths(): array;

    /**
     * Zamknięta biała lista szablonów poleceń dozwolonych do wywołania wewnątrz kontenera (brak wolnego exec).
     *
     * @return array<int, string>
     */
    public function execAllowlist(): array;

    /**
     * Schemat formularza ręcznej konfiguracji kontenera (JSON Schema).
     *
     * @return array<string, mixed>
     */
    public function configSchema(): array;

    /**
     * Ręczna aplikacja konfiguracji do kontenera z weryfikacją diffa.
     *
     * @param  array<string, mixed>  $config
     * @return array{success: bool, diff: array<string, mixed>, error: ?string}
     */
    public function configureContainer(string $containerId, array $config): array;

    /**
     * Automatyczna konfiguracja kontenera w oparciu o profil (auto-wybór modelu, rejestracja tokenu).
     *
     * @param  array<string, mixed>  $profileSteps
     * @return array{success: bool, log: string, error: ?string}
     */
    public function autoConfigure(string $containerId, array $profileSteps): array;

    /**
     * Lista modeli udostępnianych przez kontener (np. w przypadku kontenera Ollama).
     *
     * @return array<int, array{id: string, name: string, size_bytes: int}>
     */
    public function listContainerModels(string $containerId): array;
}
