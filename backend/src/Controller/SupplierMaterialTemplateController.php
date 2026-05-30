<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SupplierMaterialTemplate;
use App\Entity\SupplierMaterialTemplateComponent;
use App\Entity\SupplierMaterialTemplateOption;
use App\Entity\SupplierMaterialTemplateOptionDelta;
use App\Entity\SupplierMaterialTemplateOptionGroup;
use App\Entity\User;
use App\Repository\SupplierMaterialTemplateRepository;
use App\Security\Voter\SupplierCompanyVoter;
use App\Service\Supplier\SupplierCompanyAccessService;
use App\Service\Supplier\SupplierLegacyTemplateImportService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/supplier-companies/{companyId}/material-templates', name: 'api_supplier_material_templates_')]
class SupplierMaterialTemplateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierMaterialTemplateRepository $templateRepository,
        private SupplierCompanyAccessService $accessService,
        private SupplierLegacyTemplateImportService $legacyTemplateImportService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function list(string $companyId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireTemplatesAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $templates = $this->templateRepository->findByCompanyId($companyId);

        return new JsonResponse([
            'material_templates' => array_map(
                static fn (SupplierMaterialTemplate $t) => $t->toArray(false),
                $templates
            ),
        ]);
    }

    #[Route('/legacy-global-hint', name: 'legacy_global_hint', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function legacyGlobalHint(string $companyId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireTemplatesAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        try {
            $preview = $this->legacyTemplateImportService->getPreview($companyId);

            return new JsonResponse([
                'available_count' => $preview['available_count'],
                'already_imported_count' => $preview['already_imported_count'],
                'manufacturer_key' => $preview['manufacturer_key'],
                'has_global_templates' => $preview['available_count'] > 0,
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function create(string $companyId, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $company = $this->accessService->requireTemplatesAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse(['error' => 'name ist erforderlich'], 400);
        }

        try {
            $template = new SupplierMaterialTemplate();
            $template->setId(IdGenerator::generateUnique($this->entityManager, SupplierMaterialTemplate::class));
            $template->setSupplierCompany($company);
            $this->applyHeaderData($template, $data);
            $this->replaceStructure($template, $data);

            $this->entityManager->persist($template);
            $this->entityManager->flush();

            return new JsonResponse([
                'material_template' => $template->toArray(true),
                'message' => 'Material-Vorlage erstellt',
            ], 201);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Erstellen: ' . $exception->getMessage()], 500);
        }
    }

    #[Route('/{templateId}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function show(string $companyId, string $templateId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireTemplatesAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $template = $this->requireTemplate($companyId, $templateId);

        return new JsonResponse(['material_template' => $template->toArray(true)]);
    }

    #[Route('/{templateId}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function update(string $companyId, string $templateId, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireTemplatesAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $template = $this->requireTemplate($companyId, $templateId);
        $data = json_decode($request->getContent(), true) ?: [];

        try {
            $this->applyHeaderData($template, $data);
            if (
                \array_key_exists('components', $data)
                || \array_key_exists('option_groups', $data)
                || \array_key_exists('standalone_options', $data)
            ) {
                $this->replaceStructure($template, $data);
            }
            $template->touch();
            $this->entityManager->flush();

            return new JsonResponse([
                'material_template' => $template->toArray(true),
                'message' => 'Material-Vorlage aktualisiert',
            ]);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Aktualisieren: ' . $exception->getMessage()], 500);
        }
    }

    #[Route('/{templateId}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]
    public function delete(string $companyId, string $templateId): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $this->accessService->requireTemplatesAccess($user, $companyId);
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        $template = $this->requireTemplate($companyId, $templateId);

        try {
            $this->entityManager->remove($template);
            $this->entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Material-Vorlage gelöscht']);
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => 'Fehler beim Löschen: ' . $exception->getMessage()], 500);
        }
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function requireTemplate(string $companyId, string $templateId): SupplierMaterialTemplate
    {
        $template = $this->templateRepository->findOneByCompanyAndId($companyId, $templateId);
        if (!$template instanceof SupplierMaterialTemplate) {
            throw $this->createNotFoundException('Material-Vorlage nicht gefunden');
        }

        return $template;
    }

    /** @param array<string, mixed> $data */
    private function applyHeaderData(SupplierMaterialTemplate $template, array $data): void
    {
        if (\array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new \InvalidArgumentException('name darf nicht leer sein');
            }
            $template->setName($name);
        }
        if (\array_key_exists('description', $data)) {
            $template->setDescription($this->nullableString($data['description']));
        }
        if (\array_key_exists('manufacturer', $data)) {
            $template->setManufacturer($this->nullableString($data['manufacturer']));
        }
        if (\array_key_exists('model', $data)) {
            $template->setModel($this->nullableString($data['model']));
        }
        if (\array_key_exists('material_type', $data)) {
            $materialType = strtolower(trim((string) $data['material_type']));
            if (!\in_array($materialType, [
                SupplierMaterialTemplate::MATERIAL_TYPE_PHYSICAL_COMBO,
                SupplierMaterialTemplate::MATERIAL_TYPE_VIRTUAL_COMBO,
            ], true)) {
                throw new \InvalidArgumentException('Ungültiger material_type');
            }
            $template->setMaterialType($materialType);
        }
        if (\array_key_exists('tent_type', $data)) {
            $template->setTentType($this->nullableString($data['tent_type']));
        }
        if (\array_key_exists('capacity', $data)) {
            $template->setCapacity($this->nullableInt($data['capacity']));
        }
        if (\array_key_exists('category_hint', $data)) {
            $template->setCategoryHint($this->nullableString($data['category_hint']));
        }
        if (\array_key_exists('unit_price', $data)) {
            $template->setUnitPrice($this->nullableDecimal($data['unit_price']));
        }
        if (\array_key_exists('currency', $data)) {
            $currency = strtoupper(trim((string) $data['currency']));
            if ($currency === '') {
                throw new \InvalidArgumentException('currency darf nicht leer sein');
            }
            $template->setCurrency($currency);
        }
        if (\array_key_exists('is_active', $data)) {
            $template->setIsActive((bool) $data['is_active']);
        }
        if (\array_key_exists('visibility', $data)) {
            $visibility = strtolower(trim((string) $data['visibility']));
            if (!\in_array($visibility, [
                SupplierMaterialTemplate::VISIBILITY_PRIVATE,
                SupplierMaterialTemplate::VISIBILITY_DEPARTMENTS,
                SupplierMaterialTemplate::VISIBILITY_GLOBAL,
            ], true)) {
                throw new \InvalidArgumentException('Ungültige visibility');
            }
            $template->setVisibility($visibility);
        }
        if (\array_key_exists('status', $data)) {
            $status = strtolower(trim((string) $data['status']));
            if (!\in_array($status, [
                SupplierMaterialTemplate::STATUS_DRAFT,
                SupplierMaterialTemplate::STATUS_PUBLISHED,
                SupplierMaterialTemplate::STATUS_PENDING_REVIEW,
            ], true)) {
                throw new \InvalidArgumentException('Ungültiger status');
            }
            $template->setStatus($status);
        }
        if (\array_key_exists('source', $data)) {
            $template->setSource($this->nullableString($data['source']));
        }

        $this->enforceVisibilityRules($template);
    }

    private function enforceVisibilityRules(SupplierMaterialTemplate $template): void
    {
        if (
            $template->getVisibility() === SupplierMaterialTemplate::VISIBILITY_GLOBAL
            && $template->getStatus() === SupplierMaterialTemplate::STATUS_PUBLISHED
        ) {
            $template->setStatus(SupplierMaterialTemplate::STATUS_PENDING_REVIEW);
        }
    }

    /** @param array<string, mixed> $data */
    private function replaceStructure(SupplierMaterialTemplate $template, array $data): void
    {
        if (\array_key_exists('components', $data)) {
            $this->replaceComponents($template, $data['components']);
        }
        if (
            \array_key_exists('option_groups', $data)
            || \array_key_exists('standalone_options', $data)
        ) {
            $this->replaceOptions($template, $data['option_groups'] ?? [], $data['standalone_options'] ?? []);
        }
    }

    /** @param mixed $lines */
    private function replaceComponents(SupplierMaterialTemplate $template, mixed $lines): void
    {
        if (!\is_array($lines)) {
            throw new \InvalidArgumentException('components muss ein Array sein');
        }

        $template->clearComponents();

        foreach ($lines as $index => $line) {
            if (!\is_array($line)) {
                continue;
            }
            $componentType = trim((string) ($line['component_type'] ?? ''));
            $name = trim((string) ($line['name'] ?? ''));
            if ($componentType === '' || $name === '') {
                throw new \InvalidArgumentException('component_type und name sind pro Komponente erforderlich');
            }

            $component = new SupplierMaterialTemplateComponent();
            $component->setId(IdGenerator::generateUnique($this->entityManager, SupplierMaterialTemplateComponent::class));
            $component->setComponentType($componentType);
            $component->setName($name);
            $component->setRequiredQty(max(1, (int) ($line['required_qty'] ?? 1)));
            $component->setIsOptional((bool) ($line['is_optional'] ?? false));
            $component->setTracking($this->normalizeTracking($line['tracking'] ?? 'bulk'));
            $component->setComponentSource($this->normalizeComponentSource($line['component_source'] ?? 'stock'));
            $component->setIsGeneric((bool) ($line['is_generic'] ?? false));
            $component->setSortOrder((int) ($line['sort_order'] ?? $index));
            $template->addComponent($component);
        }
    }

    /** @param mixed $groups @param mixed $standalone */
    private function replaceOptions(SupplierMaterialTemplate $template, mixed $groups, mixed $standalone): void
    {
        if (!\is_array($groups)) {
            throw new \InvalidArgumentException('option_groups muss ein Array sein');
        }
        if (!\is_array($standalone)) {
            throw new \InvalidArgumentException('standalone_options muss ein Array sein');
        }

        $template->clearOptions();
        $template->clearOptionGroups();

        foreach ($groups as $groupIndex => $groupData) {
            if (!\is_array($groupData)) {
                continue;
            }
            $groupName = trim((string) ($groupData['name'] ?? ''));
            if ($groupName === '') {
                throw new \InvalidArgumentException('name ist pro Options-Gruppe erforderlich');
            }

            $group = new SupplierMaterialTemplateOptionGroup();
            $group->setId(IdGenerator::generateUnique($this->entityManager, SupplierMaterialTemplateOptionGroup::class));
            $group->setName($groupName);
            $group->setSelectionType($this->normalizeSelectionType($groupData['selection_type'] ?? 'exclusive'));
            $group->setMinSelect((int) ($groupData['min_select'] ?? 0));
            $group->setMaxSelect($this->nullableInt($groupData['max_select'] ?? null));
            $group->setSortOrder((int) ($groupData['sort_order'] ?? $groupIndex));
            $template->addOptionGroup($group);

            $options = $groupData['options'] ?? [];
            if (!\is_array($options)) {
                throw new \InvalidArgumentException('options muss ein Array sein');
            }
            foreach ($options as $optionIndex => $optionData) {
                $this->createOption($template, $group, $optionData, $optionIndex);
            }
        }

        foreach ($standalone as $optionIndex => $optionData) {
            $this->createOption($template, null, $optionData, $optionIndex);
        }
    }

    /** @param array<string, mixed> $optionData */
    private function createOption(
        SupplierMaterialTemplate $template,
        ?SupplierMaterialTemplateOptionGroup $group,
        mixed $optionData,
        int $sortFallback,
    ): void {
        if (!\is_array($optionData)) {
            return;
        }
        $name = trim((string) ($optionData['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name ist pro Option erforderlich');
        }

        $option = new SupplierMaterialTemplateOption();
        $option->setId(IdGenerator::generateUnique($this->entityManager, SupplierMaterialTemplateOption::class));
        $option->setName($name);
        $option->setDisplayMode($this->normalizeDisplayMode($optionData['display_mode'] ?? 'toggle'));
        $option->setDefaultSelected((bool) ($optionData['default_selected'] ?? false));
        $option->setSortOrder((int) ($optionData['sort_order'] ?? $sortFallback));
        $option->setOptionGroup($group);
        $template->addOption($option);

        $deltas = $optionData['deltas'] ?? [];
        if (!\is_array($deltas)) {
            throw new \InvalidArgumentException('deltas muss ein Array sein');
        }
        foreach ($deltas as $deltaIndex => $deltaData) {
            if (!\is_array($deltaData)) {
                continue;
            }
            $componentType = trim((string) ($deltaData['component_type'] ?? ''));
            $deltaName = trim((string) ($deltaData['name'] ?? ''));
            if ($componentType === '' || $deltaName === '') {
                throw new \InvalidArgumentException('component_type und name sind pro Delta erforderlich');
            }

            $delta = new SupplierMaterialTemplateOptionDelta();
            $delta->setId(IdGenerator::generateUnique($this->entityManager, SupplierMaterialTemplateOptionDelta::class));
            $delta->setComponentType($componentType);
            $delta->setName($deltaName);
            $delta->setQtyDelta((int) ($deltaData['qty_delta'] ?? 0));
            $delta->setTracking($this->normalizeTracking($deltaData['tracking'] ?? 'bulk'));
            $delta->setComponentSource($this->normalizeComponentSource($deltaData['component_source'] ?? 'stock'));
            $delta->setIsGeneric((bool) ($deltaData['is_generic'] ?? false));
            $delta->setSortOrder((int) ($deltaData['sort_order'] ?? $deltaIndex));
            $option->addDelta($delta);
        }
    }

    private function normalizeTracking(mixed $value): string
    {
        $tracking = strtolower(trim((string) $value));
        if (!\in_array($tracking, ['bulk', 'serialized'], true)) {
            throw new \InvalidArgumentException('Ungültiger tracking-Wert');
        }

        return $tracking;
    }

    private function normalizeComponentSource(mixed $value): string
    {
        $source = strtolower(trim((string) $value));
        if (!\in_array($source, ['stock', 'self_provided'], true)) {
            throw new \InvalidArgumentException('Ungültiger component_source-Wert');
        }

        return $source;
    }

    private function normalizeSelectionType(mixed $value): string
    {
        $type = strtolower(trim((string) $value));
        if (!\in_array($type, ['exclusive', 'multi', 'quantity'], true)) {
            throw new \InvalidArgumentException('Ungültiger selection_type-Wert');
        }

        return $type;
    }

    private function normalizeDisplayMode(mixed $value): string
    {
        $mode = strtolower(trim((string) $value));
        if (!\in_array($mode, ['toggle', 'group'], true)) {
            throw new \InvalidArgumentException('Ungültiger display_mode-Wert');
        }

        return $mode;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
