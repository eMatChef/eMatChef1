<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo\Scenario;

use App\Entity\Department;
use App\Entity\Organisation;
use App\Service\Demo\Scenario\AbstractDemoScenario;
use App\Service\Demo\Scenario\DemoScenarioInterface;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use App\Service\Demo\Scenario\SeedContext;
use App\Service\Demo\Scenario\SeedResult;
use PHPUnit\Framework\TestCase;

abstract class ScenarioTestCase extends TestCase
{
    protected function department(string $id, bool $demo = true, bool $grossanlass = false, ?string $key = null): Department
    {
        $org = new Organisation();
        $org->setId('org_t1');
        $d = new Department();
        $d->setId($id);
        $d->setOrganisation($org);
        $d->setName('Dept ' . $id);
        $d->setDemoMode($demo);
        $d->setIsGrossanlass($grossanlass);
        $d->setDemoScenarioKey($key);

        return $d;
    }

    /** Konfigurierbares Test-Szenario. */
    protected function scenario(string $key, bool $grossanlass = false, bool $resetSupported = false, ?\Closure $onReset = null, ?\Closure $onSync = null): DemoScenarioInterface
    {
        return new class($key, $grossanlass, $resetSupported, $onReset, $onSync) extends AbstractDemoScenario {
            public function __construct(private string $k, private bool $ga, private bool $reset, private ?\Closure $onReset, private ?\Closure $onSync)
            {
            }

            public function key(): string
            {
                return $this->k;
            }

            public function label(): string
            {
                return $this->k;
            }

            public function expectsGrossanlass(): bool
            {
                return $this->ga;
            }

            public function supportsReset(): bool
            {
                return $this->reset;
            }

            protected function phaseHint(): string
            {
                return 'test';
            }

            public function sync(SeedContext $context): SeedResult
            {
                return $this->onSync ? ($this->onSync)($context) : parent::sync($context);
            }

            public function reset(SeedContext $context): SeedResult
            {
                return $this->onReset ? ($this->onReset)($context) : parent::reset($context);
            }
        };
    }

    protected function registry(DemoScenarioInterface ...$scenarios): DemoScenarioRegistry
    {
        return new DemoScenarioRegistry($scenarios);
    }
}
