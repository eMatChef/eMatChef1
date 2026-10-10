<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use App\Entity\Department;

/**
 * Gemeinsame Grundprüfungen. Konkrete Szenarien überschreiben sync()/reset()/verifyContent() in den späteren Phasen.
 */
abstract class AbstractDemoScenario implements DemoScenarioInterface
{
    abstract protected function phaseHint(): string;

    public function supportsReset(): bool
    {
        return false;
    }

    public function sync(SeedContext $context): SeedResult
    {
        return SeedResult::notImplemented(sprintf('Sync für «%s» ist noch nicht implementiert (%s).', $this->key(), $this->phaseHint()));
    }

    public function reset(SeedContext $context): SeedResult
    {
        throw new DemoScenarioException(sprintf('Reset für «%s» ist nicht verfügbar.', $this->key()));
    }

    public function verify(SeedContext $context): array
    {
        $department = $context->department();
        if ($department === null) {
            return [];
        }

        $violations = [];
        if (!$department->isDemoMode()) {
            $violations[] = 'Department trägt einen Szenario-Schlüssel, aber nicht demo_mode.';
        }
        if ($department->isGrossanlass() !== $this->expectsGrossanlass()) {
            $violations[] = $this->expectsGrossanlass()
                ? 'Department ist kein Grossanlass-Department.'
                : 'Department ist ein Grossanlass-Department.';
        }
        foreach ($context->foreignLedgerRecords() as $record) {
            $violations[] = sprintf('Ledger-Eintrag «%s» zeigt auf ein anderes Department.', $record->getSeedKey());
        }

        return array_merge($violations, $this->verifyContent($context));
    }

    /** @return list<string> */
    protected function verifyContent(SeedContext $context): array
    {
        return [];
    }

    public function clockOrigin(Department $department): ?\DateTimeInterface
    {
        return null;
    }
}
