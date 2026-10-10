<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\Grossanlass\GrossanlassAccessRoles;
use App\Service\Grossanlass\GrossanlassAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Serverseitige Sperre eines Grossanlasses vor der Freigabe der Ersteinrichtung.
 *
 * Gilt für alle Grossanlass-Endpunkte `/api/departments/{id}/grossanlass/…` eines Departments mit offener Einrichtung:
 * - MW, Co-MW und OK-Leitung erreichen nur die Einrichtung: `setup`, Stammdaten (`planung`), Ressorts und deren Mitglieder
 *   (`groups`, `groups/{id}/members`).
 * - Alle anderen Mitglieder (Bereichsleitung, Logistik, Komm/Spon, Helfer …) erreichen noch nichts.
 * Globale Admins im Scope des Departments und Nicht-Mitglieder bleiben unberührt (ihre Rechte ändern sich nicht, die
 * bestehenden Prüfungen der Endpunkte gelten weiter). Gast-Endpunkte (`…/{gast-department}/grossanlass/hosts/…`) sind nicht
 * betroffen, weil die Department-ID dort ein normales Department ist.
 */
final class GrossanlassSetupGateSubscriber implements EventSubscriberInterface
{
    private const PATTERN = '#^/api/departments/([^/]+)/grossanlass(?:/(.*))?$#';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokens,
        private readonly GrossanlassAccessService $access,
        private readonly AdminCapabilityChecker $adminCapabilities,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Nach der Firewall (Priorität 8): der Benutzer ist bekannt.
        return [KernelEvents::REQUEST => ['onRequest', 0]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !preg_match(self::PATTERN, $event->getRequest()->getPathInfo(), $m)) {
            return;
        }
        $user = $this->tokens->getToken()?->getUser();
        if (!$user instanceof User) {
            return;
        }
        $department = $this->entityManager->getRepository(Department::class)->find($m[1]);
        if (!$department instanceof Department || !$department->isGrossanlass()) {
            return;
        }
        $config = $department->getGrossanlassConfig();
        if ($config === null || $config->isSetupReleased()) {
            return;
        }
        if ($this->adminCapabilities->canAdministerDepartment($user, $department->getId())) {
            return;
        }
        $role = $this->access->membershipRole($user, $department);
        if ($role === null) {
            return; // kein Mitglied: die Endpunkte lehnen selbst ab
        }
        if (GrossanlassAccessRoles::canSetup($role) && self::isSetupRoute($event->getRequest()->getMethod(), '/' . ($m[2] ?? ''))) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => GrossanlassAccessRoles::canSetup($role)
                ? 'Diese Funktion ist erst nach der Freigabe der Ersteinrichtung verfügbar.'
                : 'Der Grossanlass ist noch in der Einrichtung und für deine Rolle noch nicht freigegeben.',
            'code' => 'grossanlass_setup_pending',
        ], 403));
    }

    /** Einrichtungs-Endpunkte, die vor der Freigabe erlaubt sind (relativ zu `/grossanlass`). */
    public static function isSetupRoute(string $method, string $path): bool
    {
        $path = rtrim($path, '/');
        if ($path === '/setup' || $path === '/setup/release') {
            return true;
        }
        if ($path === '/planung') {
            return \in_array($method, ['GET', 'PATCH'], true);
        }
        if ($path === '/groups') {
            return \in_array($method, ['GET', 'POST'], true);
        }
        if (preg_match('#^/groups/[^/]+$#', $path)) {
            return \in_array($method, ['PUT', 'DELETE'], true);
        }

        return (bool) preg_match('#^/groups/[^/]+/members(?:/[^/]+)?$#', $path);
    }
}
