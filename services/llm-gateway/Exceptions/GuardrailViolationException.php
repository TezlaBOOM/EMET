<?php

declare(strict_types=1);

namespace App\Services\LlmGateway\Exceptions;

use RuntimeException;

class GuardrailViolationException extends RuntimeException
{
    /**
     * @param  list<string>  $matchedRules
     */
    public function __construct(
        string $message,
        public readonly array $matchedRules = [],
        public readonly string $severity = 'high',
    ) {
        parent::__construct($message);
    }
}

