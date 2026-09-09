<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\DepartmentGrossanlassUnterlager;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class GrossanlassPlaceService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $appFrontendUrl,
        #[Autowire('%env(APP_PUBLIC_QR_URL)%')] private string $appPublicQrUrl,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Department $department, User $user): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canSubmitEinsatz($user, $department)
            && !$this->access->canSeeAnlassOverview($user, $department)
            && !$this->access->canOperateAusgabe($user, $department)
        ) {
            $this->assertMemberCanSeePlaces($user, $department);
        }
        $this->seedFromUnterlager($department);

        return array_map(fn (DepartmentGrossanlassPlace $row) => $this->serialize($row), $this->rows($department));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(Department $department, User $user, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canSeeAnlassOverview($user, $department)
            && !$this->access->canSubmitEinsatz($user, $department)
        ) {
            throw new \RuntimeException('Keine Berechtigung für Orte');
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Name ist erforderlich');
        }
        $row = $this->makePlace($department, $name);
        $row->setGroupId(isset($data['group_id']) ? trim((string) $data['group_id']) : null);
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    public function findByCode(string $code): ?DepartmentGrossanlassPlace
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)
            ->findOneBy(['publicCode' => $code]);

        return $row instanceof DepartmentGrossanlassPlace ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolvePublic(string $code): ?array
    {
        $row = $this->findByCode($code);
        if (!$row instanceof DepartmentGrossanlassPlace) {
            return null;
        }

        return $this->serialize($row) + [
            'entity_type' => 'ga_place',
            'department' => [
                'id' => $row->getDepartmentId(),
                'name' => $row->getDepartment()->getName(),
            ],
        ];
    }

    public function qrUrl(string $code): string
    {
        $base = trim($this->appPublicQrUrl) !== ''
            ? rtrim($this->appPublicQrUrl, '/')
            : rtrim($this->appFrontendUrl, '/');

        return $base . '/i/p/' . rawurlencode($code);
    }

    /**
     * @return list<DepartmentGrossanlassPlace>
     */
    private function rows(Department $department): array
    {
        return $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)
            ->findBy(['departmentId' => $department->getId()], ['name' => 'ASC']);
    }

    private function seedFromUnterlager(Department $department): void
    {
        $existing = [];
        foreach ($this->rows($department) as $row) {
            if ($row->getUnterlagerId()) {
                $existing[$row->getUnterlagerId()] = true;
            }
        }
        $nodes = $this->entityManager->getRepository(DepartmentGrossanlassUnterlager::class)
            ->findBy(['hostDepartmentId' => $department->getId()]);
        $added = false;
        foreach ($nodes as $node) {
            if (!$node instanceof DepartmentGrossanlassUnterlager || isset($existing[$node->getId()])) {
                continue;
            }
            $place = $this->makePlace($department, $node->getName());
            $place->setUnterlagerId($node->getId());
            $added = true;
        }
        if ($added) {
            $this->entityManager->flush();
        }
    }

    private function makePlace(Department $department, string $name): DepartmentGrossanlassPlace
    {
        $row = new DepartmentGrossanlassPlace();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::PLACE,
            DepartmentGrossanlassPlace::class,
        ));
        $row->setDepartment($department);
        $row->setName($name);
        $row->setPublicCode(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::PLACE,
            DepartmentGrossanlassPlace::class,
            'publicCode',
        ));
        $this->entityManager->persist($row);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(DepartmentGrossanlassPlace $row): array
    {
        return [
            'id' => $row->getId(),
            'name' => $row->getName(),
            'group_id' => $row->getGroupId(),
            'unterlager_id' => $row->getUnterlagerId(),
            'public_code' => $row->getPublicCode(),
            'qr_url' => $this->qrUrl($row->getPublicCode()),
        ];
    }

    private function assertMemberCanSeePlaces(User $user, Department $department): void
    {
        $role = $this->access->membershipRole($user, $department);
        if ($role === null) {
            throw new \RuntimeException('Keine Berechtigung für Orte');
        }
    }
}
