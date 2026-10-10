<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/** Die Ersteinrichtung ist unvollständig: Freigabe nicht möglich (HTTP 422). */
final class GrossanlassSetupIncompleteException extends \DomainException
{
    /** @param list<array<string, mixed>> $steps */
    public function __construct(private readonly array $steps)
    {
        parent::__construct('Die Ersteinrichtung ist noch nicht vollständig.');
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return ['error' => $this->getMessage(), 'code' => 'grossanlass_setup_incomplete', 'steps' => $this->steps];
    }
}
