<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassInquiryCsv;
use PHPUnit\Framework\TestCase;

class GrossanlassInquiryCsvTest extends TestCase
{
    public function testParsesGermanSemicolonSheet(): void
    {
        $csv = "Firma;E-Mail;Ort;Bereiche\nMüller AG;info@mueller.example;Bern;Fahrzeuge, Bauholz\n";
        $rows = GrossanlassInquiryCsv::parse($csv);
        self::assertCount(1, $rows);
        self::assertSame('Müller AG', $rows[0]['name']);
        self::assertSame('info@mueller.example', $rows[0]['email']);
        self::assertSame('Bern', $rows[0]['place']);
        self::assertSame(['Fahrzeuge', 'Bauholz'], $rows[0]['categories']);
        self::assertSame(2, $rows[0]['line']);
    }

    public function testSkipsEmptyNamesAndReadsEnglishHeaders(): void
    {
        $csv = "Company,Email,Place,Categories\n,skip@x.example,,\nZeltwerk,anfragen@zelt.example,Burgdorf,Zelt\n";
        $rows = GrossanlassInquiryCsv::parse($csv);
        self::assertCount(1, $rows);
        self::assertSame('Zeltwerk', $rows[0]['name']);
        self::assertSame(['Zelt'], $rows[0]['categories']);
    }

    public function testRejectsCsvWithoutNameColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GrossanlassInquiryCsv::parse("E-Mail;Ort\na@b.ch;Bern\n");
    }
}
