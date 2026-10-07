<?php

declare(strict_types=1);

namespace App\Service\Auth;

final class HitobitoApiException extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?int $statusCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
