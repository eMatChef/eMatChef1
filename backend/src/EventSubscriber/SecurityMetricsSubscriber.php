<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Security\SecurityAlertingStore;
use App\Service\Security\SecurityMetricsStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityMetricsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SecurityMetricsStore $metrics,
        private readonly SecurityAlertingStore $alerting,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $status = $event->getResponse()->getStatusCode();
        if ($status === 401 || $status === 429 || $status >= 500) {
            $this->metrics->record($path, $status);
        }
        $this->alerting->recordLoginFailure($request, $status);
    }
}

