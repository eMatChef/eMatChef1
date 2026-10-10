<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use App\Entity\Department;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Zuordnung Szenario ↔ Department über `department.demo_scenario_key`. Nie über Namen.
 * Vergeben wird der Schlüssel nur ausdrücklich (Operator) und nur an Demo-Departments; echte Departments
 * (ohne demo_mode) werden nie angefasst.
 */
class DemoScenarioIdentity
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DemoScenarioRegistry $registry,
    ) {
    }

    public function findDepartment(string $scenarioKey): ?Department
    {
        $department = $this->entityManager->getRepository(Department::class)->findOneBy(['demoScenarioKey' => $scenarioKey]);

        return $department instanceof Department ? $department : null;
    }

    public function assign(Department $department, string $scenarioKey): void
    {
        $scenario = $this->registry->get($scenarioKey);

        if (!$department->isDemoMode()) {
            throw new DemoScenarioException(sprintf('Department «%s» ist kein Demo-Department (demo_mode); es wird nicht angefasst.', $department->getName()));
        }
        if ($department->isGrossanlass() !== $scenario->expectsGrossanlass()) {
            throw new DemoScenarioException(sprintf(
                'Department «%s» passt nicht zu «%s» (%s erwartet).',
                $department->getName(),
                $scenarioKey,
                $scenario->expectsGrossanlass() ? 'Grossanlass' : 'normales Department',
            ));
        }
        $current = $department->getDemoScenarioKey();
        if ($current === $scenarioKey) {
            return;
        }
        if ($current !== null) {
            throw new DemoScenarioException(sprintf('Department «%s» gehört bereits zum Szenario «%s».', $department->getName(), $current));
        }
        $holder = $this->findDepartment($scenarioKey);
        if ($holder !== null && $holder->getId() !== $department->getId()) {
            throw new DemoScenarioException(sprintf('Szenario «%s» ist bereits dem Department «%s» zugeordnet.', $scenarioKey, $holder->getName()));
        }

        $department->setDemoScenarioKey($scenarioKey);
        $this->entityManager->flush();
    }
}
