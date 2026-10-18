<?php

declare(strict_types=1);

namespace App\Service\Display;

use App\Entity\Department;
use App\Entity\DepartmentDisplayScreen;
use App\Entity\DepartmentGrossanlassMap;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\DepartmentGrossanlassPlaceGroup;
use App\Entity\Group;
use App\Service\Media\MediaStorageService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Kartendaten für das Infoscreen-Widget «ga_site_map»: dieselben Orte (Punkte, Bereiche/Polygone) und
 * Geländepläne wie die Karte in der GA-Planung, nur auf Anzeige reduziert (kein QR, keine Codes, keine Rechte-Flags).
 *
 * Das Department kommt ausschliesslich vom Screen (Gerät bzw. Verwalter-Vorschau), nie aus dem Request.
 */
final class DisplayMapDataService
{
    public const DEVICE_IMAGE_PATH = '/api/public/display-device/map-image/';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @param bool $forDevice true = Plan-Bilder über die Geräte-Route ausliefern (Gerät hat keine User-Session)
     *
     * @return array<string, mixed>
     */
    public function forScreen(DepartmentDisplayScreen $screen, bool $forDevice): array
    {
        $departmentId = $screen->getDepartmentId();
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department instanceof Department || !$department->isGrossanlass()) {
            return ['state' => 'unavailable', 'plan' => null, 'places' => []];
        }

        return [
            'state' => 'ready',
            'plan' => $this->primaryPlan($department, $forDevice),
            'places' => $this->places($department),
        ];
    }

    /** Plan-Datei für die Geräte-Route; null, wenn der Plan nicht zum Department des Screens gehört. */
    public function mapImagePath(DepartmentDisplayScreen $screen, string $mapId): ?string
    {
        $map = $this->entityManager->getRepository(DepartmentGrossanlassMap::class)->find($mapId);
        if (!$map instanceof DepartmentGrossanlassMap
            || $map->getDepartmentId() !== $screen->getDepartmentId()
            || !$map->getImageFilename()
            || !$map->hasBounds()
        ) {
            return null;
        }
        try {
            return $this->mediaStorage->resolveStoredFilePath(
                MediaStorageService::CONTEXT_GROSSANLASS_MAP,
                $map->getDepartmentId(),
                $map->getId(),
                $map->getImageFilename(),
            );
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Geländeplan wie in der GA-Planung: der Plan mit Bildern und Bounds, der alphabetisch zuerst kommt
     * (GrossanlassPlaceService::primaryMap).
     *
     * @return array<string, mixed>|null
     */
    private function primaryPlan(Department $department, bool $forDevice): ?array
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassMap::class)
            ->findBy(['departmentId' => $department->getId()], ['name' => 'ASC']);
        foreach ($rows as $map) {
            $filename = $map->getImageFilename();
            if (!$filename || !$map->hasBounds()) {
                continue;
            }
            $url = $forDevice
                ? self::DEVICE_IMAGE_PATH . rawurlencode($map->getId()) . '?v=' . $map->getUpdatedAt()->getTimestamp()
                : $this->mediaStorage->buildPublicMediaUrl(
                    MediaStorageService::CONTEXT_GROSSANLASS_MAP,
                    $department->getId(),
                    $map->getId(),
                    $filename,
                );

            return [
                'id' => $map->getId(),
                'name' => $map->getName(),
                'image_url' => $url,
                'bounds_north' => $map->getBoundsNorth(),
                'bounds_south' => $map->getBoundsSouth(),
                'bounds_east' => $map->getBoundsEast(),
                'bounds_west' => $map->getBoundsWest(),
                'overlay_opacity' => $map->getOverlayOpacity(),
            ];
        }

        return null;
    }

    /**
     * `group_ids` enthält die zugeordneten Gruppen samt ihren Vorgängern, damit das Widget nach einem Bauprojekt
     * oder Bereich filtern kann, ohne die Gruppenhierarchie nachzubauen.
     *
     * @return list<array<string, mixed>>
     */
    private function places(Department $department): array
    {
        $places = $this->entityManager->getRepository(DepartmentGrossanlassPlace::class)
            ->findBy(['departmentId' => $department->getId()], ['name' => 'ASC']);
        $places = array_values(array_filter($places, static fn (DepartmentGrossanlassPlace $p) => self::hasGeometry($p)));
        if ($places === []) {
            return [];
        }

        $parents = [];
        foreach ($this->entityManager->getRepository(Group::class)->findBy(['departmentId' => $department->getId()]) as $group) {
            $parents[$group->getId()] = $group->getParentId();
        }
        $links = $this->entityManager->getRepository(DepartmentGrossanlassPlaceGroup::class)
            ->createQueryBuilder('l')
            ->where('IDENTITY(l.place) IN (:ids)')
            ->setParameter('ids', array_map(static fn (DepartmentGrossanlassPlace $p) => $p->getId(), $places))
            ->getQuery()
            ->getResult();
        $groupIds = [];
        foreach ($links as $link) {
            $gid = $link->getGroup()->getId();
            for ($i = 0; $gid !== null && $i < 20; ++$i) {
                $groupIds[$link->getPlace()->getId()][$gid] = true;
                $gid = $parents[$gid] ?? null;
            }
        }

        $out = [];
        foreach ($places as $place) {
            $out[] = [
                'id' => $place->getId(),
                'name' => $place->getName(),
                'kind' => $place->getKind(),
                'latitude' => $place->getLatitude(),
                'longitude' => $place->getLongitude(),
                'polygon' => $place->getPolygon(),
                'starred' => $place->isStarred(),
                'group_ids' => array_keys($groupIds[$place->getId()] ?? []),
            ];
        }

        return $out;
    }

    private static function hasGeometry(DepartmentGrossanlassPlace $place): bool
    {
        if (\is_array($place->getPolygon()) && \count($place->getPolygon()) >= 3) {
            return true;
        }

        return $place->getLatitude() !== null && $place->getLongitude() !== null;
    }
}
