<?php

declare(strict_types=1);

namespace App\Contracts\Llm;

interface SelfReflectionCriticInterface
{
    /**
     * Ocenia wygenerowaną odpowiedź pod kątem oczekiwanego kontraktu (np. poprawny JSON, wymagane klucze/sekcje).
     *
     * @param  array<string, mixed>  $criteria
     * @return array{
     *     is_valid: bool,
     *     score: float,
     *     issues: array<int, string>,
     *     repaired_content: string|null
     * }
     */
    public function inspectResponse(string $content, array $criteria = []): array;
}

