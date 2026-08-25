<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassGmailRouting;
use PHPUnit\Framework\TestCase;

class GrossanlassGmailRoutingTest extends TestCase
{
    public function testDefaultTreeUsesDepartmentName(): void
    {
        $names = GrossanlassGmailRouting::labelNames(
            GrossanlassGmailRouting::defaults(),
            'PFF 2027',
            ['Fahrzeuge'],
        );

        self::assertSame([
            'PFF 2027',
            'PFF 2027/Firmenanfragen',
            'PFF 2027/Status/Wartet auf Antwort',
            'PFF 2027/Firmenanfragen/Fahrzeuge',
        ], $names);
    }

    public function testCustomRootAndDisabledPackage(): void
    {
        $routing = GrossanlassGmailRouting::normalize([
            'label_root' => 'PFF27',
            'label_inquiries' => 'Partner',
            'label_waiting' => '',
            'label_by_package' => false,
            'extra_labels' => "OK\nPFF27/Archiv",
            'reference_prefix' => 'PFF-',
        ]);

        $names = GrossanlassGmailRouting::labelNames($routing, 'Ignored', ['Holz']);
        self::assertSame(['PFF27', 'PFF27/Partner', 'OK', 'PFF27/Archiv'], $names);
        self::assertSame('PFF-iq12ab34cd56', GrossanlassGmailRouting::displayReference($routing['reference_prefix'], 'iq12ab34cd56'));
    }

    public function testSlashesInRootBecomeDashes(): void
    {
        $routing = GrossanlassGmailRouting::normalize(['label_root' => 'PFF/27']);
        self::assertSame('PFF-27', $routing['label_root']);
    }

    public function testRepliedStatusUsesAnswerLabel(): void
    {
        $names = GrossanlassGmailRouting::labelNames(
            GrossanlassGmailRouting::defaults(),
            'PFF 2027',
            [],
            GrossanlassGmailRouting::STATUS_REPLIED,
        );
        self::assertContains('PFF 2027/Status/Antwort erhalten', $names);
        self::assertNotContains('PFF 2027/Status/Wartet auf Antwort', $names);
    }
}
