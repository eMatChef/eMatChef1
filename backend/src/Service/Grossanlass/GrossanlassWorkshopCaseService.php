<?php

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassWorkshopCase;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class GrossanlassWorkshopCaseService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Department $department, User $user): array
    {
        $this->assertManage($department, $user);

        $rows = $this->entityManager->getRepository(DepartmentGrossanlassWorkshopCase::class)
            ->findBy(['departmentId' => $department->getId()], ['createdAt' => 'DESC']);

        return array_map(fn (DepartmentGrossanlassWorkshopCase $row) => $this->serialize($row), $rows);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(Department $department, User $user, array $data): array
    {
        $this->assertManage($department, $user);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Titel ist erforderlich');
        }

        $row = new DepartmentGrossanlassWorkshopCase();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::WORKSHOP_CASE,
            DepartmentGrossanlassWorkshopCase::class,
        ));
        $row->setDepartment($department);
        $row->setCreatedById($user->getId());
        $row->setTitle($title);
        $this->applyFields($row, $data, true);
        $this->entityManager->persist($row);
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(Department $department, User $user, string $caseId, array $data): array
    {
        $this->assertManage($department, $user);
        $row = $this->find($department, $caseId);
        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ($title === '') {
                throw new \InvalidArgumentException('Titel ist erforderlich');
            }
            $row->setTitle($title);
        }
        $this->applyFields($row, $data, false);
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyFields(DepartmentGrossanlassWorkshopCase $row, array $data, bool $isCreate): void
    {
        if (array_key_exists('description', $data) || $isCreate) {
            $row->setDescription(trim((string) ($data['description'] ?? '')));
        }
        if (array_key_exists('material_label', $data) || $isCreate) {
            $row->setMaterialLabel(trim((string) ($data['material_label'] ?? '')));
        }
        if (array_key_exists('owner_firm_name', $data) || $isCreate) {
            $row->setOwnerFirmName(trim((string) ($data['owner_firm_name'] ?? '')));
        }
        if (array_key_exists('origin', $data) || $isCreate) {
            $origin = (string) ($data['origin'] ?? DepartmentGrossanlassWorkshopCase::ORIGIN_LOAN);
            if (!in_array($origin, DepartmentGrossanlassWorkshopCase::ORIGINS, true)) {
                throw new \InvalidArgumentException('Ungültige Herkunft');
            }
            $row->setOrigin($origin);
        }
        if (array_key_exists('path', $data) || $isCreate) {
            $path = (string) ($data['path'] ?? DepartmentGrossanlassWorkshopCase::PATH_REPAIR);
            if (!in_array($path, DepartmentGrossanlassWorkshopCase::PATHS, true)) {
                throw new \InvalidArgumentException('Ungültiger Weg');
            }
            $row->setPath($path);
            if ($isCreate && !array_key_exists('status', $data)) {
                $row->setStatus(
                    $path === DepartmentGrossanlassWorkshopCase::PATH_OWNER
                        ? DepartmentGrossanlassWorkshopCase::STATUS_WAITING_OWNER
                        : DepartmentGrossanlassWorkshopCase::STATUS_IN_PROGRESS,
                );
            }
        }
        if (array_key_exists('status', $data)) {
            $status = (string) $data['status'];
            if (!in_array($status, DepartmentGrossanlassWorkshopCase::STATUSES, true)) {
                throw new \InvalidArgumentException('Ungültiger Status');
            }
            $row->setStatus($status);
        }
    }

    private function find(Department $department, string $id): DepartmentGrossanlassWorkshopCase
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassWorkshopCase::class)->find($id);
        if (!$row instanceof DepartmentGrossanlassWorkshopCase || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Fall nicht gefunden');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(DepartmentGrossanlassWorkshopCase $row): array
    {
        $createdBy = $this->entityManager->getRepository(User::class)->find($row->getCreatedById());

        return [
            'id' => $row->getId(),
            'title' => $row->getTitle(),
            'description' => $row->getDescription(),
            'origin' => $row->getOrigin(),
            'owner_firm_name' => $row->getOwnerFirmName(),
            'material_label' => $row->getMaterialLabel(),
            'path' => $row->getPath(),
            'status' => $row->getStatus(),
            'created_by_id' => $row->getCreatedById(),
            'created_by_name' => $createdBy?->getProfile()?->getDisplayName() ?: null,
            'created_at' => $row->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $row->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function assertManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für die Werkstatt');
        }
    }
}
