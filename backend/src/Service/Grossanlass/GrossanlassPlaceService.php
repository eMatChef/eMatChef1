<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassMap;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\DepartmentGrossanlassUnterlager;
use App\Entity\Group;
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
        $groupId = isset($data['group_id']) ? trim((string) $data['group_id']) : '';
        $row = $this->makePlace($department, $name);
        $row->setGroupId($groupId !== '' ? $groupId : null);
        $row->setKind($this->inferKind($department, $data, null));
        $this->applyStarred($department, $row, $data, true);
        $this->applyMapPosition($department, $row, $data);
        $this->applyGeoPosition($department, $row, $data);
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(Department $department, User $user, string $placeId, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canSeeAnlassOverview($user, $department)
            && !$this->access->canSubmitEinsatz($user, $department)
        ) {
            throw new \RuntimeException('Keine Berechtigung für Orte');
        }
        $row = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->find($placeId);
        if (!$row instanceof DepartmentGrossanlassPlace || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Ort nicht gefunden');
        }
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new \InvalidArgumentException('Name ist erforderlich');
            }
            $row->setName($name);
        }
        if (array_key_exists('kind', $data)) {
            $row->setKind($this->inferKind($department, $data, $row->getUnterlagerId()));
        }
        if (array_key_exists('group_id', $data)) {
            $groupId = trim((string) $data['group_id']);
            $row->setGroupId($groupId !== '' ? $groupId : null);
        }
        $this->applyStarred($department, $row, $data, false);
        $this->applyMapPosition($department, $row, $data);
        $this->applyGeoPosition($department, $row, $data);
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function placesOnMap(Department $department, string $mapId): array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->findBy([
            'departmentId' => $department->getId(),
            'mapId' => $mapId,
        ]);
        $out = [];
        foreach ($rows as $row) {
            if (!$row instanceof DepartmentGrossanlassPlace) {
                continue;
            }
            if ($row->getMapX() === null || $row->getMapY() === null) {
                continue;
            }
            $out[] = $this->serialize($row);
        }

        return $out;
    }

    public function assertCanSeePlaces(User $user, Department $department): void
    {
        if ($this->access->canSubmitEinsatz($user, $department)
            || $this->access->canSeeAnlassOverview($user, $department)
            || $this->access->canOperateAusgabe($user, $department)
        ) {
            return;
        }
        $this->assertMemberCanSeePlaces($user, $department);
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

        return GrossanlassPlaceCodes::qrUrl($base, $code);
    }

    /**
     * @return list<DepartmentGrossanlassPlace>
     */
    public function rows(Department $department): array
    {
        return $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)
            ->findBy(['departmentId' => $department->getId()], ['name' => 'ASC']);
    }

    public function findForGroup(Department $department, string $groupId): ?DepartmentGrossanlassPlace
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->findOneBy([
            'departmentId' => $department->getId(),
            'groupId' => $groupId,
        ]);

        return $row instanceof DepartmentGrossanlassPlace ? $row : null;
    }

    public function getPlace(Department $department, string $placeId): DepartmentGrossanlassPlace
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)->find($placeId);
        if (!$row instanceof DepartmentGrossanlassPlace || $row->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Ort nicht gefunden');
        }

        return $row;
    }

    public function ensureForBauprojekt(Department $department, Group $group): DepartmentGrossanlassPlace
    {
        $existing = $this->findForGroup($department, $group->getId());
        if ($existing instanceof DepartmentGrossanlassPlace) {
            if ($existing->getKind() !== GrossanlassPlaceCodes::KIND_BAUPROJEKT) {
                $existing->setKind(GrossanlassPlaceCodes::KIND_BAUPROJEKT);
                $this->entityManager->flush();
            }

            return $existing;
        }
        $row = $this->makePlace($department, $group->getName());
        $row->setGroupId($group->getId());
        $row->setKind(GrossanlassPlaceCodes::KIND_BAUPROJEKT);
        $this->entityManager->flush();

        return $row;
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
            $place->setKind(GrossanlassPlaceCodes::KIND_UNTERLAGER);
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
        $row->setKind(GrossanlassPlaceCodes::KIND_POI);
        $row->setPublicCode(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::PLACE_PUBLIC,
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
            'kind' => $row->getKind(),
            'map_id' => $row->getMapId(),
            'map_x' => $row->getMapX(),
            'map_y' => $row->getMapY(),
            'latitude' => $row->getLatitude(),
            'longitude' => $row->getLongitude(),
            'starred' => $row->isStarred(),
            'public_code' => $row->getPublicCode(),
            'qr_url' => $this->qrUrl($row->getPublicCode()),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function inferKind(Department $department, array $data, ?string $unterlagerId): string
    {
        $groupId = trim((string) ($data['group_id'] ?? ''));
        $groupIsBauprojekt = false;
        if ($groupId !== '') {
            $group = $this->entityManager->getRepository(Group::class)->find($groupId);
            $groupIsBauprojekt = $group instanceof Group
                && $group->getDepartmentId() === $department->getId()
                && $group->getGrossanlassKind() === Group::GROSSANLASS_KIND_TEILBEREICH;
        }

        return GrossanlassPlaceCodes::inferKind(
            isset($data['kind']) ? (string) $data['kind'] : null,
            $unterlagerId,
            $groupIsBauprojekt,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyStarred(
        Department $department,
        DepartmentGrossanlassPlace $row,
        array $data,
        bool $creating,
    ): void {
        if (!$creating && !array_key_exists('starred', $data)) {
            return;
        }
        $starred = filter_var($data['starred'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $row->setStarred($starred);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyMapPosition(Department $department, DepartmentGrossanlassPlace $row, array $data): void
    {
        if (array_key_exists('map_id', $data)) {
            $mapId = trim((string) ($data['map_id'] ?? ''));
            if ($mapId === '') {
                $row->setMap(null);
            } else {
                $map = $this->entityManager->getRepository(DepartmentGrossanlassMap::class)->find($mapId);
                if (!$map instanceof DepartmentGrossanlassMap || $map->getDepartmentId() !== $department->getId()) {
                    throw new \InvalidArgumentException('Karte nicht gefunden');
                }
                $row->setMap($map);
            }
        }
        if (array_key_exists('map_x', $data) || array_key_exists('map_y', $data)) {
            $row->setMapX(GrossanlassPlaceCodes::clampAxis($data['map_x'] ?? $row->getMapX()));
            $row->setMapY(GrossanlassPlaceCodes::clampAxis($data['map_y'] ?? $row->getMapY()));
        }
        $this->fillMissingGeoFromMap($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyGeoPosition(Department $department, DepartmentGrossanlassPlace $row, array $data): void
    {
        $touched = array_key_exists('latitude', $data) || array_key_exists('longitude', $data);
        if ($touched) {
            $row->setLatitude(GrossanlassPlaceCodes::optionalLatitude($data['latitude'] ?? $row->getLatitude()));
            $row->setLongitude(GrossanlassPlaceCodes::optionalLongitude($data['longitude'] ?? $row->getLongitude()));
        }
        $this->syncPlaceToPrimaryMap($department, $row);
        if (!$touched) {
            $this->fillMissingGeoFromMap($row);
        }
    }

    public function syncDepartmentPlacesToMap(Department $department, DepartmentGrossanlassMap $map): void
    {
        foreach ($this->rows($department) as $row) {
            $this->syncPlaceToMap($row, $map);
        }
    }

    private function syncPlaceToPrimaryMap(Department $department, DepartmentGrossanlassPlace $row): void
    {
        $map = $row->getMap();
        if (!$map instanceof DepartmentGrossanlassMap || !$map->hasBounds()) {
            $map = $this->primaryMap($department);
        }
        if (!$map instanceof DepartmentGrossanlassMap) {
            return;
        }
        $this->syncPlaceToMap($row, $map);
    }

    private function syncPlaceToMap(DepartmentGrossanlassPlace $row, DepartmentGrossanlassMap $map): void
    {
        if (!$map->hasBounds()) {
            $this->fillMissingGeoFromMap($row);
            return;
        }
        $onThisMap = $row->getMapId() === $map->getId();
        $point = GrossanlassPlaceCodes::mapPointFromLatLng(
            $row->getLatitude(),
            $row->getLongitude(),
            $map->getBoundsNorth(),
            $map->getBoundsSouth(),
            $map->getBoundsEast(),
            $map->getBoundsWest(),
        );
        if ($point !== null) {
            $row->setMap($map);
            $row->setMapX($point['x']);
            $row->setMapY($point['y']);
            return;
        }
        if ($onThisMap) {
            $this->fillMissingGeoFromMap($row);
        }
    }

    private function fillMissingGeoFromMap(DepartmentGrossanlassPlace $row): void
    {
        if ($row->getLatitude() !== null && $row->getLongitude() !== null) {
            return;
        }
        $map = $row->getMap();
        if (!$map instanceof DepartmentGrossanlassMap || !$map->hasBounds()) {
            return;
        }
        $geo = GrossanlassPlaceCodes::latLngFromMapPoint(
            $row->getMapX(),
            $row->getMapY(),
            $map->getBoundsNorth(),
            $map->getBoundsSouth(),
            $map->getBoundsEast(),
            $map->getBoundsWest(),
        );
        if ($geo === null) {
            return;
        }
        $row->setLatitude($geo['lat']);
        $row->setLongitude($geo['lng']);
    }

    private function primaryMap(Department $department): ?DepartmentGrossanlassMap
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassMap::class)->findOneBy(
            ['departmentId' => $department->getId()],
            ['name' => 'ASC'],
        );

        return $row instanceof DepartmentGrossanlassMap ? $row : null;
    }

    private function assertMemberCanSeePlaces(User $user, Department $department): void
    {
        $role = $this->access->membershipRole($user, $department);
        if ($role === null) {
            throw new \RuntimeException('Keine Berechtigung für Orte');
        }
    }
}
