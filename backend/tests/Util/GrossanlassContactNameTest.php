<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\GrossanlassContactName;
use PHPUnit\Framework\TestCase;

class GrossanlassContactNameTest extends TestCase
{
    public function testSplitsFirstSpace(): void
    {
        self::assertSame(['Hans', 'Muster'], GrossanlassContactName::split('Hans Muster'));
        self::assertSame(['Anna', 'von Bergen'], GrossanlassContactName::split('  Anna   von Bergen '));
        self::assertSame(['Lisa', ''], GrossanlassContactName::split('Lisa'));
        self::assertSame(['', ''], GrossanlassContactName::split(''));
    }

    public function testMailPartsKeepNamesSeparateAndAnredeHerrFrau(): void
    {
        $parts = GrossanlassContactName::mailParts('Hans', 'Muster', '', 'herr');
        self::assertSame('Hans', $parts['VORNAME']);
        self::assertSame('Muster', $parts['NACHNAME']);
        self::assertSame('Hans Muster', $parts['KONTAKT']);
        self::assertSame('Herr', $parts['ANREDE']);
    }

    public function testMailPartsSplitLegacyFullName(): void
    {
        $parts = GrossanlassContactName::mailParts('', '', 'Hans Muster', 'Frau');
        self::assertSame('Frau', $parts['ANREDE']);
        self::assertSame('Hans', $parts['VORNAME']);
        self::assertSame('Muster', $parts['NACHNAME']);
    }

    public function testMailPartsWithoutSalutation(): void
    {
        $parts = GrossanlassContactName::mailParts('', '', '');
        self::assertSame('', $parts['ANREDE']);
        self::assertSame('', $parts['KONTAKT']);
    }

    public function testNormalizesSalutationAliases(): void
    {
        self::assertSame('herr', GrossanlassContactName::normalizeSalutation('Hr.'));
        self::assertSame('frau', GrossanlassContactName::normalizeSalutation('Mrs'));
        self::assertSame('', GrossanlassContactName::normalizeSalutation('Guten Tag'));
    }
}
