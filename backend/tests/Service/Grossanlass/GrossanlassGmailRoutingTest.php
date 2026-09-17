<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassGmailRouting;
use PHPUnit\Framework\TestCase;

class GrossanlassGmailRoutingTest extends TestCase
{
    public function testDefaultTreeUsesEmatchefRoot(): void
    {
        $names = GrossanlassGmailRouting::labelNames(
            GrossanlassGmailRouting::defaults(),
            'PFF 2027',
            ['Fahrzeuge'],
        );

        self::assertSame([
            'eMatChef-PFF 2027',
            'eMatChef-PFF 2027/Firmenanfragen',
            'eMatChef-PFF 2027/Status/Wartet auf Antwort',
            'eMatChef-PFF 2027/Status',
            'eMatChef-PFF 2027/Firmenanfragen/Fahrzeuge',
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
        self::assertSame(['eMatChef-PFF27', 'eMatChef-PFF27/Partner', 'OK', 'PFF27/Archiv'], $names);
        self::assertSame('PFF-iq12ab34cd56', GrossanlassGmailRouting::displayReference($routing['reference_prefix'], 'iq12ab34cd56'));
    }

    public function testSlashesInRootBecomeDashes(): void
    {
        $routing = GrossanlassGmailRouting::normalize(['label_root' => 'PFF/27']);
        self::assertSame('eMatChef-PFF-27', $routing['label_root']);
    }

    public function testImportFromGmailTree(): void
    {
        $imported = GrossanlassGmailRouting::importFromGmail(
            [
                'PFF 2027',
                'PFF 2027/Firmenanfragen',
                'PFF 2027/Firmenanfragen/Fahrzeuge',
                'PFF 2027/Status',
                'PFF 2027/Status/Wartet auf Antwort',
                'PFF 2027/Status/Antwort erhalten',
                'PFF 2027/Archiv',
                'Privat',
            ],
            'PFF 2027',
            'PFF-',
        );
        self::assertSame('eMatChef-PFF 2027', $imported['label_root']);
        self::assertSame('Firmenanfragen', $imported['label_inquiries']);
        self::assertSame('Status/Wartet auf Antwort', $imported['label_waiting']);
        self::assertSame('Status/Antwort erhalten', $imported['label_replied']);
        self::assertTrue($imported['label_by_package']);
        self::assertContains('PFF 2027/Archiv', $imported['extra_labels']);
        self::assertSame('eMatChef', GrossanlassGmailRouting::normalize([])['label_root']);
        self::assertSame('eMatChef-PFF 2027', GrossanlassGmailRouting::composedRoot('PFF 2027'));
        self::assertSame('eMatChef-PFF 2027', GrossanlassGmailRouting::resolveRoot([], 'PFF 2027'));
        self::assertSame('eMatChef-PFF 2027', GrossanlassGmailRouting::suggestRoot(
            ['PFF 2027', 'PFF 2027/Firmenanfragen', 'Anderes'],
            'PFF 2027',
        ));
        self::assertSame('eMatChef-PFF 2027', GrossanlassGmailRouting::suggestRoot(
            ['eMatChef-PFF 2027', 'eMatChef-PFF 2027/Firmenanfragen', 'PFF 2027'],
            'PFF 2027',
        ));
        self::assertSame('eMatChef', GrossanlassGmailRouting::suggestRoot(
            ['eMatChef', 'eMatChef/Firmenanfragen', 'PFF 2027'],
            'PFF 2027',
        ));
        self::assertSame('eMatChef-PFF 2027', GrossanlassGmailRouting::suggestRoot(['Privat'], 'PFF 2027'));
        self::assertSame(
            ['Fahrzeuge'],
            GrossanlassGmailRouting::inquiryCategoryNames(
                [
                    'PFF 2027',
                    'PFF 2027/Firmenanfragen',
                    'PFF 2027/Firmenanfragen/Fahrzeuge',
                    'PFF 2027/Status',
                    'PFF 2027/Status/Wartet auf Antwort',
                ],
                'PFF 2027',
                'Firmenanfragen',
            ),
        );
    }

    public function testRepliedStatusUsesAnswerLabel(): void
    {
        $names = GrossanlassGmailRouting::labelNames(
            GrossanlassGmailRouting::defaults(),
            'PFF 2027',
            [],
            GrossanlassGmailRouting::STATUS_REPLIED,
        );
        self::assertContains('eMatChef-PFF 2027/Status/Antwort erhalten', $names);
        self::assertNotContains('eMatChef-PFF 2027/Status/Wartet auf Antwort', $names);
    }

    public function testInquiryStatusPathAndFullStatusTree(): void
    {
        $routing = GrossanlassGmailRouting::normalize(['label_root' => 'PFF27']);
        self::assertSame('eMatChef-PFF27/Status/Wartet auf Antwort', GrossanlassGmailRouting::inquiryStatusPath($routing, 'Ignored', 'gesendet'));
        self::assertSame('eMatChef-PFF27/Status/Antwort erhalten', GrossanlassGmailRouting::inquiryStatusPath($routing, 'Ignored', 'antwort'));
        self::assertSame('eMatChef-PFF27/Status/Zusage', GrossanlassGmailRouting::inquiryStatusPath($routing, 'Ignored', 'zusage'));
        self::assertSame('eMatChef-PFF27/Status/Absage', GrossanlassGmailRouting::inquiryStatusPath($routing, 'Ignored', 'absage'));
        $all = GrossanlassGmailRouting::allStatusLabelNames($routing, 'Ignored');
        self::assertContains('eMatChef-PFF27/Status/Nachfassen', $all);
        self::assertContains('eMatChef-PFF27/Status/Erledigt', $all);
        self::assertContains('eMatChef-PFF27/Status', $all);
    }

    public function testInquiryCategoryNamesFromScreenshotTree(): void
    {
        $names = GrossanlassGmailRouting::inquiryCategoryNames(
            [
                'PFF27',
                'PFF27/Firmenanfragen',
                'PFF27/Firmenanfragen/Bauholz',
                'PFF27/Firmenanfragen/Fahrzeuge',
                'PFF27/Firmenanfragen/Werkzeug',
                'PFF27/Status',
                'PFF27/Status/Zusage',
            ],
            'PFF27',
            'Firmenanfragen',
        );
        self::assertSame(['Bauholz', 'Fahrzeuge', 'Werkzeug'], $names);
    }

    public function testUnusedGmailLabelsStayUnderRootAndSkipParents(): void
    {
        $wanted = [
            'PFF27',
            'PFF27/Firmenanfragen',
            'PFF27/Firmenanfragen/Fahrzeuge',
            'PFF27/Status',
            'PFF27/Status/Zusage',
        ];
        $unused = GrossanlassGmailRouting::unusedGmailLabels(
            [
                'Privat',
                'PFF27',
                'PFF27/Firmenanfragen',
                'PFF27/Firmenanfragen/Fahrzeuge',
                'PFF27/Firmenanfragen/Alt',
                'PFF27/Status',
                'PFF27/Status/Altstatus',
            ],
            $wanted,
            'PFF27',
        );
        self::assertSame(['PFF27/Firmenanfragen/Alt', 'PFF27/Status/Altstatus'], $unused);
        self::assertNotContains('Privat', $unused);
        self::assertNotContains('PFF27', $unused);
        self::assertNotContains('PFF27/Firmenanfragen', $unused);
    }

    public function testNestedCategoryBecomesNestedGmailLabel(): void
    {
        $names = GrossanlassGmailRouting::labelNames(
            GrossanlassGmailRouting::defaults(),
            'PFF 2027',
            ['Fahrzeuge/Anhänger'],
        );

        self::assertContains('eMatChef-PFF 2027/Firmenanfragen/Fahrzeuge', $names);
        self::assertContains('eMatChef-PFF 2027/Firmenanfragen/Fahrzeuge/Anhänger', $names);
    }

    public function testForeignRootIsPrefixedWithEmatchef(): void
    {
        self::assertSame('eMatChef', GrossanlassGmailRouting::enforceEmatchefRoot(''));
        self::assertSame('eMatChef', GrossanlassGmailRouting::enforceEmatchefRoot('eMatChef'));
        self::assertSame('eMatChef-PFF 2027', GrossanlassGmailRouting::enforceEmatchefRoot('eMatChef-PFF 2027'));
        self::assertSame('eMatChef-PFF27', GrossanlassGmailRouting::enforceEmatchefRoot('PFF27'));
        self::assertSame('eMatChef-PFF27', GrossanlassGmailRouting::normalize(['label_root' => 'PFF27'])['label_root']);
    }

    public function testHasRootLabelAndInboxQuery(): void
    {
        $root = 'eMatChef-PFF 2027';
        self::assertTrue(GrossanlassGmailRouting::hasRootLabel([$root], $root));
        self::assertTrue(GrossanlassGmailRouting::hasRootLabel([$root . '/Firmenanfragen'], $root));
        self::assertFalse(GrossanlassGmailRouting::hasRootLabel(['INBOX', 'Privat'], $root));
        self::assertFalse(GrossanlassGmailRouting::hasRootLabel(['eMatChef-Anderer Anlass'], $root));
        self::assertSame('label:eMatChef-PFF-2027 newer_than:21d', GrossanlassGmailRouting::inboxQuery($root));
    }
}
