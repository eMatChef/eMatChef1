<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\DepartmentGrossanlassHelper;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Transaktion und Sperre pro Helfer für alle Schreibwege des Helferpools (Verfügbarkeit, Zuteilung). Wer einen
 * Helfer ändert, sperrt zuerst dessen Zeile (`FOR UPDATE`); gleichzeitige Zuteilungen desselben Helfers laufen damit
 * nacheinander und sehen die jeweils vorherige. Ohne Exclusion Constraint, damit begründete Ausnahmen möglich bleiben.
 */
final class GrossanlassStaffingLocks
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Wie `wrapInTransaction`, schliesst aber bei einem fachlichen Konflikt den EntityManager nicht.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function transactional(callable $fn): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $result = $fn();
            $connection->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Sperrt den Helfer bis Transaktionsende und lädt ihn frisch. Nur innerhalb von {@see transactional()}.
     *
     * @throws \InvalidArgumentException Helfer unbekannt oder aus einem anderen Grossanlass
     */
    public function lockHelper(string $departmentId, string $helperId): DepartmentGrossanlassHelper
    {
        $helper = $this->entityManager->find(DepartmentGrossanlassHelper::class, $helperId, LockMode::PESSIMISTIC_WRITE);
        if (!$helper instanceof DepartmentGrossanlassHelper || $helper->getDepartmentId() !== $departmentId) {
            throw new \InvalidArgumentException('Helfer nicht gefunden');
        }
        $this->entityManager->refresh($helper);

        return $helper;
    }
}
