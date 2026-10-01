<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassGroupShareRules;
use PHPUnit\Framework\TestCase;

class GrossanlassGroupShareRulesTest extends TestCase
{
    public function testAllowsCousinRessorts(): void
    {
        self::assertNull(GrossanlassGroupShareRules::forbiddenReason(
            'bau',
            'deko',
            ['bau', 'buehne'],
            ['deko', 'deko-west'],
            false,
        ));
    }

    public function testRejectsSelfAndOwnBranch(): void
    {
        self::assertSame(
            'Kann nicht mit sich selbst teilen',
            GrossanlassGroupShareRules::forbiddenReason('bau', 'bau', ['bau'], ['bau'], false),
        );
        self::assertSame(
            'Kann nicht in den eigenen Zweig teilen',
            GrossanlassGroupShareRules::forbiddenReason('bau', 'buehne', ['bau', 'buehne'], ['buehne'], false),
        );
        self::assertSame(
            'Kann nicht mit einem Unterknoten teilen',
            GrossanlassGroupShareRules::forbiddenReason('buehne', 'bau', ['buehne'], ['bau', 'buehne'], false),
        );
        self::assertSame(
            'Ziel muss ein Ressort oder Bereich sein',
            GrossanlassGroupShareRules::forbiddenReason('bau', 'foh', ['bau'], ['foh'], true),
        );
    }
}
