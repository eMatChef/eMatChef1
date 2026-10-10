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

    /** @var list<string> Rollen, die Infoscreens verwalten (und koppeln) dürfen. */
    public const MANAGER_ROLES = ['mw', 'matwart', 'dc', 'depchef', 'sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DisplayAccessCodeGenerator $accessCodeGenerator,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $appFrontendUrl,
        #[Autowire('%env(default::APP_DISPLAY_URL)%')] private ?string $appDisplayUrl = null,
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

        return \in_array($role, self::MANAGER_ROLES, true);
    }

    /**
     * Aktive Screens aller Departments (inkl. Grossanlässe), die der User verwalten darf.
     *
     * @return list<array{screen: DepartmentDisplayScreen, department: Department}>
     */
    public function listManageableScreens(User $user): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s')
            ->from(DepartmentDisplayScreen::class, 's')
            ->innerJoin(Department::class, 'd', 'WITH', 'd.id = s.departmentId')
            ->where('s.revokedAt IS NULL')
            ->orderBy('d.name', 'ASC')
            ->addOrderBy('s.name', 'ASC');

        if (!\in_array('ROLE_SUPERADMIN', $user->getRoles(), true)) {
            $memberships = $this->entityManager->getRepository(Membership::class)->findBy(['userId' => $user->getId()]);
            $departmentIds = [];
            foreach ($memberships as $membership) {
                if (\in_array(strtolower((string) $membership->getRole()), self::MANAGER_ROLES, true)) {
                    $departmentIds[] = $membership->getDepartmentId();
                }
            }
            if ($departmentIds === []) {
                return [];
            }
            $qb->andWhere('s.departmentId IN (:departmentIds)')->setParameter('departmentIds', array_values(array_unique($departmentIds)));
        }

        $rows = [];
        foreach ($qb->getQuery()->getResult() as $entity) {
            if ($entity instanceof DepartmentDisplayScreen) {
                $department = $this->entityManager->getRepository(Department::class)->find($entity->getDepartmentId());
                if ($department instanceof Department) {
                    $rows[] = ['screen' => $entity, 'department' => $department];
                }
            }
        }

        return $rows;
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
        $screen->setId($this->generateUnusedId(DepartmentDisplayScreen::class, 'dsp', 'id'));
        $screen->setDepartmentId($departmentId);
        $screen->setName($name);
        $screen->setPublicId($this->generateUnusedId(DepartmentDisplayScreen::class, 'dsi', 'publicId'));
        $screen->setAccessCodeHash($this->hashAccessCode($accessCode));
        $screen->setAccessCodeHint(substr($accessCode, -2));
        $screen->setCreatedByUserId($createdBy?->getId());
        $screen->setActivityTypes(self::DISPLAY_ACTIVITY_TYPES);
        $screen->setActivityStatuses(self::DEFAULT_DISPLAY_ACTIVITY_STATUSES);
        $screen->setWorkshopStatuses(self::DEFAULT_DISPLAY_WORKSHOP_STATUSES);
        if ($department->isGrossanlass()) {
            // Grossanlass: keine Department-Anzeigebereiche; Inhalte folgen später über GA-Vorlagen.
            $screen->setShowActivities(false);
            $screen->setShowWorkshop(false);
            $screen->setShowStatistics(false);
        }
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

        $department = $this->entityManager->getRepository(Department::class)->find($screen->getDepartmentId());
        if ($department?->isGrossanlass()) {
            foreach (['show_activities', 'show_workshop', 'show_statistics', 'activity_types', 'activity_statuses', 'workshop_statuses'] as $departmentOnly) {
                if (\array_key_exists($departmentOnly, $data)) {
                    throw new \InvalidArgumentException('Für Grossanlässe gibt es keine Department-Anzeigebereiche.');
                }
            }
        }

        if (\array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '' || mb_strlen($name) > 120) {
                throw new \InvalidArgumentException('Name ist erforderlich (maximal 120 Zeichen).');
            }
            $screen->setName($name);
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

        $isGrossanlass = $department?->isGrossanlass() === true;
        if (!$isGrossanlass && !$showActivities && !$showWorkshop && !$showStatistics) {
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
     * Neue ID, die weder vergeben ist noch je vergeben war (gelöschte Screens hinterlassen einen Eintrag in display_deleted_id).
     */
    public function generateUnusedId(string $entityClass, string $prefix, string $field): string
    {
        $connection = $this->entityManager->getConnection();
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $id = IdGenerator::generate12UniqueWithPrefix($this->entityManager, $entityClass, $prefix, $field);
            if ($connection->fetchOne('SELECT 1 FROM display_deleted_id WHERE id = ?', [$id]) === false) {
                return $id;
            }
        }

        throw new \RuntimeException('Konnte keine unbenutzte Infoscreen-ID erzeugen.');
    }

    /**
     * Endgültiges Löschen: nur bei widerrufenem Screen. Zugehörige Kopplungsanfragen werden gezielt entfernt,
     * die IDs bleiben als Tombstone gesperrt (keine Wiederverwendung).
     */
    public function deletePermanently(DepartmentDisplayScreen $screen, ?User $deletedBy): void
    {
        if (!$screen->isRevoked()) {
            throw new \InvalidArgumentException('Nur widerrufene Screens können endgültig gelöscht werden.');
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $now = (new \DateTime())->format('Y-m-d H:i:s');
            foreach ([[$screen->getId(), 'screen'], [$screen->getPublicId(), 'public']] as [$id, $kind]) {
                $connection->executeStatement(
                    'INSERT INTO display_deleted_id (id, kind, deleted_at, deleted_by_user_id) VALUES (?, ?, ?, ?) ON CONFLICT (id) DO NOTHING',
                    [$id, $kind, $now, $deletedBy?->getId()],
                );
            }
            $connection->executeStatement('DELETE FROM display_pairing_request WHERE screen_id = ?', [$screen->getId()]);
            // Alle Geräteberechtigungen des Screens erlöschen; auch die Geräte-IDs bleiben gesperrt.
            $connection->executeStatement(
                "INSERT INTO display_deleted_id (id, kind, deleted_at, deleted_by_user_id) SELECT id, 'device', ?, ? FROM department_display_device WHERE screen_id = ? ON CONFLICT (id) DO NOTHING",
                [$now, $deletedBy?->getId(), $screen->getId()],
            );
            $connection->executeStatement('DELETE FROM department_display_device WHERE screen_id = ?', [$screen->getId()]);
            $this->entityManager->remove($screen);
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @param list<string>|null $onlyVia null = alle Geräte
     */
    private function revokeDevices(string $screenId, ?array $onlyVia): void
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $connection = $this->entityManager->getConnection();
        if ($onlyVia === null) {
            $connection->executeStatement('UPDATE department_display_device SET revoked_at = ?, updated_at = ? WHERE screen_id = ? AND revoked_at IS NULL', [$now, $now, $screenId]);
        } else {
            $connection->executeStatement(
                'UPDATE department_display_device SET revoked_at = ?, updated_at = ? WHERE screen_id = ? AND revoked_at IS NULL AND created_via IN (?)',
                [$now, $now, $screenId, $onlyVia],
                [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ArrayParameterType::STRING],
            );
        }
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
        // Geräte, die aus dem Zugangscode entstanden sind (manuell/migriert), verlieren ihre Freigabe; gekoppelte bleiben.
        $this->revokeDevices($screen->getId(), ['manual', 'migrated']);

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
        // Alle Geräte effektiv sperren; nach Reaktivierung ist eine neue Kopplung nötig.
        $this->revokeDevices($screen->getId(), null);
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
        // Eigene Infoscreen-Domain (display.), sonst App-Origin.
        $origin = trim((string) $this->appDisplayUrl) !== '' ? trim((string) $this->appDisplayUrl) : trim($this->appFrontendUrl);
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
