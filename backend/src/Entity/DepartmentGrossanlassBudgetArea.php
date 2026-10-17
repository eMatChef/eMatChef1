<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Budgetlimit eines Bereichs der Beschaffung (Material oder Logistik). Das Gesamtbudget ist die Summe beider Limits und
 * wird nicht separat gespeichert.
 */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_budget_area')]
class DepartmentGrossanlassBudgetArea
{
    public const AREA_MATERIAL = 'material';
    public const AREA_LOGISTIK = 'logistik';

    /** @var list<string> */
    public const AREAS = [self::AREA_MATERIAL, self::AREA_LOGISTIK];

    #[ORM\Id]
    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 16)]
    private string $area;

    #[ORM\Column(name: 'limit_chf', type: 'decimal', precision: 12, scale: 2)]
    private string $limitChf = '0.00';

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct(string $departmentId, string $area)
    {
        $this->departmentId = $departmentId;
        $this->area = $area;
        $this->updatedAt = new \DateTime();
    }

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function getArea(): string
    {
        return $this->area;
    }

    public function getLimitChf(): string
    {
        return $this->limitChf;
    }

    public function setLimitChf(string $limitChf): self
    {
        $this->limitChf = $limitChf;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }
}
