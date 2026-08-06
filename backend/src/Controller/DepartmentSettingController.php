<?php

namespace App\Controller;

use App\Entity\DepartmentSetting;
use App\Entity\Department;
use App\Entity\User;
use App\Service\Workshop\WorkshopDepartmentSettingsValidator;
use App\Service\Workshop\WorkshopSparePartsCategoryBootstrapService;
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
        private EntityManagerInterface $entityManager,
        private WorkshopDepartmentSettingsValidator $workshopSettingsValidator,
        private WorkshopSparePartsCategoryBootstrapService $workshopSparePartsCategoryBootstrap,
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

        return new JsonResponse($this->mergeSettingDefaults($result));
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

        $merged = $this->mergeSettingDefaults($result);

        if ($prefix === 'workshop') {
            $this->workshopSparePartsCategoryBootstrap->ensure($department);
            $merged = $this->mergeSettingDefaults($this->loadStoredSettings($departmentId));
        }

        $filtered = [];
        foreach ($merged as $key => $value) {
            if (str_starts_with($key, $prefix . '.')) {
                $filtered[$key] = $value;
            }
        }

        return new JsonResponse($filtered);
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

        $validData = $this->extractAllowedSettings($data);
        if (empty($validData)) {
            return new JsonResponse(['error' => 'Keine gültigen Settings'], 400);
        }

        unset($validData[WorkshopSparePartsCategoryBootstrapService::SETTING_KEY]);

        $workshopData = $this->workshopSettingsValidator->filterAllowed($validData);
        if ($workshopData !== []) {
            $errors = $this->workshopSettingsValidator->validate($workshopData, $departmentId);
            if ($errors !== []) {
                return new JsonResponse(['error' => $errors[0], 'errors' => $errors], 422);
            }
            $workshopData = $this->workshopSettingsValidator->normalize($workshopData);
            foreach ($workshopData as $key => $value) {
                $validData[$key] = $value;
            }
        }

        $timingKeys = [
            'accounting.settlement_timing_consumable',
            'accounting.settlement_timing_external',
        ];
        $allowedTiming = ['offer_at_activity', 'accounting_only'];
        foreach ($timingKeys as $timingKey) {
            if (!\array_key_exists($timingKey, $validData)) {
                continue;
            }
            $v = strtolower(trim((string) $validData[$timingKey]));
            if (!\in_array($v, $allowedTiming, true)) {
                return new JsonResponse([
                    'error' => $timingKey . ' muss offer_at_activity oder accounting_only sein',
                ], 422);
            }
            $validData[$timingKey] = $v;
        }

        $roleLabelKeys = ['roles.label.l1', 'roles.label.l2', 'roles.label.l3'];
        foreach ($roleLabelKeys as $roleLabelKey) {
            if (!\array_key_exists($roleLabelKey, $validData)) {
                continue;
            }
            $label = trim(preg_replace('/\s+/u', ' ', (string) $validData[$roleLabelKey]) ?? '');
            if (mb_strlen($label) > 60) {
                return new JsonResponse([
                    'error' => 'Rollenbezeichnung darf maximal 60 Zeichen lang sein',
                ], 422);
            }
            $validData[$roleLabelKey] = $label;
        }

        $this->workshopSparePartsCategoryBootstrap->ensure($department);

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

        return new JsonResponse($this->mergeSettingDefaults($result));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function extractAllowedSettings(array $data): array
    {
        $allowedPrefixes = ['activity.', 'material.', 'general.', 'onboarding.', 'rental.', 'calendar.', 'workshop.', 'accounting.', 'roles.', 'js.'];
        $validData = [];

        foreach ($data as $key => $value) {
            if (!\is_string($key)) {
                continue;
            }
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $validData[$key] = (string) $value;
                    break;
                }
            }
        }

        return $validData;
    }

    /**
     * @param array<string, string> $stored
     *
     * @return array<string, string>
     */
    /**
     * @return array<string, string>
     */
    private function loadStoredSettings(string $departmentId): array
    {
        $settings = $this->entityManager->getRepository(DepartmentSetting::class)
            ->findBy(['departmentId' => $departmentId]);

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->getSettingKey()] = $setting->getSettingValue();
        }

        return $result;
    }

    private function mergeSettingDefaults(array $stored): array
    {
        $result = $stored;
        foreach (DepartmentSetting::getAllDefaults() as $key => $defaultValue) {
            if (!isset($result[$key])) {
                $result[$key] = $defaultValue;
            }
        }

        return $result;
    }
}
