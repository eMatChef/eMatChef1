<?php

namespace App\Service;

use App\Entity\AuditEvent;
use App\Entity\Department;
use App\Entity\User;
use App\Service\Auth\SecurityActivityService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogger
{
    /** Änderungsfelder, deren Wert nie ins Audit-Log gehört (Teilstring-Treffer, Gross-/Kleinschreibung egal). */
    private const SENSITIVE_KEY_PATTERN = '/password|passwd|secret|token|hash|otp_|credential/i';

    /** Aktionen, bei denen nur die Namen der geänderten Felder, nie deren Werte gespeichert werden. */
    private const FIELD_NAMES_ONLY_ACTIONS = ['profile_updated'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?RequestStack $requestStack = null,
    ) {
    }

    public function log(
        string $entityType,
        string $entityId,
        string $action,
        ?User $actor = null,
        ?User $targetUser = null,
        ?Department $department = null,
        array $changes = []
    ): void {
        $event = new AuditEvent();
        $event->setId(IdGenerator::generate13Unique($this->entityManager, AuditEvent::class, 'ae'));
        $event->setEntityType($entityType);
        $event->setEntityId($entityId);
        $event->setAction($action);
        $event->setActorUserId($actor?->getId());
        $event->setTargetUserId($targetUser?->getId());
        $event->setDepartmentId($department?->getId());
        $event->setChanges(self::redact($action, $changes));

        if (SecurityActivityService::isSecurityAction($action)) {
            $this->applyRequestContext($event);
        }

        $this->entityManager->persist($event);
    }

    public static function buildMembershipEntityId(string $userId, string $departmentId): string
    {
        return $userId . ':' . $departmentId;
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    public static function redact(string $action, array $changes): array
    {
        $result = [];
        foreach ($changes as $field => $change) {
            $field = (string) $field;
            if (\in_array($action, self::FIELD_NAMES_ONLY_ACTIONS, true)) {
                $result[$field] = ['changed' => true];
            } elseif (preg_match(self::SENSITIVE_KEY_PATTERN, $field) === 1) {
                $result[$field] = ['old' => null, 'new' => '[redacted]'];
            } else {
                $result[$field] = $change;
            }
        }

        return $result;
    }

    /** IP kommt ausschliesslich von Request::getClientIp() (beachtet nur konfigurierte Trusted Proxies). */
    private function applyRequestContext(AuditEvent $event): void
    {
        $request = $this->requestStack?->getCurrentRequest();
        if ($request === null) {
            return;
        }
        $ip = $request->getClientIp();
        if (\is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            $event->setIpAddress($ip);
        }
        $userAgent = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $request->headers->get('User-Agent')));
        if ($userAgent !== '') {
            $event->setUserAgent(mb_substr($userAgent, 0, 255));
        }
    }
}
