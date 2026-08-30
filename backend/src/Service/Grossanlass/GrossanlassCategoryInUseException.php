<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

final class GrossanlassCategoryInUseException extends \InvalidArgumentException
{
    /**
     * @param array{
     *     code: string,
     *     lines: list<array<string, mixed>>,
     *     inquiries: list<array<string, mixed>>
     * } $payload
     */
    public function __construct(
        private array $payload,
    ) {
        parent::__construct('Kategorie hat noch Positionen oder Anfragen');
    }

    /**
     * @return array{
     *     error: string,
     *     code: string,
     *     lines: list<array<string, mixed>>,
     *     inquiries: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'error' => $this->getMessage(),
            'code' => $this->payload['code'],
            'lines' => $this->payload['lines'],
            'inquiries' => $this->payload['inquiries'],
        ];
    }
}
