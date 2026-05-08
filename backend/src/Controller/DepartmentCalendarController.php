<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Entity\Membership;
use App\Entity\User;
use App\Service\Calendar\FcalApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/calendar-markers', name: 'api_department_calendar_')]
class DepartmentCalendarController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FcalApiService $fcalApi,
    ) {
    }

    /**
     * Schulferien-Marker (fcal, class=0) für die konfigurierte Geo-ID.
     * Query: years=2024,2025,2026 (optional; Standard: aktuelles Jahr −1 bis +2).
     */
    #[Route('', name: 'markers', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function markers(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership) {
            return new JsonResponse(['error' => 'Kein Zugriff'], 403);
        }

        $geoIdRaw = $this->readSetting($departmentId, 'calendar.fcal_geo_id', DepartmentSetting::getCalendarDefaults()['calendar.fcal_geo_id']);
        $geoId = (int) preg_replace('/\D/', '', (string) $geoIdRaw);
        if ($geoId < 1) {
            return new JsonResponse([
                'markers' => [],
                'location' => null,
                'source' => 'none',
                'message' => 'calendar.fcal_geo_id nicht gesetzt',
            ]);
        }

        $years = $this->parseYearsParam($request->query->get('years'));
        $allMarkers = [];
        $location = null;
        foreach ($years as $year) {
            $pack = $this->fcalApi->fetchSchoolHolidayMarkers($geoId, $year);
            foreach ($pack['markers'] as $m) {
                $allMarkers[] = $m;
            }
            if ($location === null && isset($pack['location']) && \is_string($pack['location'])) {
                $location = $pack['location'];
            }
        }

        return new JsonResponse([
            'markers' => $allMarkers,
            'location' => $location,
            'source' => 'fcal',
            'geoId' => $geoId,
        ]);
    }

    private function readSetting(string $departmentId, string $key, string $default): string
    {
        $row = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $departmentId,
            'settingKey' => $key,
        ]);

        return $row ? $row->getSettingValue() : $default;
    }

    /**
     * @return list<int>
     */
    private function parseYearsParam(?string $raw): array
    {
        $y = (int) (new \DateTimeImmutable('now'))->format('Y');
        $default = range($y - 1, $y + 2);
        if ($raw === null || trim($raw) === '') {
            return $default;
        }
        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $n = (int) $p;
            if ($n >= 2000 && $n <= 2050) {
                $out[] = $n;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out !== [] ? $out : $default;
    }
}
