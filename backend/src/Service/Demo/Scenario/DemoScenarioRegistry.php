<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Sammelt alle Szenarien per Tag `app.demo_scenario`; nur die drei bekannten Schlüssel, je einmal. */
final class DemoScenarioRegistry
{
    /** @var array<string, DemoScenarioInterface>|null */
    private ?array $byKey = null;

    /** @param iterable<DemoScenarioInterface> $scenarios */
    public function __construct(
        #[AutowireIterator(DemoScenarioInterface::TAG)]
        private iterable $scenarios = [],
    ) {
    }

    /** @return array<string, DemoScenarioInterface> */
    public function all(): array
    {
        if ($this->byKey === null) {
            $byKey = [];
            foreach ($this->scenarios as $scenario) {
                $key = $scenario->key();
                if (!\in_array($key, DemoScenarioKey::all(), true)) {
                    throw new DemoScenarioException(sprintf('Unbekannter Szenario-Schlüssel «%s».', $key));
                }
                if (isset($byKey[$key])) {
                    throw new DemoScenarioException(sprintf('Szenario-Schlüssel «%s» ist doppelt registriert.', $key));
                }
                $byKey[$key] = $scenario;
            }
            ksort($byKey);
            $this->byKey = $byKey;
        }

        return $this->byKey;
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    public function get(string $key): DemoScenarioInterface
    {
        return $this->all()[$key] ?? throw new DemoScenarioException(sprintf('Szenario «%s» ist nicht registriert.', $key));
    }
}
