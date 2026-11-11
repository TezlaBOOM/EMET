<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Contracts\Skills\FederatedSkillHubInterface;
use App\Models\FederatedRegistry;
use App\Models\Skill;
use App\Models\SkillVersion;
use RuntimeException;

class FederatedSkillHubService implements FederatedSkillHubInterface
{
    public function generateKeypair(): array
    {
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $secretKey = sodium_crypto_sign_secretkey($keypair);

        return [
            'public_key_hex' => bin2hex($publicKey),
            'secret_key_hex' => bin2hex($secretKey),
        ];
    }

    public function signManifest(array $manifest, string $secretKeyHex): string
    {
        $canonicalJson = $this->canonicalizeManifest($manifest);
        $secretKey = hex2bin($secretKeyHex);
        if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('Nieprawidłowy klucz prywatny Ed25519.');
        }

        $signature = sodium_crypto_sign_detached($canonicalJson, $secretKey);

        return bin2hex($signature);
    }

    public function verifyManifestSignature(array $manifest, string $signatureHex, string $publicKeyHex): bool
    {
        $canonicalJson = $this->canonicalizeManifest($manifest);
        $signature = @hex2bin($signatureHex);
        $publicKey = @hex2bin($publicKeyHex);

        if (
            $signature === false
            || $publicKey === false
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
        ) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $canonicalJson, $publicKey);
    }

    public function installVerifiedPackage(FederatedRegistry $registry, array $packageData): Skill
    {
        $manifest = is_array($packageData['manifest'] ?? null) ? $packageData['manifest'] : [];
        $signatureHex = (string) ($packageData['signature_ed25519'] ?? '');

        if (! $this->verifyManifestSignature($manifest, $signatureHex, $registry->public_key_ed25519)) {
            throw new RuntimeException('Weryfikacja podpisu kryptograficznego Ed25519 paczki zakończona niepowodzeniem.');
        }

        $slug = (string) ($manifest['slug'] ?? '');
        $name = (string) ($manifest['name'] ?? $slug);
        $versionStr = (string) ($manifest['version'] ?? '1.0.0');
        $definition = is_array($manifest['definition'] ?? null) ? $manifest['definition'] : ['entrypoint' => 'index.php'];
        $expectedChecksum = $manifest['checksum_sha256'] ?? null;

        $calculatedChecksum = hash('sha256', json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
        if ($expectedChecksum !== null && $expectedChecksum !== $calculatedChecksum) {
            throw new RuntimeException('Niezgodność sumy kontrolnej SHA-256 definicji paczki.');
        }

        $skill = Skill::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => (string) ($manifest['description'] ?? 'Paczka ze sfederowanego katalogu '.$registry->name),
                'type' => (string) ($manifest['type'] ?? Skill::TYPE_PACKAGE),
                'status' => Skill::STATUS_ACTIVE,
                'author' => (string) ($manifest['author'] ?? $registry->name),
                'tags' => is_array($manifest['tags'] ?? null) ? $manifest['tags'] : ['federated', $registry->slug],
                'source_ref' => "federated://{$registry->slug}/{$slug}@{$versionStr}",
            ]
        );

        $version = SkillVersion::updateOrCreate(
            [
                'skill_id' => $skill->id,
                'version' => $versionStr,
            ],
            [
                'definition' => $definition,
                'checksum' => $calculatedChecksum,
                'changelog' => (string) ($manifest['changelog'] ?? "Zainstalowano zweryfikowaną kryptograficznie wersję {$versionStr} z {$registry->name}"),
            ]
        );

        $skill->update(['current_version_id' => $version->id]);

        return $skill->fresh(['currentVersion']) ?? $skill;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function canonicalizeManifest(array $manifest): string
    {
        ksort($manifest);

        return json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
