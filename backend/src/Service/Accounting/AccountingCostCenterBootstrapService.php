<?php

namespace App\Service\Accounting;

use App\Entity\AccountingCostCenter;
use App\Entity\Department;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Legt Standard-Kostenstellen für ein neues Department an (idempotent).
 */
final class AccountingCostCenterBootstrapService
{
    /**
     * @return int Anzahl neu angelegter Kostenstellen (0 wenn bereits welche existieren)
     */
    public function ensureDefaultCostCenters(EntityManagerInterface $em, Department $department): int
    {
        $count = (int) $em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(AccountingCostCenter::class, 'c')
            ->where('c.department = :d')
            ->setParameter('d', $department)
            ->getQuery()
            ->getSingleScalarResult();

        if ($count > 0) {
            return 0;
        }

        $defaults = [
            ['name' => 'Allgemein', 'description' => 'Nicht zugeordnete oder übergreifende Kosten', 'sort_order' => 0],
            ['name' => 'Material & Einkauf', 'description' => 'Anschaffungen', 'sort_order' => 10],
            ['name' => 'Reparatur & Werkstatt', 'description' => 'Intern und extern', 'sort_order' => 20],
            ['name' => 'Vermietung', 'description' => 'Kosten rund um Vermietung', 'sort_order' => 30],
        ];

        $created = 0;
        foreach ($defaults as $def) {
            $cc = new AccountingCostCenter();
            $cc->setId(IdGenerator::generate13Unique($em, AccountingCostCenter::class, 'ks'));
            $cc->setDepartment($department);
            $cc->setName($def['name']);
            $cc->setDescription($def['description']);
            $cc->setSortOrder($def['sort_order']);
            $em->persist($cc);
            $created++;
        }

        $em->flush();

        return $created;
    }
}
