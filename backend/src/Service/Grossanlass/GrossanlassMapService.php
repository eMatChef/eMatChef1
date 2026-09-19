<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassMap;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\User;
use App\Service\Media\MediaStorageService;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class GrossanlassMapService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassPlaceService $places,
        private MediaStorageService $mediaStorage,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Department $department, User $user): array
    {
        $this->assertCanSee($department, $user);

        return array_map(
            fn (DepartmentGrossanlassMap $row) => $this->serialize($department, $row),
            $this->rows($department),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(Department $department, User $user, array $data): array
    {
        $this->assertCanEdit($department, $user);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = 'Gelände';
        }
        $row = new DepartmentGrossanlassMap();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::MAP,
            DepartmentGrossanlassMap::class,
        ));
        $row->setDepartment($department);
        $row->setName($name);
        $this->entityManager->persist($row);
        $this->applyBounds($row, $data);
        $this->entityManager->flush();

        return $this->serialize($department, $row);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(Department $department, User $user, string $mapId, array $data): array
    {
        $this->assertCanEdit($department, $user);
        $row = $this->requireMap($department, $mapId);
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name !== '') {
                $row->setName($name);
            }
        }
        $this->applyBounds($row, $data);
        $this->places->syncDepartmentPlacesToMap($department, $row);
        $this->entityManager->flush();

        return $this->serialize($department, $row);
    }

    /**
     * @param array<string, mixed> $bounds
     * @return array<string, mixed>
     */
    public function uploadBackground(
        Department $department,
        User $user,
        string $mapId,
        UploadedFile $file,
        array $bounds = [],
    ): array {
        $this->assertCanEdit($department, $user);
        $row = $this->requireMap($department, $mapId);
        $stored = $this->mediaStorage->store(
            MediaStorageService::CONTEXT_GROSSANLASS_MAP,
            $row->getId(),
            $department->getId(),
            $user,
            $file,
            [],
        );
        $row->setImageFilename((string) $stored['filename']);
        $row->setImageWidth((int) ($stored['width'] ?? 0));
        $row->setImageHeight((int) ($stored['height'] ?? 0));
        $this->applyBounds($row, $bounds);
        $this->places->syncDepartmentPlacesToMap($department, $row);
        $this->entityManager->flush();

        return $this->serialize($department, $row);
    }

    public function requireMap(Department $department, string $mapId): DepartmentGrossanlassMap
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassMap::class)->find($mapId);
        if (!$row instanceof DepartmentGrossanlassMap || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Karte nicht gefunden');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Department $department, DepartmentGrossanlassMap $row): array
    {
        $filename = $row->getImageFilename();
        $imageUrl = null;
        if ($filename) {
            $imageUrl = $this->mediaStorage->buildPublicMediaUrl(
                MediaStorageService::CONTEXT_GROSSANLASS_MAP,
                $department->getId(),
                $row->getId(),
                $filename,
            );
        }

        $pins = [];
        foreach ($this->places->placesOnMap($department, $row->getId()) as $place) {
            $pins[] = $place;
        }

        return [
            'id' => $row->getId(),
            'name' => $row->getName(),
            'image_url' => $imageUrl,
            'image_width' => $row->getImageWidth(),
            'image_height' => $row->getImageHeight(),
            'bounds_north' => $row->getBoundsNorth(),
            'bounds_south' => $row->getBoundsSouth(),
            'bounds_east' => $row->getBoundsEast(),
            'bounds_west' => $row->getBoundsWest(),
            'places' => $pins,
        ];
    }

    private function assertCanSee(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        $this->places->assertCanSeePlaces($user, $department);
    }

    private function assertCanEdit(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canSeeAnlassOverview($user, $department)
            && !$this->access->canSubmitEinsatz($user, $department)
        ) {
            throw new \RuntimeException('Keine Berechtigung für die GA-Karte');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyBounds(DepartmentGrossanlassMap $row, array $data): void
    {
        $hasAny = array_key_exists('bounds_north', $data)
            || array_key_exists('bounds_south', $data)
            || array_key_exists('bounds_east', $data)
            || array_key_exists('bounds_west', $data)
            || array_key_exists('north', $data)
            || array_key_exists('south', $data)
            || array_key_exists('east', $data)
            || array_key_exists('west', $data);
        if (!$hasAny) {
            return;
        }
        $north = $data['bounds_north'] ?? $data['north'] ?? null;
        $south = $data['bounds_south'] ?? $data['south'] ?? null;
        $east = $data['bounds_east'] ?? $data['east'] ?? null;
        $west = $data['bounds_west'] ?? $data['west'] ?? null;
        if ($north === null && $south === null && $east === null && $west === null) {
            $row->setBounds(null, null, null, null);
            return;
        }
        if (!GrossanlassPlaceCodes::validBounds($north, $south, $east, $west)) {
            throw new \InvalidArgumentException('Kartenausschnitt ungültig');
        }
        $row->setBounds((float) $north, (float) $south, (float) $east, (float) $west);
    }

    /**
     * @return list<DepartmentGrossanlassMap>
     */
    private function rows(Department $department): array
    {
        return $this->entityManager->getRepository(DepartmentGrossanlassMap::class)
            ->findBy(['departmentId' => $department->getId()], ['name' => 'ASC']);
    }
}
