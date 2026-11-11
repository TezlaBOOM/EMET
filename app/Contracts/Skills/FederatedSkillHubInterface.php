<?php

declare(strict_types=1);

namespace App\Contracts\Skills;

use App\Models\FederatedRegistry;
use App\Models\Skill;

interface FederatedSkillHubInterface
{
    /**
     * Generuje nową parę kluczy kryptograficznych Ed25519 (libsodium) w formacie szesnastkowym.
     *
     * @return array{public_key_hex: string, secret_key_hex: string}
     */
    public function generateKeypair(): array;

    /**
     * Podpisuje kanoniczny manifest paczki kluczem prywatnym Ed25519.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function signManifest(array $manifest, string $secretKeyHex): string;

    /**
     * Weryfikuje podpis kryptograficzny Ed25519 manifestu paczki.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function verifyManifestSignature(array $manifest, string $signatureHex, string $publicKeyHex): bool;

    /**
     * Weryfikuje podpis Ed25519 oraz sumę SHA-256 paczki ze sfederowanego katalogu i instaluje/aktualizuje Skill.
     *
     * @param  array<string, mixed>  $packageData
     */
    public function installVerifiedPackage(FederatedRegistry $registry, array $packageData): Skill;
}
