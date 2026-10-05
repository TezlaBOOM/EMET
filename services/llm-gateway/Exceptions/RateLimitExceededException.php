<?php

declare(strict_types=1);

namespace App\Services\LlmGateway\Exceptions;

use RuntimeException;

class RateLimitExceededException extends RuntimeException
{
    public function __construct(
        string $message = 'Rate limit exceeded (HTTP 429)',
        public int $retryAfterSeconds = 60,
        public ?int $accountId = null,
        int $code = 429,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
