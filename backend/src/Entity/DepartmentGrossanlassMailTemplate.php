<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'department_grossanlass_mail_template')]
class DepartmentGrossanlassMailTemplate
{
    public const KIND_ANFRAGE = 'anfrage';
    public const KIND_DANK_ABSAGE = 'dank_absage';
    public const KIND_ZUSAGE_OK = 'zusage_ok';
    public const KIND_NICHT_GENOMMEN = 'nicht_genommen';
    public const KIND_NEHMEN = 'nehmen';
    public const KIND_NACHFASSEN = 'nachfassen';

    /** @var list<string> */
    public const OPTIONAL_KINDS = [
        self::KIND_DANK_ABSAGE,
        self::KIND_ZUSAGE_OK,
        self::KIND_NICHT_GENOMMEN,
        self::KIND_NEHMEN,
        self::KIND_NACHFASSEN,
    ];

    /** @var list<string> */
    public const KINDS = [
        self::KIND_ANFRAGE,
        self::KIND_DANK_ABSAGE,
        self::KIND_ZUSAGE_OK,
        self::KIND_NICHT_GENOMMEN,
        self::KIND_NEHMEN,
        self::KIND_NACHFASSEN,
    ];

    #[ORM\Id]
    #[ORM\Column(name: 'department_id', type: 'string', length: 12, columnDefinition: 'CHARACTER(12) NOT NULL')]
    private string $departmentId;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $kind;

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Department $department;

    #[ORM\Column(type: 'string', length: 255)]
    private string $subject = '';

    #[ORM\Column(type: 'text')]
    private string $body = '';

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): self
    {
        $this->department = $department;
        $this->departmentId = $department->getId();

        return $this;
    }

    public function setKind(string $kind): self
    {
        $this->kind = $kind;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }
}
