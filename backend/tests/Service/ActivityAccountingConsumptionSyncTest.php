<?php

declare(strict_types=1);

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * D5 — Verbrauch-Buchhaltung (MW-Auftrag) erst ab Retour, nicht bei at_event.
 */
final class ActivityAccountingConsumptionSyncTest extends TestCase
{
    public function testConsumptionFollowUpStatusesExcludeAtEvent(): void
    {
        $method = $this->consumptionFollowUpMethod();
        $filename = $method->getFileName();
        self::assertIsString($filename);

        $lines = file($filename);
        self::assertIsArray($lines);

        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        self::assertStringContainsString('Activity::STATUS_RETURNED', $body);
        self::assertStringContainsString('Activity::STATUS_STORING', $body);
        self::assertStringContainsString('Activity::STATUS_COMPLETED', $body);
        self::assertStringNotContainsString('Activity::STATUS_AT_EVENT', $body);
    }

    private function consumptionFollowUpMethod(): ReflectionMethod
    {
        $ref = new ReflectionClass(\App\Service\ActivityAccountingCostService::class);
        $method = $ref->getMethod('syncConsumptionFollowUp');
        $method->setAccessible(true);

        return $method;
    }
}
