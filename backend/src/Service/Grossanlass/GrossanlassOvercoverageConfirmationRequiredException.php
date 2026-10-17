<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/**
 * Die Buchung würde den Bedarf einer Position mehrfach decken (z. B. Leihe plus Kauf der ganzen Menge)
 * und braucht eine bewusste Bestätigung (confirm_overcoverage).
 */
final class GrossanlassOvercoverageConfirmationRequiredException extends \InvalidArgumentException
{
    public function __construct(
        public readonly int $need,
        public readonly int $alreadyCovered,
        public readonly int $requested,
    ) {
        parent::__construct(sprintf(
            'Bedarf %d, bereits gedeckt %d: mit %d mehr wären es %d zu viel. Überdeckung bewusst bestätigen.',
            $need,
            $alreadyCovered,
            $requested,
            max(0, $alreadyCovered + $requested - $need),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $open = max(0, $this->need - $this->alreadyCovered);

        return [
            'error' => $this->getMessage(),
            'code' => 'overcoverage_confirmation_required',
            'need' => $this->need,
            'already_covered' => $this->alreadyCovered,
            'requested' => $this->requested,
            'open' => $open,
            'over' => max(0, $this->alreadyCovered + $this->requested - $this->need),
        ];
    }

    /**
     * Wirft, wenn $requested zusätzlich zu $alreadyCovered den Bedarf überschreitet und nicht bestätigt ist.
     */
    public static function assertWithin(int $need, int $alreadyCovered, int $requested, bool $confirmed): void
    {
        if (!$confirmed && $requested > 0 && $alreadyCovered + $requested > $need) {
            throw new self($need, $alreadyCovered, $requested);
        }
    }
}
