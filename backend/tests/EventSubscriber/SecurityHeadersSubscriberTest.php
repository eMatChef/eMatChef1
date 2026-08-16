<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersSubscriberTest extends TestCase
{
    public function testAddsNoindexRobotsTag(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent(
            $kernel,
            Request::create('/api/health'),
            HttpKernelInterface::MAIN_REQUEST,
            new Response('ok'),
        );

        (new SecurityHeadersSubscriber())->onKernelResponse($event);

        self::assertSame('noindex, nofollow', $event->getResponse()->headers->get('X-Robots-Tag'));
    }
}
