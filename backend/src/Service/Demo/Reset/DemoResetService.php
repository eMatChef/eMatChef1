<?php

declare(strict_types=1);

namespace App\Service\Demo\Reset;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Demo\DemoEnvironmentGuard;
use App\Service\Demo\Scenario\DemoScenarioException;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use App\Service\Demo\Scenario\DemoScenarioRunner;
use App\Service\Demo\Scenario\SeedResult;
use App\Service\Grossanlass\GrossanlassAccessRoles;
use App\Service\Grossanlass\GrossanlassAccessService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Demo-Reset für die Oberfläche (gelber Testumgebungs-Balken): Status, Vorschau (Dry-Run) und Ausführung.
 *
 * Schutz (jeweils serverseitig, in dieser Reihenfolge):
 *  - Umgebung: {@see DemoEnvironmentGuard::destructiveDenial()} (lokal; Develop nur mit EMATCHEF_DEMO_DESTRUCTIVE=1;
 *    Staging und Production nie),
 *  - nur ausdrücklich unterstützte Szenarien ({@see self::SUPPORTED}) und nur deren Szenario-Department,
 *  - Berechtigung: Mitglied mit Freigabe-Recht (MW, OK-Leitung) oder Administrator dieses Departments,
 *  - Ausführung nur mit Szenario-Bestätigung und dem Plan-Hash der Vorschau; weicht der Plan beim Ausführen ab,
 *    wird alles zurückgerollt.
 */
class DemoResetService
{
    /** Szenarien, die die Oberfläche zurücksetzen darf. */
    public const SUPPORTED = [DemoScenarioKey::GROSSANLASS_SETUP];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DemoEnvironmentGuard $guard,
        private DemoScenarioRegistry $registry,
        private DemoScenarioRunner $runner,
        private GrossanlassAccessService $access,
        private AdminCapabilityChecker $adminCapabilities,
    ) {
    }

    /** @return array{supported: bool, scenario: ?string, label: ?string, reason: ?string} */
    public function status(User $user, string $departmentId): array
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        $reason = $this->denial($user, $department);
        if ($reason !== null || !$department instanceof Department) {
            return ['supported' => false, 'scenario' => null, 'label' => null, 'reason' => $reason];
        }
        $scenario = $this->registry->get((string) $department->getDemoScenarioKey());

        return ['supported' => true, 'scenario' => $scenario->key(), 'label' => $scenario->label(), 'reason' => null];
    }

    /** @return array<string, mixed> Plan inklusive `plan_hash` */
    public function preview(User $user, string $departmentId): array
    {
        $department = $this->requireAllowed($user, $departmentId);
        $result = $this->runner->previewReset($this->registry->get((string) $department->getDemoScenarioKey()));

        return $this->payload($result);
    }

    /** @return array<string, mixed> */
    public function execute(User $user, string $departmentId, string $planHash, string $confirm): array
    {
        $department = $this->requireAllowed($user, $departmentId);
        $scenario = $this->registry->get((string) $department->getDemoScenarioKey());
        if ($confirm !== $scenario->key()) {
            throw new DemoScenarioException('Bestätigung fehlt oder stimmt nicht mit dem Szenario überein.');
        }
        if ($planHash === '') {
            throw new DemoScenarioException('Plan-Hash der Vorschau fehlt.');
        }

        $result = $this->runner->reset($scenario, function (SeedResult $result) use ($planHash): void {
            if (($result->data['blocked'] ?? []) !== []) {
                throw new DemoScenarioException('Reset ist blockiert; nichts wurde verändert.');
            }
            if (!hash_equals(self::hash($result->data), $planHash)) {
                throw new DemoScenarioException('Der Datenstand hat sich seit der Vorschau geändert; Reset abgebrochen (nichts wurde verändert). Bitte Vorschau neu erstellen.');
            }
        });

        return $this->payload($result);
    }

    /** @param array<string, mixed> $plan */
    public static function hash(array $plan): string
    {
        return hash('sha256', json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function payload(SeedResult $result): array
    {
        return ['message' => $result->message, 'plan' => $result->data, 'plan_hash' => self::hash($result->data), 'notes' => $result->notes];
    }

    private function requireAllowed(User $user, string $departmentId): Department
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        $reason = $this->denial($user, $department);
        if ($reason !== null || !$department instanceof Department) {
            throw new DemoScenarioException($reason ?? 'Department nicht gefunden.');
        }

        return $department;
    }

    private function denial(User $user, ?Department $department): ?string
    {
        if ($denial = $this->guard->destructiveDenial()) {
            return $denial;
        }
        if (!$department instanceof Department) {
            return 'Department nicht gefunden.';
        }
        $key = $department->getDemoScenarioKey();
        if (!$department->isDemoMode() || $key === null || !\in_array($key, self::SUPPORTED, true) || !$this->registry->has($key)) {
            return 'Dieses Department ist kein unterstütztes Demo-Szenario.';
        }
        $scenario = $this->registry->get($key);
        if (!$scenario->supportsReset()) {
            return 'Reset ist für dieses Szenario nicht freigegeben.';
        }
        $role = $this->access->membershipRole($user, $department);
        $allowed = ($role !== null && GrossanlassAccessRoles::canReleaseSetup($role))
            || $this->adminCapabilities->canAdministerDepartment($user, (string) $department->getId());
        if (!$allowed) {
            return 'Keine Berechtigung für den Demo-Reset (nur Materialchef, OK-Leitung oder Administration).';
        }

        return null;
    }
}
