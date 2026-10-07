<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\AuditEvent;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Letzte sicherheitsrelevante Ereignisse des eigenen Kontos aus dem bestehenden Audit-Log.
 * Nur eine feste Allowlist von Aktionen und nur ausgewählte, unkritische Detailfelder (nie Roh-Changes).
 */
class SecurityActivityService
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 50;

    /** Aktion => Detailfeld aus den Changes (new), das angezeigt werden darf. */
    private const ACTIONS = [
        'login_success' => 'auth_method',
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
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @return list<array{action: string, created_at: string, detail: string|null}>
     */
    public function recent(User $user, int $limit = self::DEFAULT_LIMIT): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        /** @var list<AuditEvent> $events */
        $events = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(AuditEvent::class, 'e')
            ->where('e.targetUserId = :user')
            ->andWhere('e.action IN (:actions)')
            ->setParameter('user', $user->getId())
            ->setParameter('actions', array_keys(self::ACTIONS))
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($events as $event) {
            $field = self::ACTIONS[$event->getAction()] ?? null;
            $detail = null;
            if ($field !== null) {
                $value = $event->getChanges()[$field]['new'] ?? null;
                $detail = \is_string($value) && $value !== '' ? mb_substr($value, 0, 120) : null;
            }
            $items[] = [
                'action' => $event->getAction(),
                'created_at' => $event->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'detail' => $detail,
            ];
        }

        return $items;
    }
}
