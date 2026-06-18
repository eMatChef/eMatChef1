<?php

namespace App\Service\Display;

use App\Entity\Department;
use App\Entity\DepartmentDisplayScreen;
use App\Entity\Membership;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DepartmentDisplayScreenService
{
    /** @var list<string> */
    public const DISPLAY_ACTIVITY_TYPES = ['activity', 'camp', 'event', 'external'];

    /** @var list<string> */
    public const DISPLAY_ACTIVITY_STATUSES = ['draft', 'submitted', 'approved', 'packing', 'packed', 'at_event', 'returned'];

    /** @var list<string> */
    public const DEFAULT_DISPLAY_ACTIVITY_STATUSES = ['submitted', 'approved', 'packing', 'packed', 'at_event'];

    /** @var list<string> */
    public const DISPLAY_WORKSHOP_STATUSES = [
        'triage',
        'planning',
        'ordered',
        'ready',
        'in_progress',
        'awaiting_quote',
        'completed',
        'cancelled',
    ];

    /** @var list<string> */
    public const DEFAULT_DISPLAY_WORKSHOP_STATUSES = ['triage', 'planning', 'in_progress', 'awaiting_quote'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DisplayAccessCodeGenerator $accessCodeGenerator,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $appFrontendUrl,
    ) {
    }

    public function canManageDepartment(User $user, string $departmentId): bool
    {
        if (\in_array('ROLE_SUPERADMIN', $user->getRoles(), true)) {
            return true;
        }

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $departmentId,
        ]);
        if ($membership === null) {
            return false;
        }

        $role = strtolower((string) $membership->getRole());
        $managerRoles = ['mw', 'matwart', 'dc', 'depchef', 'sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'];

        return \in_array($role, $managerRoles, true);
    }

    /**
     * @return list<DepartmentDisplayScreen>
     */
    public function listForDepartment(string $departmentId, bool $includeRevoked = false): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s')
            ->from(DepartmentDisplayScreen::class, 's')
            ->where('s.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('s.createdAt', 'DESC');

        if (!$includeRevoked) {
            $qb->andWhere('s.revokedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{screen: DepartmentDisplayScreen, access_code: string}
     */
    public function create(string $departmentId, string $name, ?User $createdBy): array
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department) {
            throw new \InvalidArgumentException('Department nicht gefunden.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Name ist erforderlich.');
        }

        $accessCode = $this->accessCodeGenerator->generate(8);
        $screen = new DepartmentDisplayScreen();
        $screen->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, DepartmentDisplayScreen::class, 'dsp'));
        $screen->setDepartmentId($departmentId);
        $screen->setName($name);
        $screen->setPublicId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, DepartmentDisplayScreen::class, 'dsi', 'publicId'));
        $screen->setAccessCodeHash($this->hashAccessCode($accessCode));
        $screen->setAccessCodeHint(substr($accessCode, -2));
        $screen->setCreatedByUserId($createdBy?->getId());
        $screen->setActivityTypes(self::DISPLAY_ACTIVITY_TYPES);
        $screen->setActivityStatuses(self::DEFAULT_DISPLAY_ACTIVITY_STATUSES);
        $screen->setWorkshopStatuses(self::DEFAULT_DISPLAY_WORKSHOP_STATUSES);
        $screen->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($screen);
        $this->entityManager->flush();

        return ['screen' => $screen, 'access_code' => $accessCode];
    }

    /**
     * @param array{
     *   subtitle_text?: ?string,
     *   show_activities?: bool,
     *   show_workshop?: bool,
     *   show_statistics?: bool,
     *   activity_types?: mixed,
     *   activity_statuses?: mixed,
     *   workshop_statuses?: mixed,
     * } $data
     */
    public function updateSettings(DepartmentDisplayScreen $screen, array $data): DepartmentDisplayScreen
    {
        if ($screen->isRevoked()) {
            throw new \InvalidArgumentException('Screen ist widerrufen.');
        }

        if (\array_key_exists('subtitle_text', $data)) {
            $raw = $data['subtitle_text'];
            if ($raw === null || trim((string) $raw) === '') {
                $screen->setSubtitleText(null);
            } else {
                $text = trim((string) $raw);
                if (mb_strlen($text) > 500) {
                    throw new \InvalidArgumentException('Untertitel darf maximal 500 Zeichen lang sein.');
                }
                $screen->setSubtitleText($text);
            }
        }

        $showActivities = \array_key_exists('show_activities', $data)
            ? (bool) $data['show_activities']
            : $screen->isShowActivities();
        $showWorkshop = \array_key_exists('show_workshop', $data)
            ? (bool) $data['show_workshop']
            : $screen->isShowWorkshop();
        $showStatistics = \array_key_exists('show_statistics', $data)
            ? (bool) $data['show_statistics']
            : $screen->isShowStatistics();

        $activityTypes = \array_key_exists('activity_types', $data)
            ? $this->normalizeActivityTypes($data['activity_types'])
            : $this->normalizeActivityTypes($screen->getActivityTypes());
        $activityStatuses = \array_key_exists('activity_statuses', $data)
            ? $this->normalizeActivityStatuses($data['activity_statuses'])
            : $this->normalizeActivityStatuses($screen->getActivityStatuses());
        $workshopStatuses = \array_key_exists('workshop_statuses', $data)
            ? $this->normalizeWorkshopStatuses($data['workshop_statuses'])
            : $this->normalizeWorkshopStatuses($screen->getWorkshopStatuses());

        if (!$showActivities && !$showWorkshop && !$showStatistics) {
            throw new \InvalidArgumentException('Mindestens ein Bereich (Anlässe, Werkstatt oder Statistik) muss aktiv sein.');
        }

        if ($showActivities && $activityTypes === []) {
            throw new \InvalidArgumentException('Mindestens ein Anlass-Typ muss ausgewählt sein.');
        }

        if ($showActivities && $activityStatuses === []) {
            throw new \InvalidArgumentException('Mindestens ein Anlass-Status muss ausgewählt sein.');
        }

        if ($showWorkshop && $workshopStatuses === []) {
            throw new \InvalidArgumentException('Mindestens eine Werkstatt-Phase muss ausgewählt sein.');
        }

        $screen->setShowActivities($showActivities);
        $screen->setShowWorkshop($showWorkshop);
        $screen->setShowStatistics($showStatistics);
        $screen->setActivityTypes($activityTypes);
        $screen->setActivityStatuses($activityStatuses);
        $screen->setWorkshopStatuses($workshopStatuses);
        $screen->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return $screen;
    }

    /**
     * @return list<string>
     */
    public function normalizeActivityTypes(mixed $raw): array
    {
        return $this->normalizeAgainstAllowed($raw, self::DISPLAY_ACTIVITY_TYPES, self::DISPLAY_ACTIVITY_TYPES);
    }

    /**
     * @return list<string>
     */
    public function normalizeActivityStatuses(mixed $raw): array
    {
        return $this->normalizeAgainstAllowed($raw, self::DISPLAY_ACTIVITY_STATUSES, self::DEFAULT_DISPLAY_ACTIVITY_STATUSES);
    }

    /**
     * @return list<string>
     */
    public function normalizeWorkshopStatuses(mixed $raw): array
    {
        return $this->normalizeAgainstAllowed($raw, self::DISPLAY_WORKSHOP_STATUSES, self::DEFAULT_DISPLAY_WORKSHOP_STATUSES);
    }

    /**
     * @param list<string> $allowed
     * @param list<string> $fallback
     *
     * @return list<string>
     */
    private function normalizeAgainstAllowed(mixed $raw, array $allowed, array $fallback): array
    {
        if (!\is_array($raw)) {
            return $fallback;
        }

        $valid = [];
        foreach ($raw as $item) {
            $value = strtolower(trim((string) $item));
            if ($value !== '' && \in_array($value, $allowed, true) && !\in_array($value, $valid, true)) {
                $valid[] = $value;
            }
        }

        return $valid;
    }

    /**
     * @return array{screen: DepartmentDisplayScreen, access_code: string}
     */
    public function rotateAccessCode(DepartmentDisplayScreen $screen): array
    {
        if ($screen->isRevoked()) {
            throw new \InvalidArgumentException('Screen ist widerrufen.');
        }

        $accessCode = $this->accessCodeGenerator->generate(8);
        $screen->setAccessCodeHash($this->hashAccessCode($accessCode));
        $screen->setAccessCodeHint(substr($accessCode, -2));
        $screen->incrementCodeVersion();
        $screen->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return ['screen' => $screen, 'access_code' => $accessCode];
    }

    public function revoke(DepartmentDisplayScreen $screen): void
    {
        if ($screen->isRevoked()) {
            throw new \InvalidArgumentException('Screen ist bereits widerrufen.');
        }

        $screen->setRevokedAt(new \DateTime());
        $screen->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * @return array{screen: DepartmentDisplayScreen, access_code: string}
     */
    public function reactivate(DepartmentDisplayScreen $screen): array
    {
        if (!$screen->isRevoked()) {
            throw new \InvalidArgumentException('Screen ist nicht widerrufen.');
        }

        $accessCode = $this->accessCodeGenerator->generate(8);
        $screen->setRevokedAt(null);
        $screen->setAccessCodeHash($this->hashAccessCode($accessCode));
        $screen->setAccessCodeHint(substr($accessCode, -2));
        $screen->incrementCodeVersion();
        $screen->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return ['screen' => $screen, 'access_code' => $accessCode];
    }

    public function verifyAccessCode(DepartmentDisplayScreen $screen, string $code): bool
    {
        if ($screen->isRevoked()) {
            return false;
        }

        $normalized = $this->accessCodeGenerator->normalize($code);
        if (!$this->accessCodeGenerator->isValidFormat($normalized)) {
            return false;
        }

        return password_verify($normalized, $screen->getAccessCodeHash());
    }

    public function touchLastUsed(DepartmentDisplayScreen $screen): void
    {
        $screen->setLastUsedAt(new \DateTime());
        $screen->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    public function findByPublicId(string $publicId): ?DepartmentDisplayScreen
    {
        return $this->entityManager->getRepository(DepartmentDisplayScreen::class)->findOneBy([
            'publicId' => $publicId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForSettings(DepartmentDisplayScreen $screen): array
    {
        return [
            'id' => $screen->getId(),
            'department_id' => $screen->getDepartmentId(),
            'name' => $screen->getName(),
            'public_id' => $screen->getPublicId(),
            'display_url' => $this->buildDisplayUrl($screen->getPublicId()),
            'subtitle_text' => $screen->getSubtitleText(),
            'show_activities' => $screen->isShowActivities(),
            'show_workshop' => $screen->isShowWorkshop(),
            'activity_types' => $this->normalizeActivityTypes($screen->getActivityTypes()),
            'activity_statuses' => $this->normalizeActivityStatuses($screen->getActivityStatuses()),
            'workshop_statuses' => $this->normalizeWorkshopStatuses($screen->getWorkshopStatuses()),
            'show_statistics' => $screen->isShowStatistics(),
            'access_code_hint' => $screen->getAccessCodeHint(),
            'code_version' => $screen->getCodeVersion(),
            'revoked_at' => $screen->getRevokedAt()?->format('c'),
            'last_used_at' => $screen->getLastUsedAt()?->format('c'),
            'created_at' => $screen->getCreatedAt()->format('c'),
            'updated_at' => $screen->getUpdatedAt()->format('c'),
        ];
    }

    public function buildDisplayUrl(string $publicId): string
    {
        $origin = trim($this->appFrontendUrl);
        if ($origin === '') {
            return '/display/' . rawurlencode($publicId);
        }

        return rtrim($origin, '/') . '/display/' . rawurlencode($publicId);
    }

    private function hashAccessCode(string $code): string
    {
        $normalized = $this->accessCodeGenerator->normalize($code);

        return password_hash($normalized, PASSWORD_DEFAULT);
    }
}
