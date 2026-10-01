<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassEinsatz;
use App\Entity\DepartmentGrossanlassMap;
use App\Entity\DepartmentGrossanlassPack;
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
        $kind = $this->inferKind($department, $data, null);
        $this->assertWritableKind($kind, null);
        $row->setKind($kind);
        $this->applyStarred($department, $row, $data, true);
        $this->applyMapPosition($department, $row, $data);
        $this->applyGeoPosition($department, $row, $data);
        $this->applyPolygon($department, $row, $data);
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
        if (array_key_exists('kind', $data) && $this->canChangeKind($row)) {
            $kind = $this->inferKind($department, $data, $row->getUnterlagerId());
            $this->assertWritableKind($kind, $row->getKind());
            $row->setKind($kind);
        }
        if (array_key_exists('group_id', $data)) {
            $groupId = trim((string) $data['group_id']);
            $row->setGroupId($groupId !== '' ? $groupId : null);
        }
        $this->applyStarred($department, $row, $data, false);
        $this->applyMapPosition($department, $row, $data);
        $this->applyGeoPosition($department, $row, $data);
        $this->applyPolygon($department, $row, $data);
        $this->entityManager->flush();

        return $this->serialize($row);
    }

    public function delete(Department $department, User $user, string $placeId): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canSeeAnlassOverview($user, $department)
            && !$this->access->canSubmitEinsatz($user, $department)
        ) {
            throw new \RuntimeException('Keine Berechtigung für Orte');
        }
        $row = $this->getPlace($department, $placeId);
        $blockers = $this->deleteBlockers($department, $row);
        if ($blockers !== []) {
            throw new \RuntimeException('Löschen nicht möglich: ' . implode(' ', $blockers));
        }
        $this->entityManager->remove($row);
        $this->entityManager->flush();
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
                $existing->setPolygon(null);
            }
            if ($existing->getName() !== $group->getName()) {
                $existing->setName($group->getName());
            }
            $this->entityManager->flush();

            return $existing;
        }
        $row = $this->makePlace($department, $group->getName());
        $row->setGroupId($group->getId());
        $row->setKind(GrossanlassPlaceCodes::KIND_BAUPROJEKT);
        $this->entityManager->flush();

        return $row;
    }

    public function ensureForArea(Department $department, Group $group): DepartmentGrossanlassPlace
    {
        $existing = $this->findForGroup($department, $group->getId());
        if ($existing instanceof DepartmentGrossanlassPlace) {
            if ($existing->getKind() !== GrossanlassPlaceCodes::KIND_AREA) {
                $existing->setKind(GrossanlassPlaceCodes::KIND_AREA);
            }
            if ($existing->getName() !== $group->getName()) {
                $existing->setName($group->getName());
            }
            $this->entityManager->flush();

            return $existing;
        }
        $row = $this->makePlace($department, $group->getName());
        $row->setGroupId($group->getId());
        $row->setKind(GrossanlassPlaceCodes::KIND_AREA);
        $this->entityManager->flush();

        return $row;
    }

    public function removeAreaForGroup(Department $department, Group $group): void
    {
        $this->detachOrgPlaceForGroup($department, $group, GrossanlassPlaceCodes::KIND_AREA);
    }

    public function detachOrgPlaceForGroup(Department $department, Group $group, ?string $onlyKind = null): void
    {
        $row = $this->findForGroup($department, $group->getId());
        if (!$row instanceof DepartmentGrossanlassPlace) {
            return;
        }
        if ($onlyKind !== null && $row->getKind() !== $onlyKind) {
            return;
        }
        $row->setGroupId(null);
        if ($this->deleteBlockers($department, $row) === []) {
            $this->entityManager->remove($row);
        }
        $this->entityManager->flush();
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
        $department = $row->getDepartment();

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
            'polygon' => $row->getPolygon(),
            'starred' => $row->isStarred(),
            'public_code' => $row->getPublicCode(),
            'qr_url' => $this->qrUrl($row->getPublicCode()),
            'can_delete' => $this->canDelete($department, $row),
            'can_change_kind' => $this->canChangeKind($row),
        ];
    }

    public function canDelete(Department $department, DepartmentGrossanlassPlace $row): bool
    {
        return $this->deleteBlockers($department, $row) === [];
    }

    public function canChangeKind(DepartmentGrossanlassPlace $row): bool
    {
        return $row->getGroupId() === null && $row->getUnterlagerId() === null;
    }

    /**
     * @return list<string>
     */
    private function deleteBlockers(Department $department, DepartmentGrossanlassPlace $row): array
    {
        $blockers = [];
        if (
            $row->getGroupId() !== null
            && $row->getGroupId() !== ''
            && $row->getKind() === GrossanlassPlaceCodes::KIND_BAUPROJEKT
        ) {
            $blockers[] = 'Mit Bauprojekt verknüpft.';
        }
        if ($row->getUnterlagerId() !== null && $row->getUnterlagerId() !== '') {
            $blockers[] = 'Unterlager-Ort.';
        }
        if ($this->countEinsatzDestinations($department, $row->getId()) > 0) {
            $blockers[] = 'Noch als Einsatz-Ziel gesetzt.';
        }
        if ($this->countPackLocations($department, $row->getId()) > 0) {
            $blockers[] = 'Noch als Pack-Standort gesetzt.';
        }

        return $blockers;
    }

    private function countEinsatzDestinations(Department $department, string $placeId): int
    {
        return (int) $this->entityManager->getRepository(DepartmentGrossanlassEinsatz::class)
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.departmentId = :departmentId')
            ->andWhere('e.destinationPlaceId = :placeId')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('placeId', $placeId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countPackLocations(Department $department, string $placeId): int
    {
        return (int) $this->entityManager->getRepository(DepartmentGrossanlassPack::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.departmentId = :departmentId')
            ->andWhere('p.currentPlaceId = :placeId')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('placeId', $placeId)
            ->getQuery()
            ->getSingleScalarResult();
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

    private function assertWritableKind(string $kind, ?string $existing): void
    {
        if ($kind !== GrossanlassPlaceCodes::KIND_MATPLATZ) {
            return;
        }
        if ($existing === GrossanlassPlaceCodes::KIND_MATPLATZ) {
            return;
        }
        throw new \InvalidArgumentException('Matplatz ist der Lagerstandort — kein GA-Ort');
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
        if (array_key_exists('map_x', $data)) {
            $row->setMapX(GrossanlassPlaceCodes::clampAxis($data['map_x']));
        }
        if (array_key_exists('map_y', $data)) {
            $row->setMapY(GrossanlassPlaceCodes::clampAxis($data['map_y']));
        }
        $this->fillMissingGeoFromMap($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function applyPolygonData(DepartmentGrossanlassPlace $row, mixed $polygon): void
    {
        $points = GrossanlassPlaceCodes::normalizePolygon($polygon);
        $row->setPolygon($points);
        if ($points === null) {
            return;
        }
        $centroid = GrossanlassPlaceCodes::centroidFromPolygon($points);
        if ($centroid === null) {
            return;
        }
        $row->setLatitude($centroid['lat']);
        $row->setLongitude($centroid['lng']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyPolygon(Department $department, DepartmentGrossanlassPlace $row, array $data): void
    {
        if (!array_key_exists('polygon', $data)) {
            return;
        }
        $this->applyPolygonData($row, $data['polygon']);
        $this->syncPlaceToPrimaryMap($department, $row);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyGeoPosition(Department $department, DepartmentGrossanlassPlace $row, array $data): void
    {
        $touched = array_key_exists('latitude', $data) || array_key_exists('longitude', $data);
        if (array_key_exists('latitude', $data)) {
            $row->setLatitude(GrossanlassPlaceCodes::optionalLatitude($data['latitude']));
        }
        if (array_key_exists('longitude', $data)) {
            $row->setLongitude(GrossanlassPlaceCodes::optionalLongitude($data['longitude']));
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
