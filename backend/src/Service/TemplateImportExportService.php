<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Department;
use App\Entity\MaterialTemplate;
use App\Entity\MaterialTemplateComponent;
use App\Entity\MaterialTemplateOption;
use App\Entity\MaterialTemplateOptionDelta;
use App\Entity\MaterialTemplateOptionGroup;
use App\Entity\MaterialTemplateRelatedAccessory;
use App\Entity\Address;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Zentraler Import/Export für Material-Vorlagen (v4/v5 JSON).
 */
class TemplateImportExportService
{
    /** Nur Materialwart (nicht Depchef) darf Vorlagen importieren/exportieren. */
    private const MATERIALWART_ROLES = ['mw', 'matwart'];

    /** Filter-Wert für Export: Vorlagen ohne Hersteller. */
    public const NO_MANUFACTURER_FILTER = '__NO_MANUFACTURER__';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<string>|null $templateIds
     *
     * @return array<string, mixed>
     */
    public function exportToJson(
        string $scope,
        ?string $departmentId = null,
        ?string $manufacturer = null,
        ?array $templateIds = null,
    ): array {
        $qb = $this->entityManager->getRepository(MaterialTemplate::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.components', 'c')
            ->addSelect('c')
            ->leftJoin('t.relatedAccessories', 'ra')
            ->addSelect('ra')
            ->orderBy('t.name', 'ASC');

        if ($scope === 'global') {
            $qb->where('t.departmentId IS NULL');
        } elseif ($departmentId !== null && $departmentId !== '') {
            $qb->where('t.departmentId = :departmentId')
                ->setParameter('departmentId', $departmentId);
        } else {
            return ['error' => 'department_id ist erforderlich für scope=department'];
        }

        if ($manufacturer !== null && $manufacturer !== '') {
            if ($manufacturer === self::NO_MANUFACTURER_FILTER) {
                $qb->andWhere('t.manufacturer IS NULL AND t.manufacturerAddressId IS NULL');
            } else {
                $qb->andWhere('LOWER(t.manufacturer) = LOWER(:manufacturer)')
                    ->setParameter('manufacturer', $manufacturer);
            }
        }

        if ($templateIds !== null && $templateIds !== []) {
            $qb->andWhere('t.id IN (:ids)')
                ->setParameter('ids', $templateIds);
        }

        /** @var MaterialTemplate[] $templates */
        $templates = $qb->getQuery()->getResult();

        if ($templates === []) {
            return ['error' => 'Keine Vorlagen gefunden'];
        }

        $exportManufacturer = $manufacturer;
        if ($exportManufacturer === null || $exportManufacturer === '') {
            $exportManufacturer = $templates[0]->getManufacturer() ?? 'unknown';
        } elseif ($exportManufacturer === self::NO_MANUFACTURER_FILTER) {
            $exportManufacturer = self::NO_MANUFACTURER_FILTER;
        }

        $exportedTemplates = [];
        foreach ($templates as $template) {
            $exportedTemplates[] = $this->serializeTemplateForExport($template);
        }

        return [
            'format_version' => 5,
            'manufacturer' => $exportManufacturer,
            'templates' => $exportedTemplates,
        ];
    }

    /**
     * @param array<string, mixed> $json
     * @param array<string, mixed> $opts scope, department_id?, duplicate_action, dry_run
     *
     * @return array<string, mixed>
     */
    public function importFromJson(array $json, array $opts): array
    {
        if (!isset($json['manufacturer']) || !isset($json['templates']) || !is_array($json['templates'])) {
            return ['success' => false, 'error' => 'Ungültiges JSON-Format. Erwartet: { "manufacturer": "...", "templates": [...] }'];
        }

        $scope = (string) ($opts['scope'] ?? 'department');
        $departmentId = isset($opts['department_id']) ? trim((string) $opts['department_id']) : null;
        $dryRun = (bool) ($opts['dry_run'] ?? false);

        $duplicateAction = (string) ($opts['duplicate_action'] ?? 'skip');
        if (!in_array($duplicateAction, ['skip', 'update', 'create'], true)) {
            $duplicateAction = 'skip';
        }
        if (!empty($opts['force'])) {
            $duplicateAction = 'update';
        }

        $department = null;
        if ($scope === 'global') {
            $departmentId = null;
        } else {
            if ($departmentId === null || $departmentId === '') {
                return ['success' => false, 'error' => 'department_id ist erforderlich'];
            }
            $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
            if (!$department) {
                return ['success' => false, 'error' => 'Department nicht gefunden'];
            }
        }

        $manufacturer = (string) $json['manufacturer'];
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $rows = [];

        foreach ($json['templates'] as $index => $tplData) {
            if (!is_array($tplData)) {
                ++$stats['errors'];
                $rows[] = [
                    'template_index' => $index,
                    'name' => null,
                    'status' => 'error',
                    'action' => null,
                    'errors' => ['Ungültiger Template-Eintrag'],
                ];
                continue;
            }

            $row = $this->importSingleTemplate(
                $tplData,
                $index,
                $manufacturer,
                $scope,
                $department,
                $duplicateAction,
                $dryRun,
            );
            $rows[] = $row;

            if ($row['status'] === 'error') {
                ++$stats['errors'];
            } elseif ($row['action'] === 'create') {
                ++$stats['created'];
            } elseif ($row['action'] === 'update') {
                ++$stats['updated'];
            } elseif ($row['action'] === 'skip') {
                ++$stats['skipped'];
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return [
            'success' => $stats['errors'] === 0,
            'dry_run' => $dryRun,
            'manufacturer' => $manufacturer,
            'rows' => $rows,
            'stats' => $stats,
            'total' => count($json['templates']),
            // Legacy-Felder für Abwärtskompatibilität
            'created' => $stats['created'],
            'updated' => $stats['updated'],
            'skipped' => $stats['skipped'],
        ];
    }

    public function assertCanImport(?string $departmentId, string $scope, ?User $user): ?string
    {
        if (!$user instanceof User) {
            return 'Nicht authentifiziert';
        }

        if ($scope === 'global') {
            return $this->canEditGlobalTemplates($user) ? null : 'Keine Berechtigung für zentrale Vorlagen';
        }

        if ($departmentId === null || $departmentId === '') {
            return 'department_id ist erforderlich';
        }

        $membership = $this->entityManager->getRepository(\App\Entity\Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if (!$membership) {
            return 'Keine Berechtigung für dieses Department';
        }

        $role = strtolower(trim($membership->getRole()));
        if (!in_array($role, self::MATERIALWART_ROLES, true)) {
            return 'Nur Materialwart (MW) darf Vorlagen importieren oder exportieren';
        }

        return null;
    }

    public function assertCanExport(?string $departmentId, string $scope, ?User $user): ?string
    {
        return $this->assertCanImport($departmentId, $scope, $user);
    }

    /**
     * @param array<string, mixed> $tplData
     *
     * @return array<string, mixed>
     */
    private function importSingleTemplate(
        array $tplData,
        int $index,
        string $manufacturer,
        string $scope,
        ?Department $department,
        string $duplicateAction,
        bool $dryRun,
    ): array {
        $templateName = trim((string) ($tplData['name'] ?? $tplData['id'] ?? 'Unbenannt'));
        $outcome = [
            'template_index' => $index,
            'name' => $templateName,
            'status' => 'ok',
            'action' => null,
            'errors' => [],
            'existing_template_id' => null,
        ];

        if ($templateName === '') {
            $outcome['status'] = 'error';
            $outcome['errors'][] = 'Template-Name fehlt';

            return $outcome;
        }

        $existing = $this->findExistingTemplate($templateName, $scope, $department);
        if ($existing !== null) {
            $outcome['existing_template_id'] = $existing->getId();
            if ($duplicateAction === 'skip') {
                $outcome['action'] = 'skip';
                $outcome['status'] = 'skipped';

                return $outcome;
            }
            if ($duplicateAction === 'create') {
                $existing = null;
            }
        }

        if ($dryRun) {
            $outcome['action'] = $existing !== null ? 'update' : 'create';

            return $outcome;
        }

        try {
            if ($existing !== null) {
                $template = $existing;
                $this->clearTemplateChildren($template);
                $outcome['action'] = 'update';
            } else {
                $template = new MaterialTemplate();
                $template->setId(IdGenerator::generate());
                $template->setDepartment($department);
                $template->setScope($scope === 'global' ? 'global' : 'department');
                $this->entityManager->persist($template);
                $outcome['action'] = 'create';
            }

            $this->applyTemplateFields($template, $tplData, $manufacturer, $scope);
            $this->applyComponents($template, $tplData);
            $this->applyRelatedAccessories($template, $tplData);
            $this->applyOptions($template, $tplData);
            $template->updateTimestamps();
        } catch (\Throwable $e) {
            $outcome['status'] = 'error';
            $outcome['errors'][] = $e->getMessage();
        }

        return $outcome;
    }

    private function findExistingTemplate(string $name, string $scope, ?Department $department): ?MaterialTemplate
    {
        $criteria = ['name' => $name];
        if ($scope === 'global') {
            $criteria['departmentId'] = null;
        } else {
            $criteria['departmentId'] = $department?->getId();
        }

        return $this->entityManager->getRepository(MaterialTemplate::class)->findOneBy($criteria);
    }

    private function clearTemplateChildren(MaterialTemplate $template): void
    {
        foreach ($template->getComponents()->toArray() as $comp) {
            $template->removeComponent($comp);
            $this->entityManager->remove($comp);
        }
        foreach ($template->getRelatedAccessories()->toArray() as $acc) {
            $template->removeRelatedAccessory($acc);
            $this->entityManager->remove($acc);
        }

        $existingOptions = $this->entityManager->getRepository(MaterialTemplateOption::class)
            ->findBy(['templateId' => $template->getId()]);
        foreach ($existingOptions as $opt) {
            foreach ($this->entityManager->getRepository(MaterialTemplateOptionDelta::class)->findBy(['optionId' => $opt->getId()]) as $d) {
                $this->entityManager->remove($d);
            }
            $this->entityManager->remove($opt);
        }
        foreach ($this->entityManager->getRepository(MaterialTemplateOptionGroup::class)->findBy(['templateId' => $template->getId()]) as $g) {
            $this->entityManager->remove($g);
        }
    }

    /**
     * @param array<string, mixed> $tplData
     */
    private function applyTemplateFields(MaterialTemplate $template, array $tplData, string $manufacturer, string $scope): void
    {
        $template->setName(trim((string) ($tplData['name'] ?? $tplData['id'] ?? 'Unbenannt')));
        $template->setDescription($this->nullableString($tplData['description'] ?? null));
        $this->applyManufacturerFromImport($template, $tplData, $manufacturer);
        $template->setModel($this->nullableString($tplData['model'] ?? null));
        $template->setMaterialType($this->readMaterialType($tplData));
        $template->setTemplateKind($this->nullableString($tplData['template_kind'] ?? $tplData['templateKind'] ?? null));
        $template->setTemplateDomain($this->nullableString($tplData['template_domain'] ?? $tplData['templateDomain'] ?? null));
        $template->setTentType($this->nullableString($tplData['tentType'] ?? $tplData['tent_type'] ?? null));
        $capacity = $tplData['capacity'] ?? null;
        $template->setCapacity($capacity !== null && $capacity !== '' ? (int) $capacity : null);
        $isActive = $tplData['isActive'] ?? $tplData['is_active'] ?? true;
        $template->setIsActive((bool) $isActive);
        $source = $this->nullableString($tplData['source'] ?? null);
        $template->setSource($source ?? strtolower($manufacturer));
        if ($scope === 'global') {
            $template->setScope('global');
        } else {
            $template->setScope('department');
        }
    }

    /**
     * @param array<string, mixed> $tplData
     */
    private function applyComponents(MaterialTemplate $template, array $tplData): void
    {
        if (!isset($tplData['components']) || !is_array($tplData['components'])) {
            return;
        }

        foreach ($tplData['components'] as $index => $compData) {
            if (!is_array($compData)) {
                continue;
            }
            $comp = new MaterialTemplateComponent();
            $comp->setId(IdGenerator::generate());
            $comp->setComponentType($this->readComponentType($compData));
            $comp->setName(trim((string) ($compData['name'] ?? $compData['component_type'] ?? $compData['type'] ?? 'Unbenannt')));
            $comp->setRequiredQty($this->readRequiredQty($compData));
            $comp->setIsOptional((bool) ($compData['optional'] ?? $compData['is_optional'] ?? false));
            $comp->setIsGeneric((bool) ($compData['is_generic'] ?? false));
            $tracking = (string) ($compData['tracking'] ?? 'bulk');
            $comp->setTracking($tracking === 'serialized' ? 'serialized' : 'bulk');
            $comp->setComponentSource(($compData['component_source'] ?? null) === 'self_provided' ? 'self_provided' : 'stock');
            $comp->setSortOrder((int) ($compData['sort_order'] ?? $index));
            if (isset($compData['repair_types']) && is_array($compData['repair_types'])) {
                $comp->setRepairTypes($compData['repair_types']);
            }
            $template->addComponent($comp);
        }
    }

    /**
     * @param array<string, mixed> $tplData
     */
    private function applyRelatedAccessories(MaterialTemplate $template, array $tplData): void
    {
        if (!isset($tplData['related_accessories']) || !is_array($tplData['related_accessories'])) {
            return;
        }

        foreach ($tplData['related_accessories'] as $index => $accData) {
            if (!is_array($accData)) {
                continue;
            }
            $acc = new MaterialTemplateRelatedAccessory();
            $acc->setId(IdGenerator::generate());
            $acc->setName(trim((string) ($accData['name'] ?? 'Zubehör')));
            $type = $accData['component_type'] ?? null;
            $acc->setComponentType(is_string($type) && trim($type) !== '' ? trim($type) : null);
            $acc->setIsGeneric((bool) ($accData['is_generic'] ?? false));
            $acc->setSortOrder((int) ($accData['sort_order'] ?? $index));
            $template->addRelatedAccessory($acc);
        }
    }

    /**
     * @param array<string, mixed> $tplData
     */
    private function applyOptions(MaterialTemplate $template, array $tplData): void
    {
        $hasGroups = isset($tplData['option_groups']) && is_array($tplData['option_groups']);
        $hasOptions = isset($tplData['options']) && is_array($tplData['options']);
        if (!$hasGroups && !$hasOptions) {
            return;
        }

        $groupIdMap = [];
        foreach (($tplData['option_groups'] ?? []) as $index => $g) {
            if (!is_array($g)) {
                continue;
            }
            $group = new MaterialTemplateOptionGroup();
            $group->setId(IdGenerator::generate());
            $group->setTemplate($template);
            $group->setName(trim((string) ($g['name'] ?? 'Gruppe')) ?: 'Gruppe');
            $st = (string) ($g['selection_type'] ?? 'exclusive');
            $group->setSelectionType(in_array($st, ['exclusive', 'multi', 'quantity'], true) ? $st : 'exclusive');
            $group->setMinSelect(max(0, (int) ($g['min_select'] ?? 0)));
            $group->setMaxSelect(isset($g['max_select']) && $g['max_select'] !== null ? max(0, (int) $g['max_select']) : null);
            $group->setSortOrder((int) ($g['sort_order'] ?? $index));
            $this->entityManager->persist($group);
            $payloadId = (string) ($g['id'] ?? $g['_key'] ?? 'g' . $index);
            $groupIdMap[$payloadId] = $group->getId();
        }

        foreach (($tplData['options'] ?? []) as $index => $o) {
            if (!is_array($o)) {
                continue;
            }
            $option = new MaterialTemplateOption();
            $option->setId(IdGenerator::generate());
            $option->setTemplate($template);
            $option->setName(trim((string) ($o['name'] ?? 'Option')) ?: 'Option');
            $dm = (string) ($o['display_mode'] ?? 'toggle');
            $option->setDisplayMode($dm === 'group' ? 'group' : 'toggle');
            $option->setDefaultSelected((bool) ($o['default_selected'] ?? false));
            $option->setSortOrder((int) ($o['sort_order'] ?? $index));
            $gid = $o['option_group_id'] ?? null;
            if ($gid !== null && $gid !== '' && isset($groupIdMap[(string) $gid])) {
                $group = $this->entityManager->getRepository(MaterialTemplateOptionGroup::class)->find($groupIdMap[(string) $gid]);
                if ($group) {
                    $option->setOptionGroup($group);
                    $option->setDisplayMode('group');
                }
            }
            $this->entityManager->persist($option);

            $deltaSort = 0;
            foreach (($o['deltas'] ?? []) as $d) {
                if (!is_array($d)) {
                    continue;
                }
                $delta = new MaterialTemplateOptionDelta();
                $delta->setId(IdGenerator::generate());
                $delta->setOption($option);
                $delta->setComponentType(trim((string) ($d['component_type'] ?? '')) ?: 'unknown');
                $delta->setName(trim((string) ($d['name'] ?? '')) ?: (string) ($d['component_type'] ?? 'Teil'));
                $delta->setQtyDelta((int) ($d['qty_delta'] ?? 0));
                $delta->setTracking(($d['tracking'] ?? 'bulk') === 'serialized' ? 'serialized' : 'bulk');
                $delta->setComponentSource(($d['component_source'] ?? null) === 'self_provided' ? 'self_provided' : 'stock');
                $delta->setIsGeneric((bool) ($d['is_generic'] ?? false));
                $delta->setSortOrder((int) ($d['sort_order'] ?? $deltaSort++));
                $this->entityManager->persist($delta);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTemplateForExport(MaterialTemplate $template): array
    {
        $data = [
            'name' => $template->getName(),
            'description' => $template->getDescription(),
            'manufacturer' => $template->getManufacturer(),
            'manufacturer_address_id' => $template->getManufacturerAddressId(),
            'template_kind' => $template->getTemplateKind(),
            'template_domain' => $template->getTemplateDomain(),
            'model' => $template->getModel(),
            'capacity' => $template->getCapacity(),
            'tentType' => $template->getTentType(),
            'materialType' => $template->getMaterialType(),
            'isActive' => $template->getIsActive(),
            'source' => $template->getSource(),
            'components' => [],
        ];

        foreach ($template->getComponents() as $comp) {
            $compExport = [
                'type' => $comp->getComponentType(),
                'name' => $comp->getName(),
                'required' => $comp->getRequiredQty(),
                'optional' => $comp->getIsOptional(),
                'tracking' => $comp->getTracking(),
                'component_source' => $comp->getComponentSource(),
            ];
            if ($comp->getIsGeneric()) {
                $compExport['is_generic'] = true;
            }
            if ($comp->getRepairTypes() !== null && $comp->getRepairTypes() !== []) {
                $compExport['repair_types'] = $comp->getRepairTypes();
            }
            $data['components'][] = $compExport;
        }

        $accessories = [];
        foreach ($template->getRelatedAccessories() as $acc) {
            $accessories[] = [
                'name' => $acc->getName(),
                'component_type' => $acc->getComponentType(),
                'is_generic' => $acc->getIsGeneric(),
                'sort_order' => $acc->getSortOrder(),
            ];
        }
        if ($accessories !== []) {
            $data['related_accessories'] = $accessories;
        }

        $groups = $this->entityManager->getRepository(MaterialTemplateOptionGroup::class)
            ->findBy(['templateId' => $template->getId()], ['sortOrder' => 'ASC']);
        $options = $this->entityManager->getRepository(MaterialTemplateOption::class)
            ->findBy(['templateId' => $template->getId()], ['sortOrder' => 'ASC']);

        if ($groups !== []) {
            $groupExportIdMap = [];
            $data['option_groups'] = [];
            foreach ($groups as $gi => $g) {
                $exportId = 'g' . $gi;
                $groupExportIdMap[(string) $g->getId()] = $exportId;
                $data['option_groups'][] = [
                    'id' => $exportId,
                    'name' => $g->getName(),
                    'selection_type' => $g->getSelectionType(),
                    'min_select' => $g->getMinSelect(),
                    'max_select' => $g->getMaxSelect(),
                    'sort_order' => $g->getSortOrder(),
                ];
            }

            $data['options'] = [];
            foreach ($options as $o) {
                $deltas = $this->entityManager->getRepository(MaterialTemplateOptionDelta::class)
                    ->findBy(['optionId' => $o->getId()], ['sortOrder' => 'ASC']);
                $optionExport = [
                    'name' => $o->getName(),
                    'display_mode' => $o->getDisplayMode(),
                    'default_selected' => $o->getDefaultSelected(),
                    'sort_order' => $o->getSortOrder(),
                    'deltas' => array_map(static fn (MaterialTemplateOptionDelta $d) => [
                        'component_type' => $d->getComponentType(),
                        'name' => $d->getName(),
                        'qty_delta' => $d->getQtyDelta(),
                        'tracking' => $d->getTracking(),
                        'component_source' => $d->getComponentSource(),
                        'is_generic' => $d->getIsGeneric(),
                        'sort_order' => $d->getSortOrder(),
                    ], $deltas),
                ];
                if ($o->getOptionGroupId() !== null && isset($groupExportIdMap[(string) $o->getOptionGroupId()])) {
                    $optionExport['option_group_id'] = $groupExportIdMap[(string) $o->getOptionGroupId()];
                }
                $data['options'][] = $optionExport;
            }
        } elseif ($options !== []) {
            $data['options'] = [];
            foreach ($options as $o) {
                $deltas = $this->entityManager->getRepository(MaterialTemplateOptionDelta::class)
                    ->findBy(['optionId' => $o->getId()], ['sortOrder' => 'ASC']);
                $data['options'][] = [
                    'name' => $o->getName(),
                    'display_mode' => $o->getDisplayMode(),
                    'default_selected' => $o->getDefaultSelected(),
                    'sort_order' => $o->getSortOrder(),
                    'deltas' => array_map(static fn (MaterialTemplateOptionDelta $d) => [
                        'component_type' => $d->getComponentType(),
                        'name' => $d->getName(),
                        'qty_delta' => $d->getQtyDelta(),
                        'tracking' => $d->getTracking(),
                        'component_source' => $d->getComponentSource(),
                        'is_generic' => $d->getIsGeneric(),
                        'sort_order' => $d->getSortOrder(),
                    ], $deltas),
                ];
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $tplData
     */
    private function readMaterialType(array $tplData): string
    {
        $raw = $tplData['materialType'] ?? $tplData['material_type'] ?? 'physical_combo';

        return in_array($raw, ['physical_combo', 'virtual_combo'], true) ? $raw : 'physical_combo';
    }

    /**
     * @param array<string, mixed> $compData
     */
    private function readComponentType(array $compData): string
    {
        $type = $compData['type'] ?? $compData['component_type'] ?? 'unknown';

        return trim((string) $type) !== '' ? trim((string) $type) : 'unknown';
    }

    /**
     * @param array<string, mixed> $compData
     */
    private function readRequiredQty(array $compData): int
    {
        if (isset($compData['required'])) {
            return max(0, (int) $compData['required']);
        }
        if (isset($compData['required_qty'])) {
            return max(0, (int) $compData['required_qty']);
        }

        return 1;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    /**
     * @param array<string, mixed> $tplData
     */
    private function applyManufacturerFromImport(MaterialTemplate $template, array $tplData, string $fileManufacturer): void
    {
        $addressId = $this->nullableString($tplData['manufacturer_address_id'] ?? null);
        if ($addressId !== null) {
            $address = $this->entityManager->getRepository(Address::class)->find($addressId);
            if ($address !== null && !$address->isDeleted()) {
                $template->setManufacturerAddress($address);
                $template->setManufacturer($this->addressDisplayLabel($address));

                return;
            }
        }

        $manufacturer = $this->nullableString($tplData['manufacturer'] ?? null);
        if ($manufacturer === null && $fileManufacturer !== self::NO_MANUFACTURER_FILTER) {
            $manufacturer = $this->nullableString($fileManufacturer);
        }
        $template->setManufacturerAddress(null);
        $template->setManufacturer($manufacturer);
    }

    private function addressDisplayLabel(Address $address): string
    {
        $label = trim((string) ($address->getCompany() ?: $address->getName() ?: ''));

        return $label !== '' ? $label : (string) $address->getId();
    }

    private function canEditGlobalTemplates(User $user): bool
    {
        foreach ($user->getRoles() as $role) {
            if (in_array($role, ['ROLE_SUPERADMIN', 'ROLE_ORGANISATIONSCHEF', 'ROLE_SUBORGCHEF'], true)) {
                return true;
            }
        }

        return false;
    }

    private function isGrantedSuperadmin(User $user): bool
    {
        foreach ($user->getRoles() as $role) {
            if ($role === 'ROLE_SUPERADMIN') {
                return true;
            }
        }

        return false;
    }
}
