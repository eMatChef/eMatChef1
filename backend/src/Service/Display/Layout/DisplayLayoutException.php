<?php

namespace App\Service\Display\Layout;

final class DisplayLayoutException extends \RuntimeException
{
    /**
     * @param list<array{path: string, code: string, message: string}> $errors
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $message,
        public readonly array $errors = [],
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }
}
