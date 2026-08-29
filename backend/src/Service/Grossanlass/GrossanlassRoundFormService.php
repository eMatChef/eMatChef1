<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\ActivityGrossanlassRound;
use App\Entity\ActivityGrossanlassRoundForm;
use App\Entity\ActivityGrossanlassRoundFormField;
use App\Entity\ActivityGrossanlassWishResponseValue;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassConfig;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class GrossanlassRoundFormService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getFormForRound(Department $department, string $roundId): array
    {
        $round = $this->findRoundForDepartment($department, $roundId);
        $form = $this->findOrCreateFormForRound($round);

        return $this->toArray($form);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function updateForm(Department $department, User $user, string $roundId, array $data): array
    {
        if (!$this->access->canManageGrossanlassForm($user, $department)) {
            throw new \RuntimeException('Nur Materialwart darf das Formular bearbeiten');
        }

        $round = $this->findRoundForDepartment($department, $roundId);
        if ($round->getStatus() === ActivityGrossanlassRound::STATUS_CLOSED) {
            throw new \InvalidArgumentException('Geschlossene Runden: Formular nicht mehr bearbeitbar');
        }

        $form = $this->findOrCreateFormForRound($round);

        if (array_key_exists('intro_text', $data)) {
            $intro = trim((string) ($data['intro_text'] ?? ''));
            $form->setIntroText($intro === '' ? null : $intro);
        }

        if (array_key_exists('fields', $data) && is_array($data['fields'])) {
            $this->replaceFields($form, $data['fields']);
        }

        $form->touchUpdatedAt();
        $this->entityManager->flush();

        return $this->toArray($form);
    }

    public function createDefaultFormForRound(ActivityGrossanlassRound $round): ActivityGrossanlassRoundForm
    {
        $existing = $this->entityManager->getRepository(ActivityGrossanlassRoundForm::class)
            ->findOneBy(['roundId' => $round->getId()]);
        if ($existing instanceof ActivityGrossanlassRoundForm) {
            $this->ensureCompanyTipInquiryFields($round, $existing);

            return $existing;
        }

        $form = new ActivityGrossanlassRoundForm();
        $form->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::FORM, ActivityGrossanlassRoundForm::class));
        $form->setRound($round);

        $this->entityManager->persist($form);

        foreach (GrossanlassFormFieldCatalog::defaultFieldsForPurpose($round->getFormPurpose()) as $def) {
            $field = $this->createFieldFromDefinition($form, $def);
            $this->entityManager->persist($field);
        }
        $this->ensureCompanyTipInquiryFields($round, $form);

        return $form;
    }

    public function findOrCreateFormForRound(ActivityGrossanlassRound $round): ActivityGrossanlassRoundForm
    {
        $form = $this->entityManager->getRepository(ActivityGrossanlassRoundForm::class)
            ->findOneBy(['roundId' => $round->getId()]);
        if ($form instanceof ActivityGrossanlassRoundForm) {
            if ($this->ensureCompanyTipInquiryFields($round, $form)) {
                $this->entityManager->flush();
            }

            return $form;
        }

        $form = $this->createDefaultFormForRound($round);
        $this->entityManager->flush();

        return $form;
    }

    /**
     * @return list<ActivityGrossanlassRoundFormField>
     */
    public function getEnabledInputFields(ActivityGrossanlassRoundForm $form): array
    {
        $fields = $this->entityManager->getRepository(ActivityGrossanlassRoundFormField::class)
            ->createQueryBuilder('f')
            ->where('f.formId = :formId')
            ->andWhere('f.enabled = true')
            ->andWhere('f.role = :role')
            ->setParameter('formId', $form->getId())
            ->setParameter('role', GrossanlassFormFieldCatalog::ROLE_INPUT)
            ->orderBy('f.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($fields, fn ($f) => $f instanceof ActivityGrossanlassRoundFormField));
    }

    public function ensureCompanyTipInquiryFields(ActivityGrossanlassRound $round, ActivityGrossanlassRoundForm $form): bool
    {
        if ($round->getFormPurpose() !== ActivityGrossanlassRound::PURPOSE_COMPANY_TIP) {
            return false;
        }

        $fields = $this->entityManager->getRepository(ActivityGrossanlassRoundFormField::class)
            ->findBy(['formId' => $form->getId()]);
        $haveKeys = [];
        $dirty = false;
        $maxSort = 0;
        foreach ($fields as $field) {
            if (!$field instanceof ActivityGrossanlassRoundFormField) {
                continue;
            }
            $maxSort = max($maxSort, $field->getSortOrder());
            if ($field->getRole() !== GrossanlassFormFieldCatalog::ROLE_INPUT || $field->getSystemKey() !== null) {
                continue;
            }
            $key = GrossanlassFormFieldCatalog::inquiryKeyFromField($field->getConfigJson(), $field->getLabel());
            if ($key === null) {
                continue;
            }
            $haveKeys[$key] = true;
            $config = $field->getConfigJson() ?? [];
            if (($config['inquiry_key'] ?? null) !== $key) {
                $config['inquiry_key'] = $key;
                $field->setConfigJson($config);
                $dirty = true;
            }
        }

        foreach (GrossanlassFormFieldCatalog::companyTipInquiryFieldDefinitions() as $def) {
            $config = is_array($def['config'] ?? null) ? $def['config'] : [];
            $key = is_string($config['inquiry_key'] ?? null) ? $config['inquiry_key'] : null;
            if ($key === null || isset($haveKeys[$key])) {
                continue;
            }
            $maxSort += 10;
            $def['sort_order'] = $maxSort;
            $this->entityManager->persist($this->createFieldFromDefinition($form, $def));
            $haveKeys[$key] = true;
            $dirty = true;
        }

        $fields = $this->entityManager->getRepository(ActivityGrossanlassRoundFormField::class)
            ->findBy(['formId' => $form->getId()]);
        if ($this->syncCompanyTipInquiryPresentation($round, $fields)) {
            $dirty = true;
        }

        return $dirty;
    }

    /**
     * @param list<ActivityGrossanlassRoundFormField|mixed> $fields
     */
    private function syncCompanyTipInquiryPresentation(ActivityGrossanlassRound $round, array $fields): bool
    {
        $choices = $this->companyTipCategoryChoices($round);
        $dirty = false;
        foreach ($fields as $field) {
            if (!$field instanceof ActivityGrossanlassRoundFormField) {
                continue;
            }
            if ($field->getRole() !== GrossanlassFormFieldCatalog::ROLE_INPUT || $field->getSystemKey() !== null) {
                continue;
            }
            $key = GrossanlassFormFieldCatalog::inquiryKeyFromField($field->getConfigJson(), $field->getLabel());
            if ($key === 'categories' && $choices !== []) {
                if ($field->getCustomType() !== GrossanlassFormFieldCatalog::CUSTOM_SELECT) {
                    $field->setCustomType(GrossanlassFormFieldCatalog::CUSTOM_SELECT);
                    $dirty = true;
                }
                $options = ['multiple' => true, 'choices' => $choices];
                if ($field->getOptionsJson() !== $options) {
                    $field->setOptionsJson($options);
                    $dirty = true;
                }
                if (in_array($field->getLabel(), ['Bereich', 'Kategorie', 'Kategorie / Bereich'], true)) {
                    $field->setLabel('Bereiche');
                    $dirty = true;
                }
            }
            if ($key === 'offering' || $key === 'notes') {
                $config = $field->getConfigJson() ?? [];
                if (($config['multiline'] ?? false) !== true) {
                    $config['multiline'] = true;
                    if ($key !== '' && ($config['inquiry_key'] ?? null) !== $key) {
                        $config['inquiry_key'] = $key;
                    }
                    $field->setConfigJson($config);
                    $dirty = true;
                }
            }
        }

        return $dirty;
    }

    /**
     * @return list<string>
     */
    private function companyTipCategoryChoices(ActivityGrossanlassRound $round): array
    {
        $departmentId = $round->getActivity()->getDepartmentId();
        $rows = $this->entityManager->getRepository(ActivityGrossanlassProcurementCategory::class)
            ->findBy(['departmentId' => $departmentId]);
        /** @var array<string, list<ActivityGrossanlassProcurementCategory>> $byParent */
        $byParent = [];
        foreach ($rows as $row) {
            if (!$row instanceof ActivityGrossanlassProcurementCategory) {
                continue;
            }
            $parentKey = $row->getParentId() ?? '';
            $byParent[$parentKey][] = $row;
        }
        foreach ($byParent as &$list) {
            usort(
                $list,
                static fn (ActivityGrossanlassProcurementCategory $a, ActivityGrossanlassProcurementCategory $b): int => $a->getSortOrder() <=> $b->getSortOrder()
                    ?: strcasecmp($a->getName(), $b->getName()),
            );
        }
        unset($list);

        $out = [];
        $walk = function (string $parentKey, int $depth) use (&$walk, &$out, $byParent): void {
            foreach ($byParent[$parentKey] ?? [] as $cat) {
                $out[] = ($depth > 0 ? '↳ ' : '') . $cat->getName();
                $walk($cat->getId(), $depth + 1);
            }
        };
        $walk('', 0);

        return $out;
    }

    public function findRoundForDepartment(Department $department, string $roundId): ActivityGrossanlassRound
    {
        $config = $department->getGrossanlassConfig();
        if (!$config instanceof DepartmentGrossanlassConfig) {
            throw new \RuntimeException('Grossanlass-Konfiguration fehlt');
        }
        $activity = $config->getMainActivity();
        if ($activity === null) {
            throw new \RuntimeException('Haupt-Aktivität fehlt');
        }

        $round = $this->entityManager->getRepository(ActivityGrossanlassRound::class)->find($roundId);
        if ($round === null || $round->getActivityId() !== $activity->getId()) {
            throw new \InvalidArgumentException('Planungsrunde nicht gefunden');
        }

        return $round;
    }

    /**
     * @param list<array<string, mixed>> $fieldPayloads
     */
    private function replaceFields(ActivityGrossanlassRoundForm $form, array $fieldPayloads): void
    {
        /** @var array<string, ActivityGrossanlassRoundFormField> $existingById */
        $existingById = [];
        foreach ($this->entityManager->getRepository(ActivityGrossanlassRoundFormField::class)
            ->findBy(['formId' => $form->getId()]) as $field) {
            if ($field instanceof ActivityGrossanlassRoundFormField) {
                $existingById[$field->getId()] = $field;
            }
        }

        $keepIds = [];
        $sort = 0;
        foreach ($fieldPayloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }
            $def = $this->normalizeFieldPayload($payload, $sort);
            $payloadId = isset($payload['id']) && is_string($payload['id']) && strlen($payload['id']) === 12
                ? $payload['id']
                : null;

            if ($payloadId !== null && isset($existingById[$payloadId])) {
                $field = $existingById[$payloadId];
                $this->applyFieldDefinition($field, $def);
            } else {
                $field = $this->createFieldFromDefinition($form, $def);
                if ($payloadId !== null) {
                    $field->setId($payloadId);
                }
                $this->entityManager->persist($field);
            }

            $keepIds[] = $field->getId();
            $sort += 10;
        }

        foreach ($existingById as $id => $field) {
            if (in_array($id, $keepIds, true)) {
                continue;
            }
            if ($this->fieldHasResponseValues($id)) {
                throw new \InvalidArgumentException(
                    sprintf('Feld «%s» kann nicht entfernt werden, da bereits Antworten existieren', $field->getLabel()),
                );
            }
            $this->entityManager->remove($field);
        }
    }

    private function fieldHasResponseValues(string $fieldId): bool
    {
        $count = (int) $this->entityManager->getRepository(ActivityGrossanlassWishResponseValue::class)
            ->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.fieldId = :fieldId')
            ->setParameter('fieldId', $fieldId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function normalizeFieldPayload(array $payload, int $defaultSort): array
    {
        $role = (string) ($payload['role'] ?? GrossanlassFormFieldCatalog::ROLE_INPUT);
        if (!in_array($role, [GrossanlassFormFieldCatalog::ROLE_INPUT, GrossanlassFormFieldCatalog::ROLE_META], true)) {
            throw new \InvalidArgumentException('Ungültige Feldrolle: ' . $role);
        }

        $systemKey = $this->nullableString($payload['system_key'] ?? null);
        $customType = $this->nullableString($payload['custom_type'] ?? null);

        if ($role === GrossanlassFormFieldCatalog::ROLE_META) {
            if ($systemKey === null || !in_array($systemKey, GrossanlassFormFieldCatalog::META_SYSTEM_KEYS, true)) {
                throw new \InvalidArgumentException('Meta-Feld benötigt gültigen system_key');
            }
            $customType = null;
        } elseif ($systemKey !== null) {
            if (!in_array($systemKey, GrossanlassFormFieldCatalog::SYSTEM_KEYS, true)) {
                throw new \InvalidArgumentException('Ungültiger system_key: ' . $systemKey);
            }
            if (in_array($systemKey, GrossanlassFormFieldCatalog::META_SYSTEM_KEYS, true)) {
                throw new \InvalidArgumentException('Meta-Felder gehören in role=meta');
            }
            $customType = null;
        } else {
            if ($customType === null || !in_array($customType, GrossanlassFormFieldCatalog::CUSTOM_TYPES, true)) {
                throw new \InvalidArgumentException('Custom-Feld benötigt gültigen custom_type');
            }
        }

        $label = trim((string) ($payload['label'] ?? ''));
        if ($label === '') {
            throw new \InvalidArgumentException('Feldlabel ist erforderlich');
        }

        return [
            'role' => $role,
            'system_key' => $systemKey,
            'custom_type' => $customType,
            'label' => $label,
            'help_text' => isset($payload['help_text']) ? trim((string) $payload['help_text']) : null,
            'required' => (bool) ($payload['required'] ?? false),
            'enabled' => (bool) ($payload['enabled'] ?? true),
            'sort_order' => (int) ($payload['sort_order'] ?? $defaultSort),
            'options' => is_array($payload['options'] ?? null) ? $payload['options'] : null,
            'config' => is_array($payload['config'] ?? null) ? $payload['config'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $def
     */
    private function createFieldFromDefinition(ActivityGrossanlassRoundForm $form, array $def): ActivityGrossanlassRoundFormField
    {
        $field = new ActivityGrossanlassRoundFormField();
        $field->setId(GrossanlassIdGenerator::unique($this->entityManager, GrossanlassIdGenerator::FORM_FIELD, ActivityGrossanlassRoundFormField::class));
        $field->setForm($form);
        $this->applyFieldDefinition($field, $def);

        return $field;
    }

    /**
     * @param array<string, mixed> $def
     */
    private function applyFieldDefinition(ActivityGrossanlassRoundFormField $field, array $def): void
    {
        $field->setRole((string) $def['role']);
        $field->setSystemKey($this->nullableString($def['system_key'] ?? null));
        $field->setCustomType($this->nullableString($def['custom_type'] ?? null));
        $field->setLabel((string) $def['label']);
        $help = $def['help_text'] ?? null;
        $field->setHelpText(is_string($help) && $help !== '' ? $help : null);
        $field->setRequired((bool) ($def['required'] ?? false));
        $field->setEnabled((bool) ($def['enabled'] ?? true));
        $field->setSortOrder((int) ($def['sort_order'] ?? 0));
        $field->setOptionsJson(is_array($def['options'] ?? null) ? $def['options'] : null);
        $field->setConfigJson(is_array($def['config'] ?? null) ? $def['config'] : null);
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(ActivityGrossanlassRoundForm $form): array
    {
        $fields = $this->entityManager->getRepository(ActivityGrossanlassRoundFormField::class)
            ->createQueryBuilder('f')
            ->where('f.formId = :formId')
            ->setParameter('formId', $form->getId())
            ->orderBy('f.sortOrder', 'ASC')
            ->addOrderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return [
            'id' => $form->getId(),
            'round_id' => $form->getRoundId(),
            'intro_text' => $form->getIntroText(),
            'fields' => array_map(fn (ActivityGrossanlassRoundFormField $f) => $this->fieldToArray($f), $fields),
            'created_at' => $form->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $form->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldToArray(ActivityGrossanlassRoundFormField $field): array
    {
        return [
            'id' => $field->getId(),
            'role' => $field->getRole(),
            'system_key' => $field->getSystemKey(),
            'custom_type' => $field->getCustomType(),
            'label' => $field->getLabel(),
            'help_text' => $field->getHelpText(),
            'required' => $field->isRequired(),
            'enabled' => $field->isEnabled(),
            'sort_order' => $field->getSortOrder(),
            'options' => $field->getOptionsJson(),
            'config' => $field->getConfigJson(),
        ];
    }
}
