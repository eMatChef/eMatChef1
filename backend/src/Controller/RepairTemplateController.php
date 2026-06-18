<?php

namespace App\Controller;

use App\Entity\RepairTemplate;
use App\Repository\RepairTemplateRepository;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/repair-templates', name: 'api_repair_templates_')]
class RepairTemplateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RepairTemplateRepository $repairTemplateRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $includeInactive = $request->query->getBoolean('include_inactive', false);
        if ($includeInactive && !$this->canManagePlatformTemplates()) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $templates = $this->repairTemplateRepository->findAllOrdered(!$includeInactive);

        return new JsonResponse(array_map(
            fn (RepairTemplate $template) => $this->serializeTemplate($template, $includeInactive),
            $templates
        ));
    }

    #[Route('/{key}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $key): JsonResponse
    {
        $template = $this->repairTemplateRepository->findOneByTemplateKey($key);
        if (!$template instanceof RepairTemplate) {
            return new JsonResponse(['error' => 'Vorlage nicht gefunden'], 404);
        }

        if (!$template->isActive() && !$this->canManagePlatformTemplates()) {
            return new JsonResponse(['error' => 'Vorlage nicht gefunden'], 404);
        }

        return new JsonResponse($this->serializeTemplate($template, true));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $accessCheck = $this->ensurePlatformTemplateAdmin();
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $validation = $this->validateWritePayload($data, true);
        if ($validation instanceof JsonResponse) {
            return $validation;
        }

        $templateKey = strtolower(trim((string) $data['template_key']));
        if ($this->repairTemplateRepository->findOneByTemplateKey($templateKey)) {
            return new JsonResponse(['error' => 'template_key existiert bereits'], 409);
        }

        try {
            $template = new RepairTemplate();
            $template->setId(IdGenerator::generateUnique($this->entityManager, RepairTemplate::class));
            $template->setTemplateKey($templateKey);
            $this->applyWritePayload($template, $data);

            $this->entityManager->persist($template);
            $this->entityManager->flush();

            return new JsonResponse($this->serializeTemplate($template, true), 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Erstellen: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{key}', name: 'update', methods: ['PATCH', 'PUT'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $key, Request $request): JsonResponse
    {
        $accessCheck = $this->ensurePlatformTemplateAdmin();
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $template = $this->repairTemplateRepository->findOneByTemplateKey($key);
        if (!$template instanceof RepairTemplate) {
            return new JsonResponse(['error' => 'Vorlage nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $validation = $this->validateWritePayload($data, false);
        if ($validation instanceof JsonResponse) {
            return $validation;
        }

        try {
            $this->applyWritePayload($template, $data);
            $template->updateTimestamps();
            $this->entityManager->flush();

            return new JsonResponse($this->serializeTemplate($template, true));
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Fehler beim Aktualisieren: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{key}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $key): JsonResponse
    {
        $accessCheck = $this->ensurePlatformTemplateAdmin();
        if ($accessCheck instanceof JsonResponse) {
            return $accessCheck;
        }

        $template = $this->repairTemplateRepository->findOneByTemplateKey($key);
        if (!$template instanceof RepairTemplate) {
            return new JsonResponse(['error' => 'Vorlage nicht gefunden'], 404);
        }

        $template->setIsActive(false);
        $template->updateTimestamps();
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Vorlage deaktiviert']);
    }

    private function canManagePlatformTemplates(): bool
    {
        return $this->isGranted('ROLE_SUPERADMIN');
    }

    private function ensurePlatformTemplateAdmin(): ?JsonResponse
    {
        if (!$this->canManagePlatformTemplates()) {
            return new JsonResponse(['error' => 'Nur Superadmin darf Plattform-Vorlagen verwalten'], 403);
        }

        return null;
    }

    private function validateWritePayload(array $data, bool $isCreate): JsonResponse|true
    {
        if ($isCreate) {
            if (empty($data['template_key']) || !is_string($data['template_key'])) {
                return new JsonResponse(['error' => 'template_key ist erforderlich'], 400);
            }
            if (!preg_match('/^[a-z0-9_]+$/', strtolower(trim($data['template_key'])))) {
                return new JsonResponse(['error' => 'template_key: nur Kleinbuchstaben, Ziffern und Unterstrich'], 400);
            }
            if (empty($data['name']) || !is_string($data['name'])) {
                return new JsonResponse(['error' => 'name ist erforderlich'], 400);
            }
            if (!isset($data['structure_json']) || !is_array($data['structure_json'])) {
                return new JsonResponse(['error' => 'structure_json ist erforderlich'], 400);
            }
        }

        if (isset($data['material_class'])) {
            if (!in_array($data['material_class'], RepairTemplate::ALL_MATERIAL_CLASSES, true)) {
                return new JsonResponse([
                    'error' => 'Ungültige material_class. Erlaubt: ' . implode(', ', RepairTemplate::ALL_MATERIAL_CLASSES),
                ], 400);
            }
        }

        if (array_key_exists('diagram_json', $data) && $data['diagram_json'] !== null && !is_array($data['diagram_json'])) {
            return new JsonResponse(['error' => 'diagram_json muss ein Objekt sein'], 400);
        }

        if (isset($data['structure_json']) && !is_array($data['structure_json'])) {
            return new JsonResponse(['error' => 'structure_json muss ein Objekt sein'], 400);
        }

        return true;
    }

    private function applyWritePayload(RepairTemplate $template, array $data): void
    {
        if (isset($data['name']) && is_string($data['name'])) {
            $template->setName(trim($data['name']));
        }
        if (isset($data['material_class']) && is_string($data['material_class'])) {
            $template->setMaterialClass($data['material_class']);
        }
        if (isset($data['structure_json']) && is_array($data['structure_json'])) {
            $template->setStructureJson($data['structure_json']);
        }
        if (array_key_exists('diagram_json', $data)) {
            $template->setDiagramJson(is_array($data['diagram_json']) ? $data['diagram_json'] : null);
        }
        if (isset($data['is_active'])) {
            $template->setIsActive((bool) $data['is_active']);
        }
    }

    private function serializeTemplate(RepairTemplate $template, bool $detailed): array
    {
        $result = [
            'id' => $template->getId(),
            'template_key' => $template->getTemplateKey(),
            'name' => $template->getName(),
            'material_class' => $template->getMaterialClass(),
            'is_active' => $template->isActive(),
            'created_at' => $template->getCreatedAt()->format('c'),
            'updated_at' => $template->getUpdatedAt()->format('c'),
        ];

        if ($detailed) {
            $result['structure_json'] = $template->getStructureJson();
            $result['diagram_json'] = $template->getDiagramJson();
        }

        return $result;
    }
}
