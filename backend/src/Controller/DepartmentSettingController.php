<?php

namespace App\Controller;

use App\Entity\DepartmentSetting;
use App\Entity\Department;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/settings', name: 'api_department_settings_')]
class DepartmentSettingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Alle Settings eines Departments laden (mit Defaults für fehlende Keys)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId): JsonResponse
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        // Gespeicherte Settings laden
        $settings = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findBy(['departmentId' => $departmentId]);

        // In Key/Value-Map konvertieren
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->getSettingKey()] = $setting->getSettingValue();
        }

        // Defaults für fehlende Settings einfügen
        $allDefaults = array_merge(
            DepartmentSetting::getGeneralDefaults(),
            DepartmentSetting::getActivityDefaults(),
            DepartmentSetting::getRentalAmortizationDefaults(),
            DepartmentSetting::getCalendarDefaults()
        );
        foreach ($allDefaults as $key => $defaultValue) {
            if (!isset($result[$key])) {
                $result[$key] = $defaultValue;
            }
        }

        return new JsonResponse($result);
    }

    /**
     * Bestimmte Settings nach Prefix laden (z.B. "activity")
     */
    #[Route('/group/{prefix}', name: 'group', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function group(string $departmentId, string $prefix): JsonResponse
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        // Settings mit Prefix laden
        $settings = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(DepartmentSetting::class, 's')
            ->where('s.departmentId = :deptId')
            ->andWhere('s.settingKey LIKE :prefix')
            ->setParameter('deptId', $departmentId)
            ->setParameter('prefix', $prefix . '.%')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->getSettingKey()] = $setting->getSettingValue();
        }

        // Defaults einfügen
        $allDefaults = array_merge(
            DepartmentSetting::getGeneralDefaults(),
            DepartmentSetting::getActivityDefaults(),
            DepartmentSetting::getRentalAmortizationDefaults(),
            DepartmentSetting::getCalendarDefaults()
        );
        foreach ($allDefaults as $key => $defaultValue) {
            if (str_starts_with($key, $prefix . '.') && !isset($result[$key])) {
                $result[$key] = $defaultValue;
            }
        }

        return new JsonResponse($result);
    }

    /**
     * Settings speichern (Batch-Update mehrerer Keys)
     * Body: { "activity.default_time_start": "14:00", "activity.material_lead_minutes": "60", ... }
     */
    #[Route('', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || empty($data)) {
            return new JsonResponse(['error' => 'Keine Settings übergeben'], 400);
        }

        // Erlaubte Setting-Keys validieren
        $allowedPrefixes = ['activity.', 'material.', 'general.', 'onboarding.', 'rental.', 'calendar.'];
        $validData = [];
        foreach ($data as $key => $value) {
            $isAllowed = false;
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $isAllowed = true;
                    break;
                }
            }
            if ($isAllowed) {
                $validData[$key] = (string) $value;
            }
        }

        if (empty($validData)) {
            return new JsonResponse(['error' => 'Keine gültigen Settings'], 400);
        }

        // Bestehende Settings für dieses Department laden
        $existing = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findBy(['departmentId' => $departmentId]);

        $existingMap = [];
        foreach ($existing as $setting) {
            $existingMap[$setting->getSettingKey()] = $setting;
        }

        // Upsert: bestehende aktualisieren oder neue erstellen
        foreach ($validData as $key => $value) {
            if (isset($existingMap[$key])) {
                // Update
                $existingMap[$key]->setSettingValue($value);
                $existingMap[$key]->setUpdatedAt(new \DateTime());
            } else {
                // Create
                $setting = new DepartmentSetting();
                $setting->setId(IdGenerator::generate());
                $setting->setDepartment($department);
                $setting->setSettingKey($key);
                $setting->setSettingValue($value);
                $this->entityManager->persist($setting);
            }
        }

        $this->entityManager->flush();

        // Alle aktuellen Settings zurückgeben
        $allSettings = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findBy(['departmentId' => $departmentId]);

        $result = [];
        foreach ($allSettings as $s) {
            $result[$s->getSettingKey()] = $s->getSettingValue();
        }

        // Defaults ergänzen
        $allDefaults = array_merge(
            DepartmentSetting::getGeneralDefaults(),
            DepartmentSetting::getActivityDefaults(),
            DepartmentSetting::getRentalAmortizationDefaults(),
            DepartmentSetting::getCalendarDefaults()
        );
        foreach ($allDefaults as $k => $v) {
            if (!isset($result[$k])) {
                $result[$k] = $v;
            }
        }

        return new JsonResponse($result);
    }
}
