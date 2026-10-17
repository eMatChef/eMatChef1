<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Grossanlass\GrossanlassAvailabilityConflict;
use App\Service\Grossanlass\GrossanlassStaffingConflict;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Überbuchungs-/Verfügbarkeitskonflikte, die ein Controller nicht selbst abfängt (z. B. Bestellung ändert die Chargenmenge),
 * werden als HTTP 409 mit dem gleichen Aufbau gemeldet wie bei den Buchungs-Endpunkten. Dasselbe gilt für Konflikte der
 * Helferzuteilung ({@see GrossanlassStaffingConflict}, `code: staffing_conflict`).
 */
final class GrossanlassAvailabilityConflictSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof GrossanlassAvailabilityConflict || $exception instanceof GrossanlassStaffingConflict) {
            $event->setResponse(new JsonResponse($exception->toPayload(), 409));
        }
    }
}
