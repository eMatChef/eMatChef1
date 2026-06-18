<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityJsOrder;
use App\Entity\Address;
use App\Entity\DepartmentSetting;
use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Vorbefüllung J+S-Bestellformular (Blöcke 1–3) aus Aktivität, Profil und Department-Defaults.
 */
class JsOrderPrefillService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /** @return array<string, mixed> */
    public static function emptyFormData(): array
    {
        return [
            'block1' => [
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'address' => '',
                'postal_code' => '',
                'city' => '',
                'canton' => '',
                'phone' => '',
                'person_nr' => '',
                'offer_number' => '',
                'user_overridden' => [],
            ],
            'block2' => [
                'course_type' => '',
                'participant_count' => null,
                'delivery_date' => '',
                'return_date' => '',
                'coach_first_name' => '',
                'coach_last_name' => '',
                'coach_person_nr' => '',
                'coach_email' => '',
                'user_overridden' => [],
            ],
            'block3' => [
                'venue_name' => '',
                'contact_first_name' => '',
                'contact_last_name' => '',
                'address' => '',
                'postal_code' => '',
                'city' => '',
                'canton' => '',
                'delivery_phone' => '',
                'camp_leader_phone' => '',
                'user_overridden' => [],
            ],
        ];
    }

    /** @return array<string, string> */
    public function loadDepartmentJsDefaults(string $departmentId): array
    {
        $defaults = DepartmentSetting::getJsMaterialDefaults();
        /** @var DepartmentSetting[] $settings */
        $settings = $this->entityManager->getRepository(DepartmentSetting::class)->findBy([
            'departmentId' => $departmentId,
        ]);

        foreach ($settings as $setting) {
            $key = $setting->getSettingKey();
            if (\array_key_exists($key, $defaults)) {
                $defaults[$key] = trim($setting->getSettingValue());
            }
        }

        return $defaults;
    }

    /**
     * Baut Vorschlagswerte aus Aktivität/Profil — ohne bestehende Order zu berücksichtigen.
     *
     * @return array<string, mixed>
     */
    public function buildSuggestedFormData(Activity $activity, User $user): array
    {
        $form = self::emptyFormData();
        $deptDefaults = $this->loadDepartmentJsDefaults($activity->getDepartmentId());

        $leader = $activity->getCreatedByUser() ?? $user;
        $profile = $leader->getProfile();
        if ($profile instanceof Profile) {
            $form['block1']['first_name'] = trim((string) ($profile->getFirstName() ?? ''));
            $form['block1']['last_name'] = trim((string) ($profile->getLastName() ?? ''));
            $form['block1']['email'] = trim($profile->getEmail());
        }

        if ($activity->getType() === 'camp') {
            $form['block2']['course_type'] = 'lager';
        }

        $participantCount = $activity->getParticipantCount();
        if ($participantCount !== null && $participantCount >= 1) {
            $form['block2']['participant_count'] = $participantCount;
        }

        $deliveryDate = $this->dateFromDateTime($activity->getPlanningStart());
        if ($deliveryDate !== null) {
            $form['block2']['delivery_date'] = $deliveryDate;
        }
        $returnDate = $this->dateFromDateTime($activity->getPlanningEnd());
        if ($returnDate !== null) {
            $form['block2']['return_date'] = $returnDate;
        }

        $form['block2']['coach_first_name'] = trim($deptDefaults['js.default_coach_first_name'] ?? '');
        $form['block2']['coach_last_name'] = trim($deptDefaults['js.default_coach_last_name'] ?? '');
        $form['block2']['coach_person_nr'] = trim($deptDefaults['js.default_coach_person_nr'] ?? '');
        $form['block2']['coach_email'] = trim($deptDefaults['js.default_coach_email'] ?? '');

        $deliveryAddress = $this->resolveDeliveryAddressForActivity($activity);
        if ($deliveryAddress instanceof Address) {
            $this->applyAddressToBlock3($form, $deliveryAddress, $profile, $leader);
        }

        return $form;
    }

    private function resolveDeliveryAddressForActivity(Activity $activity): ?Address
    {
        $venue = $activity->getVenueAddress();
        if ($venue instanceof Address) {
            $child = $this->findEventDeliveryChild($venue);
            if ($child instanceof Address) {
                return $child;
            }
        }

        $explicit = $activity->getJsDeliveryAddress();
        if ($explicit instanceof Address) {
            return $explicit;
        }

        return $venue;
    }

    private function findEventDeliveryChild(Address $venue): ?Address
    {
        if ($venue->getType() !== Address::TYPE_EVENT) {
            return null;
        }

        /** @var Address|null $child */
        $child = $this->entityManager->getRepository(Address::class)->findOneBy([
            'parentId' => $venue->getId(),
            'type' => Address::TYPE_EVENT_DELIVERY,
        ]);

        return ($child instanceof Address && !$child->isDeleted()) ? $child : null;
    }

    /**
     * @param array<string, mixed> $form
     */
    private function applyAddressToBlock3(array &$form, Address $venue, ?Profile $profile, User $leader): void
    {
        $form['block3']['venue_name'] = trim((string) ($venue->getName() ?? $venue->getCompany() ?? ''));
        $form['block3']['address'] = trim($venue->getStreetLine());
        $form['block3']['postal_code'] = trim((string) ($venue->getPostalCode() ?? ''));
        $form['block3']['city'] = trim((string) ($venue->getCity() ?? ''));
        $form['block3']['canton'] = trim((string) ($venue->getCanton() ?? ''));
        $form['block3']['delivery_phone'] = trim((string) ($venue->getPhone() ?? ''));
        $form['block1']['phone'] = trim((string) ($venue->getPhone() ?? ''));
        if ($profile instanceof Profile) {
            $form['block3']['contact_first_name'] = trim((string) ($profile->getFirstName() ?? ''));
            $form['block3']['contact_last_name'] = trim((string) ($profile->getLastName() ?? ''));
            $leaderPhone = trim((string) ($venue->getPhone() ?? ''));
            if ($leaderPhone !== '') {
                $form['block3']['camp_leader_phone'] = $leaderPhone;
            }
        }
    }

    public function resolveDefaultDeliveryType(Activity $activity): string
    {
        $deptDefaults = $this->loadDepartmentJsDefaults($activity->getDepartmentId());
        $raw = trim($deptDefaults['js.default_delivery_type'] ?? ActivityJsOrder::DELIVERY_FRANKO);

        return $raw === ActivityJsOrder::DELIVERY_PICKUP_THUN
            ? ActivityJsOrder::DELIVERY_PICKUP_THUN
            : ActivityJsOrder::DELIVERY_FRANKO;
    }

    /**
     * Übernimmt Vorschläge in bestehende Order — nur leere Felder, respektiert user_overridden.
     */
    public function applyPrefill(ActivityJsOrder $order, Activity $activity, User $user, bool $onlyEmpty = true): void
    {
        $suggested = $this->buildSuggestedFormData($activity, $user);
        $current = $order->getFormData() ?? self::emptyFormData();
        $merged = $this->mergeFormData($current, $suggested, $onlyEmpty);
        $order->setFormData($merged);

        if ($order->getParticipantCount() === null) {
            $pc = $activity->getParticipantCount();
            if ($pc !== null && $pc >= 1) {
                $order->setParticipantCount($pc);
            } elseif (\is_int($merged['block2']['participant_count'] ?? null)) {
                $order->setParticipantCount($merged['block2']['participant_count']);
            }
        }

        if ($order->getDeliveryType() === ActivityJsOrder::DELIVERY_FRANKO && $activity->getVenueAddressId() !== null) {
            $order->setDeliveryType($this->resolveDefaultDeliveryType($activity));
        }
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $suggested
     *
     * @return array<string, mixed>
     */
    public function mergeFormData(array $current, array $suggested, bool $onlyEmpty): array
    {
        $base = self::emptyFormData();
        foreach (['block1', 'block2', 'block3'] as $blockKey) {
            $currentBlock = \is_array($current[$blockKey] ?? null) ? $current[$blockKey] : [];
            $suggestedBlock = \is_array($suggested[$blockKey] ?? null) ? $suggested[$blockKey] : [];
            $mergedBlock = array_merge($base[$blockKey], $currentBlock);

            $overridden = [];
            if (\is_array($mergedBlock['user_overridden'] ?? null)) {
                $overridden = array_values(array_unique(array_map('strval', $mergedBlock['user_overridden'])));
            }

            foreach ($suggestedBlock as $field => $value) {
                if ($field === 'user_overridden') {
                    continue;
                }
                if (\in_array($field, $overridden, true)) {
                    continue;
                }
                $existing = $mergedBlock[$field] ?? null;
                if (!$onlyEmpty || $this->isEmptyFieldValue($existing)) {
                    $mergedBlock[$field] = $value;
                }
            }

            $mergedBlock['user_overridden'] = $overridden;
            $base[$blockKey] = $mergedBlock;
        }

        return $base;
    }

    /** @param array<string, mixed>|null $incoming */
    public function normalizeIncomingFormData(?array $incoming): array
    {
        $base = self::emptyFormData();
        if (!\is_array($incoming)) {
            return $base;
        }

        foreach (['block1', 'block2', 'block3'] as $blockKey) {
            if (!\is_array($incoming[$blockKey] ?? null)) {
                continue;
            }
            foreach ($incoming[$blockKey] as $field => $value) {
                if ($field === 'user_overridden') {
                    $base[$blockKey]['user_overridden'] = \is_array($value)
                        ? array_values(array_unique(array_map('strval', $value)))
                        : [];
                    continue;
                }
                if (!\array_key_exists($field, $base[$blockKey])) {
                    continue;
                }
                if ($field === 'participant_count') {
                    $base[$blockKey][$field] = $this->normalizeParticipantCount($value);
                    continue;
                }
                $base[$blockKey][$field] = \is_string($value) ? $value : (string) ($value ?? '');
            }
        }

        return $base;
    }

    private function normalizeParticipantCount(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = \is_int($value) ? $value : (int) $value;

        return $n >= 1 ? $n : null;
    }

    private function isEmptyFieldValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (\is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }

    private function dateFromDateTime(?\DateTimeInterface $dt): ?string
    {
        if ($dt === null) {
            return null;
        }

        return $dt->format('Y-m-d');
    }
}
