<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassFormFieldCatalog;
use PHPUnit\Framework\TestCase;

class GrossanlassFormFieldCatalogTest extends TestCase
{
    public function testCompanyTipDefaultsIncludeSalutationAndNames(): void
    {
        $keys = [];
        foreach (GrossanlassFormFieldCatalog::defaultCompanyTipFields() as $field) {
            $config = is_array($field['config'] ?? null) ? $field['config'] : [];
            $key = is_string($config['inquiry_key'] ?? null) ? $config['inquiry_key'] : null;
            if ($key !== null) {
                $keys[] = $key;
            }
        }
        self::assertContains('contact_salutation', $keys);
        self::assertContains('contact_first_name', $keys);
        self::assertContains('contact_last_name', $keys);
        self::assertContains('email', $keys);
        self::assertContains('phone', $keys);
        self::assertContains('name', $keys);
        $category = null;
        $offering = null;
        foreach (GrossanlassFormFieldCatalog::defaultCompanyTipFields() as $field) {
            $config = is_array($field['config'] ?? null) ? $field['config'] : [];
            if (($config['inquiry_key'] ?? null) === 'categories') {
                $category = $field;
            }
            if (($config['inquiry_key'] ?? null) === 'offering') {
                $offering = $field;
            }
        }
        self::assertSame('Bereiche', $category['label'] ?? null);
        self::assertSame(GrossanlassFormFieldCatalog::CUSTOM_SELECT, $category['custom_type'] ?? null);
        self::assertTrue(is_array($category['options'] ?? null) && ($category['options']['multiple'] ?? false) === true);
        self::assertTrue(is_array($offering['config'] ?? null) && ($offering['config']['multiline'] ?? false) === true);
    }

    public function testInquiryKeyFromLabelPrefersFirstNameOverKontakt(): void
    {
        self::assertSame('contact_first_name', GrossanlassFormFieldCatalog::inquiryKeyFromLabel('Kontakt Vorname'));
        self::assertSame('contact_last_name', GrossanlassFormFieldCatalog::inquiryKeyFromLabel('Nachname'));
        self::assertSame('contact_salutation', GrossanlassFormFieldCatalog::inquiryKeyFromLabel('Anrede'));
        self::assertSame('email', GrossanlassFormFieldCatalog::inquiryKeyFromLabel('Kontakt / E-Mail'));
        self::assertSame('email', GrossanlassFormFieldCatalog::inquiryKeyFromField(['inquiry_key' => 'email'], 'Ignoriert'));
    }

    public function testExtractMapsSalutationNamesAndCsvColumns(): void
    {
        $extracted = GrossanlassFormFieldCatalog::extractInquiryFieldsFromAnswers([
            ['label' => 'Firma', 'text' => 'Holz AG'],
            ['label' => 'Ort', 'text' => 'Bern'],
            ['label' => 'Webseite', 'text' => 'https://holz.example'],
            ['label' => 'Bereich', 'text' => 'Fahrzeuge, Anhänger'],
            ['label' => 'Was', 'text' => 'Muldenkipper'],
            ['label' => 'Hinweise', 'text' => 'nur Anfrage'],
            ['label' => 'Anrede', 'text' => 'Frau'],
            ['label' => 'Vorname', 'text' => 'Anna'],
            ['label' => 'Nachname', 'text' => 'Meier'],
            ['label' => 'E-Mail', 'text' => 'anna@holz.example'],
            ['label' => 'Telefon', 'text' => '031 000 00 00'],
        ]);
        self::assertSame('Holz AG', $extracted['name']);
        self::assertSame('Bern', $extracted['place']);
        self::assertSame('https://holz.example', $extracted['website']);
        self::assertSame(['Fahrzeuge', 'Anhänger'], $extracted['categories']);
        self::assertSame('Muldenkipper', $extracted['offering']);
        self::assertSame('nur Anfrage', $extracted['notes']);
        self::assertSame('Frau', $extracted['contact_salutation']);
        self::assertSame('Anna', $extracted['contact_first_name']);
        self::assertSame('Meier', $extracted['contact_last_name']);
        self::assertSame('anna@holz.example', $extracted['email']);
        self::assertSame('031 000 00 00', $extracted['phone']);
    }

    public function testExtractStripsCategoryPrefixAndJsonList(): void
    {
        $fromJson = GrossanlassFormFieldCatalog::extractInquiryFieldsFromAnswers([
            ['label' => 'Bereiche', 'text' => '', 'json' => ['Fahrzeuge', '↳ Anhänger']],
        ]);
        self::assertSame(['Fahrzeuge', 'Anhänger'], $fromJson['categories']);
        self::assertSame('Anhänger', GrossanlassFormFieldCatalog::normalizeCategoryToken('↳ Anhänger'));
    }
}
