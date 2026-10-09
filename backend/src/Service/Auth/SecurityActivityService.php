<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\AuditEvent;
use App\Entity\User;
use App\Util\UserAgentSummary;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persönliches Sicherheitsprotokoll aus dem bestehenden Audit-Log (audit_event).
 * Nur eine feste Allowlist von Aktionen und nur ausgewählte, unkritische Felder (nie Roh-Changes, nie Feldwerte).
 * Zugriff ausschliesslich für den betroffenen User (Controller); hier wird immer nach target_user_id gefiltert.
 */
class SecurityActivityService
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 50;

    /**
     * Zentrale Aufbewahrung: IP und User-Agent von Sicherheitsereignissen werden nach dieser Frist geleert
     * (app:security-activity:purge-context). Die Audit-Zeilen selbst bleiben unverändert erhalten.
     */
    public const CONTEXT_RETENTION_DAYS = 90;

    /** Änderungsfeld, das nur als Feldname erscheint (Profiländerungen: nie der Wert). */
    private const FIELD_NAMES = 'field_names';

    /** Aktion => Detailfeld aus den Changes (new, sonst old), das angezeigt werden darf. */
    private const ACTIONS = [
        'login_success' => null,
        'totp_enabled' => null,
        'totp_reset' => null,
        'totp_disabled' => null,
        'totp_locked' => null,
        'recovery_codes_regenerated' => null,
        'recovery_code_used' => null,
        'step_up_success' => null,
        'step_up_failure' => null,
        'trusted_device_created' => 'device',
        'trusted_device_used' => 'device',
        'trusted_device_revoked' => 'device',
        'session_revoked' => null,
        'other_sessions_revoked' => null,
        'user_password_changed' => null,
        'user_password_reset' => null,
        'profile_primary_email_changed' => null,
        'profile_email_added' => null,
        'profile_email_verified' => null,
        'profile_email_removed' => null,
        'profile_email_change_requested' => null,
        'profile_email_change_confirmed' => null,
        'profile_updated' => self::FIELD_NAMES,
        'external_identity_linked' => 'provider',
        'external_identity_unlinked' => 'provider',
        'membership_created' => 'role',
        'membership_role_changed' => 'role',
        'membership_removed' => 'role',
    ];

    /** Aktionen, bei denen die MFA-Quelle aus dem Ereignistyp selbst folgt. */
    private const IMPLIED_MFA_SOURCE = [
        'recovery_code_used' => 'recovery_code',
        'trusted_device_used' => 'trusted_device',
    ];

    private const AUTH_METHODS = ['password', 'google', 'midata', 'legacy'];
    private const MFA_SOURCES = ['totp', 'recovery_code', 'trusted_device'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public static function isSecurityAction(string $action): bool
    {
        return \array_key_exists($action, self::ACTIONS);
    }

    /** @return list<string> freigegebene Ereignistypen (Filterwerte) */
    public static function actions(): array
    {
        return array_keys(self::ACTIONS);
    }

    /**
     * Eine Seite des Protokolls, neueste zuerst. Cursor-Pagination (stabil bei neuen Ereignissen).
     * Optional gefiltert nach einem Ereignistyp (nur freigegebene) und Zeitraum (inklusive $to-Tag).
     *
     * @return array{events: list<array<string, mixed>>, next_cursor: string|null}
     *
     * @throws \InvalidArgumentException bei ungültigem Cursor oder nicht freigegebenem Ereignistyp
     */
    public function page(
        User $user,
        int $limit = self::DEFAULT_LIMIT,
        ?string $cursor = null,
        ?string $action = null,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
    ): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(AuditEvent::class, 'e')
            ->where('e.targetUserId = :user')
            ->andWhere('e.action IN (:actions)')
            ->setParameter('user', $user->getId())
            ->setParameter('actions', array_keys(self::ACTIONS));

        if ($action !== null) {
            if (!self::isSecurityAction($action)) {
                throw new \InvalidArgumentException('Ungültiger Ereignistyp');
            }
            $qb->andWhere('e.action = :action')->setParameter('action', $action);
        }
        if ($from !== null) {
            $qb->andWhere('e.createdAt >= :from')->setParameter('from', \DateTime::createFromInterface($from)->setTime(0, 0));
        }
        if ($to !== null) {
            $qb->andWhere('e.createdAt < :toExclusive')->setParameter('toExclusive', \DateTime::createFromInterface($to)->setTime(0, 0)->modify('+1 day'));
        }
        if ($cursor !== null) {
            $position = self::decodeCursor($cursor);
            $qb->andWhere('(e.createdAt < :cursorAt OR (e.createdAt = :cursorAt AND e.id < :cursorId))')
                ->setParameter('cursorAt', $position['at'])
                ->setParameter('cursorId', $position['id']);
        }

        /** @var list<AuditEvent> $events */
        $events = $qb->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        $hasMore = \count($events) > $limit;
        $events = \array_slice($events, 0, $limit);
        $actors = $this->actorNames($user, $events);

        $items = array_map(fn (AuditEvent $event): array => $this->serialize($event, $user, $actors), $events);
        $last = $events === [] ? null : $events[\count($events) - 1];

        return [
            'events' => $items,
            'next_cursor' => $hasMore && $last !== null ? self::encodeCursor($last) : null,
        ];
    }

    /**
     * Leert IP und User-Agent von Sicherheitsereignissen, die älter als die Aufbewahrungsfrist sind.
     *
     * @return int Anzahl bereinigter Ereignisse
     */
    public function countExpiredContext(?\DateTimeImmutable $now = null): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(AuditEvent::class, 'e')
            ->where('e.createdAt < :cutoff')
            ->andWhere('e.action IN (:actions)')
            ->andWhere('e.ipAddress IS NOT NULL OR e.userAgent IS NOT NULL')
            ->setParameter('cutoff', $this->cutoff($now))
            ->setParameter('actions', array_keys(self::ACTIONS))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function purgeExpiredContext(?\DateTimeImmutable $now = null): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->update(AuditEvent::class, 'e')
            ->set('e.ipAddress', 'NULL')
            ->set('e.userAgent', 'NULL')
            ->where('e.createdAt < :cutoff')
            ->andWhere('e.action IN (:actions)')
            ->andWhere('e.ipAddress IS NOT NULL OR e.userAgent IS NOT NULL')
            ->setParameter('cutoff', $this->cutoff($now))
            ->setParameter('actions', array_keys(self::ACTIONS))
            ->getQuery()
            ->execute();
    }

    private function cutoff(?\DateTimeImmutable $now): \DateTime
    {
        return \DateTime::createFromImmutable(($now ?? new \DateTimeImmutable())->modify('-' . self::CONTEXT_RETENTION_DAYS . ' days'));
    }

    /**
     * @param array<string, string> $actors actorUserId => Anzeigename
     *
     * @return array<string, mixed>
     */
    private function serialize(AuditEvent $event, User $user, array $actors): array
    {
        $action = $event->getAction();
        $changes = $event->getChanges();
        $field = self::ACTIONS[$action] ?? null;
        $detail = null;
        $changedFields = [];
        if ($field === self::FIELD_NAMES) {
            $changedFields = array_values(array_filter(
                array_map('strval', array_keys($changes)),
                static fn (string $name): bool => preg_match('/^[a-z0-9_]{1,40}$/', $name) === 1,
            ));
        } elseif ($field !== null) {
            $value = $changes[$field]['new'] ?? $changes[$field]['old'] ?? null;
            $detail = \is_string($value) && $value !== '' ? mb_substr($value, 0, 120) : null;
        }

        $authMethod = null;
        if ($action === 'login_success') {
            $value = $changes['auth_method']['new'] ?? null;
            $authMethod = \in_array($value, self::AUTH_METHODS, true) ? $value : null;
        }
        $mfaSource = self::IMPLIED_MFA_SOURCE[$action] ?? null;
        if ($action === 'login_success') {
            $value = $changes['mfa_source']['new'] ?? null;
            $mfaSource = \in_array($value, self::MFA_SOURCES, true) ? $value : null;
        }

        $actorId = $event->getActorUserId();
        // IP und Browser gehören dem Handelnden: bei Aktionen eines anderen Users (z. B. Admin) nie an den Betroffenen ausliefern.
        $foreignActor = $actorId !== null && $actorId !== $user->getId();
        $ua = UserAgentSummary::describe($foreignActor ? null : $event->getUserAgent());

        return [
            'id' => $event->getId(),
            'action' => $action,
            'created_at' => $event->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'detail' => $detail,
            'auth_method' => $authMethod,
            'mfa_source' => $mfaSource,
            'browser' => $ua['browser'],
            'os' => $ua['os'],
            'ip_address' => $foreignActor ? null : $event->getIpAddress(),
            'changed_fields' => $changedFields,
            // self | other (Name des handelnden Users, z. B. Admin) | null (kein handelnder User erfasst, z. B. System/Reset-Code)
            'actor' => $actorId === null ? null : ($actorId === $user->getId()
                ? ['type' => 'self', 'name' => null]
                : ['type' => 'other', 'name' => $actors[$actorId] ?? null]),
        ];
    }

    /**
     * @param list<AuditEvent> $events
     *
     * @return array<string, string>
     */
    private function actorNames(User $user, array $events): array
    {
        $ids = [];
        foreach ($events as $event) {
            $actorId = $event->getActorUserId();
            if ($actorId !== null && $actorId !== $user->getId()) {
                $ids[$actorId] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $names = [];
        /** @var list<User> $actors */
        $actors = $this->entityManager->getRepository(User::class)->findBy(['id' => array_keys($ids)]);
        foreach ($actors as $actor) {
            $profile = $actor->getProfile();
            $name = trim((string) ($profile?->getFirstName() . ' ' . $profile?->getLastName()));
            if ($name !== '') {
                $names[(string) $actor->getId()] = mb_substr($name, 0, 120);
            }
        }

        return $names;
    }

    private static function encodeCursor(AuditEvent $event): string
    {
        return rtrim(strtr(base64_encode($event->getCreatedAt()->format('Y-m-d H:i:s') . '|' . $event->getId()), '+/', '-_'), '=');
    }

    /** @return array{at: \DateTime, id: string} */
    private static function decodeCursor(string $cursor): array
    {
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($raw === false || preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\|([A-Za-z0-9]{1,13})$/', $raw, $m) !== 1) {
            throw new \InvalidArgumentException('Ungültiger Cursor');
        }
        $at = \DateTime::createFromFormat('Y-m-d H:i:s', $m[1]);

        return $at === false ? throw new \InvalidArgumentException('Ungültiger Cursor') : ['at' => $at, 'id' => $m[2]];
    }
}
