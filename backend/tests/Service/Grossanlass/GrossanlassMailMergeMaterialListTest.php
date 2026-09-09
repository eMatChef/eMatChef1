<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassMailMergeService;
use PHPUnit\Framework\TestCase;

class GrossanlassMailMergeMaterialListTest extends TestCase
{
    public function testFormatIncludesQuantityAndLabelOnly(): void
    {
        $html = GrossanlassMailMergeService::formatMaterialListHtml([
            ['quantity' => 2, 'label' => 'Gator'],
            ['quantity' => 1, 'label' => 'Anhänger'],
        ]);
        self::assertSame('2× Gator<br>1× Anhänger', $html);
    }

    public function testFormatWithoutQuantityListsLabelsOnly(): void
    {
        $html = GrossanlassMailMergeService::formatMaterialListHtml([
            ['quantity' => 12, 'label' => 'Schraube M8x40'],
            ['quantity' => 4, 'label' => 'Schraube M10x50'],
        ], false);
        self::assertSame('Schraube M8x40<br>Schraube M10x50', $html);
        self::assertStringNotContainsString('12×', $html);
    }

    public function testFormatIgnoresNotesAndLocationIfPassed(): void
    {
        $html = GrossanlassMailMergeService::formatMaterialListHtml([
            [
                'quantity' => 2,
                'label' => 'Gator',
                'location' => 'Auf dem gelände',
                'notes' => 'intern, nicht in die Mail',
            ],
        ]);
        self::assertSame('2× Gator', $html);
        self::assertStringNotContainsString('gelände', $html);
        self::assertStringNotContainsString('intern', $html);
    }

    public function testGroupedListPutsArticlesUnderCategoryWithoutQuantity(): void
    {
        $html = GrossanlassMailMergeService::formatGroupedMaterialListHtml([
            ['category' => 'Handwerkzeuge', 'items' => ['Hämmer', 'Schaufeln']],
            ['category' => 'Elektrowerkzeuge und Maschinen', 'items' => ['Akkuschrauber']],
        ]);
        self::assertSame(
            '<strong>Handwerkzeuge</strong><br>Hämmer sowie Schaufeln<br><br>'
            . '<strong>Elektrowerkzeuge und Maschinen</strong><br>Akkuschrauber',
            $html,
        );
        self::assertStringNotContainsString('×', $html);
        self::assertSame(
            ['Infrastruktur'],
            GrossanlassMailMergeService::areaNamesFromGroupedItems([
                ['category' => 'Sanitär & Wasserversorgung', 'area' => 'Infrastruktur', 'items' => ['Wasserschlauch 32mm']],
                ['category' => 'Bauten, Überdachungen & Raummodule', 'area' => 'Infrastruktur', 'items' => ['Container']],
            ]),
        );
    }

    public function testGermanNameListJoinsWithSowie(): void
    {
        self::assertSame('Akkuschrauber', GrossanlassMailMergeService::formatGermanNameList(['Akkuschrauber']));
        self::assertSame('Akkuschrauber sowie Leitern', GrossanlassMailMergeService::formatGermanNameList([
            'Akkuschrauber',
            'Leitern',
        ]));
        self::assertSame(
            'Akkuschrauber, Bohrmaschinen sowie Leitern',
            GrossanlassMailMergeService::formatGermanNameList(['Akkuschrauber', 'Bohrmaschinen', 'Leitern']),
        );
    }

    public function testMatchBodyPositionsFlagsUnexpectedBedarfAndAllowsOmitting(): void
    {
        $body = GrossanlassMailMergeService::formatGroupedMaterialListHtml([
            ['category' => 'Sanitär & Wasserversorgung', 'items' => ['Wasserschlauch 32mm']],
        ]);
        $match = GrossanlassMailMergeService::matchBodyPositions(
            $body,
            ['Wasserschlauch 32mm', 'Container'],
            ['Generator', 'Werkzeuge'],
        );
        self::assertSame(['Wasserschlauch 32mm'], $match['mentioned']);
        self::assertSame(['Container'], $match['omitted']);
        self::assertSame([], $match['unexpected']);

        $withExtra = $body . '<p>Bitte auch Generator und Werkzeuge.</p>';
        $bad = GrossanlassMailMergeService::matchBodyPositions(
            $withExtra,
            ['Wasserschlauch 32mm', 'Sanitär & Wasserversorgung'],
            ['Container', 'Werkzeuge', 'Generator'],
        );
        self::assertSame(['Werkzeuge', 'Generator'], $bad['unexpected']);
    }

    public function testFindMentionedNamesUsesWordBoundaries(): void
    {
        $plain = GrossanlassMailMergeService::htmlToPlainForMatch('Bitte Containerkran liefern.');
        self::assertSame(
            [],
            GrossanlassMailMergeService::findMentionedNames($plain, ['Kran', 'Container']),
        );
        self::assertSame(
            ['Containerkran'],
            GrossanlassMailMergeService::findMentionedNames($plain, ['Kran', 'Container', 'Containerkran']),
        );
        self::assertSame(
            [],
            GrossanlassMailMergeService::findMentionedNames(
                'Gelände- und Utility-Fahrzeuge',
                ['Fahrzeuge'],
            ),
        );
    }

    public function testZeitraumTextForMailEscapesAndKeepsLineBreaks(): void
    {
        self::assertSame(
            GrossanlassMailMergeService::ZEITRAUM_ABSPRACHE,
            html_entity_decode(strip_tags(GrossanlassMailMergeService::zeitraumTextForMail('')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
        $html = GrossanlassMailMergeService::zeitraumTextForMail("Aufbau: 1.7.\nAnlass: <script>");
        self::assertStringContainsString('Aufbau: 1.7.<br>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testMailItemNamesUseSelectedLeaves(): void
    {
        $rows = [
            ['id' => 'w', 'parentId' => null, 'name' => 'Werkzeuge', 'kind' => 'package'],
            ['id' => 'e', 'parentId' => 'w', 'name' => 'Elektrowerkzeuge und Maschinen', 'kind' => 'package'],
            ['id' => 'h', 'parentId' => 'w', 'name' => 'Handwerkzeuge', 'kind' => 'package'],
        ];
        self::assertSame(
            ['Elektrowerkzeuge und Maschinen', 'Handwerkzeuge'],
            GrossanlassMailMergeService::mailItemNames($rows, ['w']),
        );
        self::assertSame(
            ['Elektrowerkzeuge und Maschinen'],
            GrossanlassMailMergeService::mailItemNames($rows, ['e']),
        );
        self::assertSame(
            ['Werkzeuge/Elektrowerkzeuge und Maschinen'],
            GrossanlassMailMergeService::packagePaths($rows, ['e']),
        );
        self::assertSame(
            ['Werkzeuge'],
            GrossanlassMailMergeService::mailAreaNames($rows, ['e']),
        );
        self::assertSame(
            ['Werkzeuge'],
            GrossanlassMailMergeService::mailAreaNames($rows, ['w']),
        );
    }

    public function testFirstWaveKindsAttachFiles(): void
    {
        self::assertTrue(GrossanlassMailMergeService::kindAttachesFiles('anfrage'));
        self::assertTrue(GrossanlassMailMergeService::kindAttachesFiles('nachfassen'));
        self::assertTrue(GrossanlassMailMergeService::kindAttachesFiles('praezisieren'));
        self::assertFalse(GrossanlassMailMergeService::kindAttachesFiles('nehmen'));
        self::assertFalse(GrossanlassMailMergeService::kindAttachesFiles('dank_absage'));
    }

    public function testMaterialListFilenameSlugsFirmName(): void
    {
        self::assertSame('Materialliste-Kehkehwa.pdf', GrossanlassMailMergeService::materialListFilename('Kehkehwa'));
        self::assertSame('Materialliste-Firma.pdf', GrossanlassMailMergeService::materialListFilename('???'));
    }

    public function testFilterGroupedItemsByLabelsKeepsOnlyAskedArticles(): void
    {
        $groups = [
            ['category' => 'Sanitär', 'items' => ['Wasserschlauch 32mm', 'Container']],
            ['category' => 'Bauten', 'items' => ['Zelt']],
        ];
        self::assertSame(
            [
                ['category' => 'Sanitär', 'items' => ['Container']],
                ['category' => 'Bauten', 'items' => ['Zelt']],
            ],
            GrossanlassMailMergeService::filterGroupedItemsByLabels($groups, ['Container', 'Zelt']),
        );
        self::assertSame([], GrossanlassMailMergeService::filterGroupedItemsByLabels($groups, []));
        self::assertSame($groups, GrossanlassMailMergeService::filterGroupedItemsByLabels($groups, null));
    }

    public function testResolveAttachmentItemLabelsUsesRestThenOverride(): void
    {
        $allowed = ['Wasserschlauch 32mm', 'Container', 'Zelt'];
        self::assertSame(
            ['Container', 'Zelt'],
            GrossanlassMailMergeService::resolveAttachmentItemLabels($allowed, ['Container', 'Zelt'], null),
        );
        self::assertSame(
            $allowed,
            GrossanlassMailMergeService::resolveAttachmentItemLabels($allowed, [], null),
        );
        self::assertSame(
            ['Zelt'],
            GrossanlassMailMergeService::resolveAttachmentItemLabels($allowed, ['Container'], ['Zelt', 'fremd']),
        );
        self::assertSame(
            [],
            GrossanlassMailMergeService::resolveAttachmentItemLabels($allowed, ['Container'], []),
        );
    }
}
