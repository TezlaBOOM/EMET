<?php

declare(strict_types=1);

namespace App\Services\Config;

use Exception;

class ConfigSecretCryptoService
{
    /**
     * Szyfrowanie danych za pomocą Argon2id + XChaCha20-Poly1305 (libsodium)
     */
    public function encrypt(string $plaintext, string $password): string
    {
        if (empty($password)) {
            throw new Exception('Hasło do zaszyfrowania sekretów nie może być puste.');
        }

        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $key = sodium_crypto_pwhash(
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
            $password,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            '',
            $nonce,
            $key
        );

        // Zwracamy spakowany ciąg binarny lub base64: salt + nonce + ciphertext
        return base64_encode($salt.$nonce.$ciphertext);
    }

    /**
     * Odszyfrowanie danych za pomocą Argon2id + XChaCha20-Poly1305
     */
    public function decrypt(string $encodedPayload, string $password): string
    {
        if (empty($password)) {
            throw new Exception('Wymagane jest hasło do odszyfrowania pliku sekretów.');
        }

        $raw = base64_decode($encodedPayload, true);
        if ($raw === false) {
            throw new Exception('Nieprawidłowy format zaszyfrowanych danych (błąd base64).');
        }

        $headerLen = SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($raw) <= $headerLen) {
            throw new Exception('Zaszyfrowany plik jest uszkodzony lub zbyt krótki.');
        }

        $salt = substr($raw, 0, SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = substr($raw, SODIUM_CRYPTO_PWHASH_SALTBYTES, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = substr($raw, $headerLen);

        $key = sodium_crypto_pwhash(
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
            $password,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            '',
            $nonce,
            $key
        );

        if ($plaintext === false) {
            throw new Exception('Niepoprawne hasło odszyfrowania lub naruszona integralność pliku sekretów.');
        }

        return $plaintext;
    }
}
