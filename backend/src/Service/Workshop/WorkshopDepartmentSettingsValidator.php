<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Category;
use App\Entity\DepartmentSetting;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Validiert workshop.* Department-Settings vor dem Speichern.
 */
class WorkshopDepartmentSettingsValidator
{
    public const ORDER_REMINDER_MODE_DAYS = 'days';
    public const ORDER_REMINDER_MODE_DOCUMENT_DATE = 'document_date';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string> Nur erlaubte workshop.* Keys mit normalisierten Werten
     */
    public function filterAllowed(array $data): array
    {
        $allowedKeys = array_keys(DepartmentSetting::getWorkshopDefaults());
        $filtered = [];

        foreach ($data as $key => $value) {
            if (!\is_string($key) || !\in_array($key, $allowedKeys, true)) {
                continue;
            }
            // System-verwaltet: WorkshopSparePartsCategoryBootstrapService
            if ($key === 'workshop.spare_parts_category_id') {
                continue;
            }
            $filtered[$key] = trim((string) $value);
        }

        return $filtered;
    }

    /**
     * @param array<string, string> $values bereits gefilterte workshop.* Werte
     *
     * @return list<string> leer = ok
     */
    public function validate(array $values, string $departmentId): array
    {
        $errors = [];

        if (\array_key_exists('workshop.hourly_rate_chf', $values)) {
            $err = $this->validateHourlyRate($values['workshop.hourly_rate_chf']);
            if ($err !== null) {
                $errors[] = $err;
            }
        }

        if (\array_key_exists('workshop.order_reminder_days', $values)) {
            $err = $this->validateReminderDays($values['workshop.order_reminder_days']);
            if ($err !== null) {
                $errors[] = $err;
            }
        }

        if (\array_key_exists('workshop.order_reminder_mode', $values)) {
            $err = $this->validateReminderMode($values['workshop.order_reminder_mode']);
            if ($err !== null) {
                $errors[] = $err;
            }
        }

        if (\array_key_exists('workshop.spare_parts_category_id', $values)) {
            $err = $this->validateSparePartsCategory($values['workshop.spare_parts_category_id'], $departmentId);
            if ($err !== null) {
                $errors[] = $err;
            }
        }

        return $errors;
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, string>
     */
    public function normalize(array $values): array
    {
        $normalized = $values;

        if (isset($normalized['workshop.hourly_rate_chf'])) {
            $normalized['workshop.hourly_rate_chf'] = $this->normalizeHourlyRate($normalized['workshop.hourly_rate_chf']);
        }

        if (isset($normalized['workshop.order_reminder_days'])) {
            $normalized['workshop.order_reminder_days'] = (string) (int) $normalized['workshop.order_reminder_days'];
        }

        if (isset($normalized['workshop.order_reminder_mode'])) {
            $normalized['workshop.order_reminder_mode'] = strtolower(trim($normalized['workshop.order_reminder_mode']));
        }

        if (isset($normalized['workshop.spare_parts_category_id'])) {
            $normalized['workshop.spare_parts_category_id'] = trim($normalized['workshop.spare_parts_category_id']);
        }

        return $normalized;
    }

    private function validateHourlyRate(string $value): ?string
    {
        $normalized = str_replace(',', '.', trim($value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return 'workshop.hourly_rate_chf muss eine Zahl sein';
        }
        if ((float) $normalized < 0) {
            return 'workshop.hourly_rate_chf darf nicht negativ sein';
        }

        return null;
    }

    private function normalizeHourlyRate(string $value): string
    {
        $normalized = str_replace(',', '.', trim($value));

        return number_format((float) $normalized, 2, '.', '');
    }

    private function validateReminderDays(string $value): ?string
    {
        if ($value === '' || !ctype_digit($value)) {
            return 'workshop.order_reminder_days muss eine ganze Zahl sein';
        }
        $days = (int) $value;
        if ($days < 1 || $days > 365) {
            return 'workshop.order_reminder_days muss zwischen 1 und 365 liegen';
        }

        return null;
    }

    private function validateReminderMode(string $value): ?string
    {
        $mode = strtolower(trim($value));
        if (!\in_array($mode, [self::ORDER_REMINDER_MODE_DAYS, self::ORDER_REMINDER_MODE_DOCUMENT_DATE], true)) {
            return 'workshop.order_reminder_mode muss "days" oder "document_date" sein';
        }

        return null;
    }

    private function validateSparePartsCategory(string $value, string $departmentId): ?string
    {
        $categoryId = trim($value);
        if ($categoryId === '') {
            return null;
        }

        if (strlen($categoryId) !== 12) {
            return 'workshop.spare_parts_category_id ist ungültig';
        }

        $category = $this->entityManager->getRepository(Category::class)->find($categoryId);
        if (!$category instanceof Category) {
            return 'workshop.spare_parts_category_id: Kategorie nicht gefunden';
        }
        if ($category->getDepartmentId() !== $departmentId) {
            return 'workshop.spare_parts_category_id gehört nicht zu diesem Department';
        }

        return null;
    }
}
