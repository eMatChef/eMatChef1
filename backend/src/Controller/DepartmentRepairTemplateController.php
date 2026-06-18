<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Department;
use App\Entity\DepartmentRepairTemplate;
use App\Entity\Membership;
use App\Entity\RepairTemplate;
use App\Entity\User;
use App\Repository\DepartmentRepairTemplateRepository;
use App\Repository\RepairTemplateRepository;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/repair-templates', name: 'api_department_repair_templates_')]
class DepartmentRepairTemplateController extends AbstractController
{
    private const MANAGER_ROLES = ['mw', 'matwart', 'dc', 'depchef'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DepartmentRepairTemplateRepository $departmentRepairTemplateRepository,
        private readonly RepairTemplateRepository $repairTemplateRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId): JsonResponse
    {
        $memberCheck = $this->requireMember($departmentId);
        if ($memberCheck instanceof JsonResponse) {
            return $memberCheck;
        }

        $templates = $this->departmentRepairTemplateRepository->findByDepartmentId($departmentId);
        $result = [];

        foreach ($templates as $departmentTemplate) {
            $platform = $this->repairTemplateRepository->findOneByTemplateKey($departmentTemplate->getTemplateKey());
            if (!$platform instanceof RepairTemplate || !$platform->isActive()) {
                continue;
            }
            $result[] = $this->serializeMerged($departmentTemplate, $platform, true);
        }

        return new JsonResponse($result);
    }

    #[Route('/import', name: 'import', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function import(string $departmentId, Request $request): JsonResponse
    {
        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department instanceof Department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $templateKey = strtolower(trim((string) ($data['template_key'] ?? '')));
        if ($templateKey === '') {
            return new JsonResponse(['error' => 'template_key ist erforderlich'], 400);
        }

        $platform = $this->repairTemplateRepository->findOneByTemplateKey($templateKey);
        if (!$platform instanceof RepairTemplate || !$platform->isActive()) {
            return new JsonResponse(['error' => 'Plattform-Vorlage nicht gefunden'], 404);
        }

        $existing = $this->departmentRepairTemplateRepository->findOneByDepartmentAndKey($departmentId, $templateKey);
        if ($existing instanceof DepartmentRepairTemplate) {
            return new JsonResponse($this->serializeMerged($existing, $platform, true));
        }

        try {
            $departmentTemplate = new DepartmentRepairTemplate();
            $departmentTemplate->setId(IdGenerator::generateUnique($this->entityManager, DepartmentRepairTemplate::class));
            $departmentTemplate->setDepartment($department);
            $departmentTemplate->setTemplateKey($templateKey);
            $departmentTemplate->setPricesJson($this->buildDefaultPricesJson($platform));

            $this->entityManager->persist($departmentTemplate);
            $this->entityManager->flush();

            return new JsonResponse($this->serializeMerged($departmentTemplate, $platform, true), 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Import fehlgeschlagen: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{templateKey}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $templateKey, Request $request): JsonResponse
    {
        $user = $this->requireManager($departmentId);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $templateKey = strtolower(trim($templateKey));
        $departmentTemplate = $this->departmentRepairTemplateRepository->findOneByDepartmentAndKey($departmentId, $templateKey);
        if (!$departmentTemplate instanceof DepartmentRepairTemplate) {
            return new JsonResponse(['error' => 'Department-Vorlage nicht gefunden'], 404);
        }

        $platform = $this->repairTemplateRepository->findOneByTemplateKey($templateKey);
        if (!$platform instanceof RepairTemplate) {
            return new JsonResponse(['error' => 'Plattform-Vorlage nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('prices_json', $data)) {
            if (!is_array($data['prices_json'])) {
                return new JsonResponse(['error' => 'prices_json muss ein Objekt sein'], 400);
            }
            $departmentTemplate->setPricesJson($data['prices_json']);
        }

        if (array_key_exists('flat_rate_chf', $data)) {
            $flat = $data['flat_rate_chf'];
            $departmentTemplate->setFlatRateChf($flat !== null && $flat !== '' ? (string) $flat : null);
        }

        if (array_key_exists('is_active', $data)) {
            $departmentTemplate->setIsActive((bool) $data['is_active']);
        }

        $departmentTemplate->updateTimestamps();
        $this->entityManager->flush();

        return new JsonResponse($this->serializeMerged($departmentTemplate, $platform, true));
    }

    private function buildDefaultPricesJson(RepairTemplate $platform): array
    {
        $prices = [];
        $structure = $platform->getStructureJson();
        $sections = $structure['sections'] ?? [];

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            foreach ($section['items'] ?? [] as $item) {
                if (!is_array($item) || empty($item['key'])) {
                    continue;
                }
                $key = (string) $item['key'];
                $prices[$key] = [
                    'unit_price_chf' => null,
                    'is_active' => true,
                ];
            }
        }

        return $prices;
    }

    private function serializeMerged(
        DepartmentRepairTemplate $departmentTemplate,
        RepairTemplate $platform,
        bool $detailed,
    ): array {
        $result = [
            'id' => $departmentTemplate->getId(),
            'department_id' => $departmentTemplate->getDepartmentId(),
            'template_key' => $departmentTemplate->getTemplateKey(),
            'name' => $platform->getName(),
            'material_class' => $platform->getMaterialClass(),
            'flat_rate_chf' => $departmentTemplate->getFlatRateChf(),
            'is_active' => $departmentTemplate->isActive(),
            'prices_json' => $departmentTemplate->getPricesJson(),
            'created_at' => $departmentTemplate->getCreatedAt()->format('c'),
            'updated_at' => $departmentTemplate->getUpdatedAt()->format('c'),
        ];

        if ($detailed) {
            $result['structure_json'] = $platform->getStructureJson();
            $result['diagram_json'] = $platform->getDiagramJson();
        }

        return $result;
    }

    private function requireMember(string $departmentId): true|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        if ($this->isGlobalAdmin($user)) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);

        if (!$membership) {
            return new JsonResponse(['error' => 'Kein Zugriff auf dieses Department'], 403);
        }

        return true;
    }

    private function requireManager(string $departmentId): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 401);
        }

        if ($this->isGlobalAdmin($user)) {
            return $user;
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);

        if (!$membership || !in_array($membership->getRole(), self::MANAGER_ROLES, true)) {
            return new JsonResponse(['error' => 'Nur Materialwart oder Department-Leitung'], 403);
        }

        return $user;
    }

    private function isGlobalAdmin(User $user): bool
    {
        return count(array_intersect(
            ['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'],
            $user->getRoles()
        )) > 0;
    }
}
