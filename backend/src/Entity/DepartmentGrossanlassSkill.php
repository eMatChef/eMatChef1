<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Eintrag im Fähigkeitenkatalog eines GA-Departments (Helferprofile und Funktionswünsche des Personalbedarfs). */
#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_skill')]
class DepartmentGrossanlassSkill
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $id;

    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Column(type: 'string', length: 80)]
    private string $name;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct(string $id, string $departmentId, string $name)
    {
        $this->id = $id;
        $this->departmentId = $departmentId;
        $this->name = $name;
        $this->createdAt = new \DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getDepartmentId(): string { return $this->departmentId; }
    public function getName(): string { return $this->name; }
}
