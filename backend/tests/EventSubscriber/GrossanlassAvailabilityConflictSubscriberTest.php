<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\GrossanlassAvailabilityConflictSubscriber;
use App\Service\Grossanlass\GrossanlassAvailabilityConflict;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class GrossanlassAvailabilityConflictSubscriberTest extends TestCase
{
    private function event(\Throwable $e): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(KernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $e);
    }

    public function testConflictBecomesHttp409WithReadablePayload(): void
    {
        $event = $this->event(new GrossanlassAvailabilityConflict('Festbank: nicht genug Menge', GrossanlassAvailabilityConflict::KIND_OVERBOOKED, ['capacity' => 10]));
        (new GrossanlassAvailabilityConflictSubscriber())->onException($event);

        self::assertSame(409, $event->getResponse()?->getStatusCode());
        $body = json_decode((string) $event->getResponse()?->getContent(), true);
        self::assertSame('availability_conflict', $body['code']);
        self::assertSame('Festbank: nicht genug Menge', $body['error']);
        self::assertSame(['kind' => 'overbooked', 'capacity' => 10], $body['conflict']);
    }

    public function testOtherExceptionsAreLeftAlone(): void
    {
        $event = $this->event(new \RuntimeException('x'));
        (new GrossanlassAvailabilityConflictSubscriber())->onException($event);

        self::assertNull($event->getResponse());
    }

    public function testConflictIsNotARuntimeExceptionSoControllersDoNotMapItTo403(): void
    {
        self::assertNotInstanceOf(\RuntimeException::class, new GrossanlassAvailabilityConflict('x', 'overbooked', []));
    }
}
