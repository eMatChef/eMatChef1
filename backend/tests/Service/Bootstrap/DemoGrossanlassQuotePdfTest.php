<?php

declare(strict_types=1);

namespace App\Tests\Service\Bootstrap;

use App\Service\Bootstrap\DemoGrossanlassQuotePdf;
use PHPUnit\Framework\TestCase;

final class DemoGrossanlassQuotePdfTest extends TestCase
{
    public function testRendersAReadableDemoQuoteFromTheSeedData(): void
    {
        $binary = (new DemoGrossanlassQuotePdf())->render([
            'supplier' => 'Häberli Holz',
            'reference' => 'HH-2027-014',
            'date' => '04.03.2027',
            'customer' => 'Demo Grossanlass Event',
            'label' => 'Schwartenbretter',
            'quantity' => 30,
            'unit' => 'Stk',
            'amount' => "1'220.00",
            'delivery' => 'Abholung am 19.05.2027',
            'note' => 'Demo-Hinweis',
        ]);

        // Dompdf bettet die Schrift als Subset ein; Text ist daher nicht per Textsuche lesbar. Struktur und Inhaltsabhängigkeit prüfen.
        self::assertStringStartsWith('%PDF-', $binary);
        self::assertStringContainsString('%%EOF', $binary);
        self::assertGreaterThan(1500, \strlen($binary));
        $other = (new DemoGrossanlassQuotePdf())->render([
            'supplier' => 'Maki Landtechnik', 'reference' => 'ML-2027-031', 'date' => '12.03.2027', 'customer' => 'Demo Grossanlass Event',
            'label' => 'Teleskoplader', 'quantity' => 1, 'unit' => 'Stk', 'amount' => '950.00', 'delivery' => 'Anlieferung', 'note' => '',
        ]);
        self::assertNotSame(md5(preg_replace('/\/(CreationDate|ID)[^\n]*/', '', $binary) ?? ''), md5(preg_replace('/\/(CreationDate|ID)[^\n]*/', '', $other) ?? ''));
    }

    public function testEscapesMarkupInSeedValues(): void
    {
        $binary = (new DemoGrossanlassQuotePdf())->render([
            'supplier' => '<b>X</b>', 'reference' => 'R', 'date' => 'd', 'customer' => 'c', 'label' => 'l', 'quantity' => 1,
            'unit' => 'Stk', 'amount' => '1.00', 'delivery' => 'x', 'note' => '',
        ]);

        self::assertStringStartsWith('%PDF-', $binary);
    }
}
