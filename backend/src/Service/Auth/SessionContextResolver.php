<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Membership;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassDepartmentSerializer;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Department-Kontext für Login-, Refresh- und Session-Antworten
 * (JwtAuthenticationSuccessSubscriber, GET /api/auth/session).
 *
 * Startkontext: gültiges last_used_department → primäres Department → erstes Department → null.
 */
final class SessionContextResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     departments: list<array<string, mixed>>,
     *     primary_department: string|null,
     *     last_used_department: string|null
     * }
     */
    public function resolve(User $user): array
    {
        /** @var list<Membership> $memberships */
        $memberships = $this->entityManager->getRepository(Membership::class)
            ->createQueryBuilder('m')
            ->innerJoin('m.department', 'd')
            ->leftJoin('d.grossanlassConfig', 'gc')
            ->addSelect('d', 'gc')
            ->where('m.userId = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getResult();

        $departments = [];
        $primaryDepartment = null;
        foreach ($memberships as $m) {
            $deptSerialized = GrossanlassDepartmentSerializer::serializeDepartmentForMembership($m->getDepartment());
            $deptData = [
                'id' => $deptSerialized['id'],
                'name' => $deptSerialized['name'],
                'organisation_id' => $deptSerialized['organisation_id'],
                'role' => $m->getRole(),
                'is_primary' => $m->getIsPrimary(),
                'is_grossanlass' => $deptSerialized['is_grossanlass'],
            ];
            if (isset($deptSerialized['grossanlass_config'])) {
                $deptData['grossanlass_config'] = $deptSerialized['grossanlass_config'];
            }
            $departments[] = $deptData;

            // Primär: letzte Membership mit is_primary, sonst die erste.
            if ($m->getIsPrimary() || !$primaryDepartment) {
                $primaryDepartment = $deptData;
            }
        }

        // Gespeicherte Präferenz nur bei bestehender Membership, sonst primäres Department.
        $allowedIds = array_map(static fn (array $d): string => $d['id'], $departments);
        $storedLastUsedId = $user->getLastUsedDepartmentId();
        $lastUsedResolved = null;
        if ($storedLastUsedId !== null && \in_array($storedLastUsedId, $allowedIds, true)) {
            $lastUsedResolved = $storedLastUsedId;
        } elseif ($primaryDepartment !== null) {
            $lastUsedResolved = $primaryDepartment['id'];
        }

        return [
            'departments' => $departments,
            'primary_department' => $primaryDepartment !== null ? $primaryDepartment['id'] : null,
            'last_used_department' => $lastUsedResolved,
        ];
    }
}
