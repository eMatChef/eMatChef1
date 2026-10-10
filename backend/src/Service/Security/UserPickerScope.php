<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Sichtbarkeit von Benutzern in Auswahllisten (Mitglied hinzufügen, Chief-MW eines Grossanlasses).
 *
 * Sichtbar sind Benutzer ohne jede Mitgliedschaft (Kandidaten) und solche mit mindestens einer Mitgliedschaft in einem
 * Department, das die suchende Person kennen darf: eigene Departments, ihr Verwaltungsbereich und (nur für die
 * Mitglieder-Auswahl eines Departments) alle Departments von dessen Organisation. Personen aus fremden Organisationen
 * werden weder gefunden noch mit Department-Namen angezeigt; sie lassen sich weiter per E-Mail einladen.
 * Der Superadmin sieht alles (`null`).
 */
final class UserPickerScope
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminCapabilityChecker $adminCapabilities,
    ) {
    }

    /**
     * @return list<string>|null null = unbeschränkt (Superadmin)
     */
    public function visibleDepartmentIds(User $caller, ?string $organisationId = null): ?array
    {
        if ($this->adminCapabilities->isSuperAdmin($caller)) {
            return null;
        }

        $ids = array_map(
            static fn (Membership $m): string => $m->getDepartmentId(),
            $this->entityManager->getRepository(Membership::class)->findBy(['userId' => $caller->getId()]),
        );
        if ($this->adminCapabilities->hasGlobalAdminRole($caller)) {
            $ids = array_merge($ids, $this->adminCapabilities->getAccessibleDepartmentIds($caller) ?? []);
        }
        if ($organisationId !== null && $organisationId !== '') {
            $ids = array_merge($ids, array_map(
                static fn (Department $d): string => $d->getId(),
                $this->entityManager->getRepository(Department::class)->findBy(['organisationId' => $organisationId]),
            ));
        }

        return array_values(array_unique($ids));
    }

    /**
     * Schränkt die Benutzerabfrage (Alias u) auf sichtbare Benutzer ein.
     *
     * @param list<string>|null $visible
     */
    public function restrict(QueryBuilder $qb, ?array $visible): void
    {
        if ($visible === null) {
            return;
        }
        $qb->andWhere(
            'NOT EXISTS (SELECT 1 FROM App\Entity\Membership mAny WHERE mAny.userId = u.id) '
            . 'OR EXISTS (SELECT 1 FROM App\Entity\Membership mVis WHERE mVis.userId = u.id AND mVis.departmentId IN (:visibleDeptIds))',
        )->setParameter('visibleDeptIds', $visible === [] ? ['-'] : $visible);
    }

    /**
     * Department-Namen einer Person, soweit sichtbar.
     *
     * @param list<string>|null $visible
     */
    public function isDepartmentVisible(?array $visible, string $departmentId): bool
    {
        return $visible === null || \in_array($departmentId, $visible, true);
    }
}
