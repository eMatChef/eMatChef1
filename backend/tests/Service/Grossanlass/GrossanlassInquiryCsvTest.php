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
        self::assertSame('', $rows[0]['website']);
        self::assertSame('', $rows[0]['contact_name']);
        self::assertSame('', $rows[0]['contact_first_name']);
        self::assertSame('', $rows[0]['contact_last_name']);
        self::assertSame('', $rows[0]['contact_salutation']);
    }

    public function testParsesMailMergeColumns(): void
    {
        $csv = "Firma;Ort;Webseite;Bereich;Was;Hinweise;Firmeninhaber / Kontakt;E-Mail;Telefon\n"
            . "Holz AG;Thun;https://holz.example;Bauholz;Gerüste;nur Anfrage;Anna Meier;anna@holz.example;033 111 22 33\n";
        $rows = GrossanlassInquiryCsv::parse($csv);
        self::assertCount(1, $rows);
        self::assertSame('Holz AG', $rows[0]['name']);
        self::assertSame('Thun', $rows[0]['place']);
        self::assertSame('https://holz.example', $rows[0]['website']);
        self::assertSame(['Bauholz'], $rows[0]['categories']);
        self::assertSame('Gerüste', $rows[0]['offering']);
        self::assertSame('nur Anfrage', $rows[0]['notes']);
        self::assertSame('Anna Meier', $rows[0]['contact_name']);
        self::assertSame('', $rows[0]['contact_salutation']);
        self::assertSame('anna@holz.example', $rows[0]['email']);
        self::assertSame('033 111 22 33', $rows[0]['phone']);
    }

    public function testParsesSalutationAndNameColumns(): void
    {
        $csv = "Firma;Anrede;Vorname;Nachname;E-Mail\nHolz AG;Frau;Anna;Meier;anna@holz.example\n";
        $rows = GrossanlassInquiryCsv::parse($csv);
        self::assertCount(1, $rows);
        self::assertSame('Frau', $rows[0]['contact_salutation']);
        self::assertSame('Anna', $rows[0]['contact_first_name']);
        self::assertSame('Meier', $rows[0]['contact_last_name']);
    }

    public function testRejectsCsvWithoutNameColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GrossanlassInquiryCsv::parse("E-Mail;Ort\na@b.ch;Bern\n");
    }
}
