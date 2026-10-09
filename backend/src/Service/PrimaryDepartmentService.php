<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Membership;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Primäres Department eines Benutzers: höchstens eines (partieller Unique-Index `uniq_membership_one_primary_per_user`).
 * Wechsel und Entfernen laufen in einer Transaktion; das bisherige Primär wird zuerst zurückgesetzt, weil die Reihenfolge der
 * UPDATEs beim ORM-Flush nicht garantiert ist und der Index sonst verletzt wird. Mitgliedschaften werden nie gelöscht.
 */
final class PrimaryDepartmentService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param string|null $departmentId null entfernt den Primärstatus
     * @return array{previous: string|null, current: string|null}
     *
     * @throws \InvalidArgumentException wenn keine Mitgliedschaft in $departmentId besteht (nichts geändert)
     */
    public function change(string $userId, ?string $departmentId): array
    {
        $repository = $this->entityManager->getRepository(Membership::class);
        $target = $departmentId === null ? null : $repository->findOneBy(['userId' => $userId, 'departmentId' => $departmentId]);
        if ($departmentId !== null && $target === null) {
            throw new \InvalidArgumentException('Keine Mitgliedschaft in diesem Department');
        }

        return $this->entityManager->wrapInTransaction(function () use ($userId, $departmentId, $target): array {
            $previous = null;
            $loaded = $this->entityManager->getRepository(Membership::class)->findBy(['userId' => $userId, 'isPrimary' => true]);
            foreach ($loaded as $membership) {
                $previous = $membership->getDepartmentId();
            }

            $this->entityManager->createQueryBuilder()
                ->update(Membership::class, 'm')
                ->set('m.isPrimary', ':no')
                ->where('m.userId = :user')
                ->andWhere('m.isPrimary = :yes')
                ->setParameter('no', false)
                ->setParameter('yes', true)
                ->setParameter('user', $userId)
                ->getQuery()
                ->execute();

            if ($departmentId !== null) {
                $this->entityManager->createQueryBuilder()
                    ->update(Membership::class, 'm')
                    ->set('m.isPrimary', ':yes')
                    ->where('m.userId = :user')
                    ->andWhere('m.departmentId = :department')
                    ->setParameter('yes', true)
                    ->setParameter('user', $userId)
                    ->setParameter('department', $departmentId)
                    ->getQuery()
                    ->execute();
            }
            // Bulk-UPDATEs umgehen die Identity Map: geladene Zeilen neu lesen
            foreach ($target !== null ? [...$loaded, $target] : $loaded as $membership) {
                $this->entityManager->refresh($membership);
            }

            return ['previous' => $previous, 'current' => $departmentId];
        });
    }
}
