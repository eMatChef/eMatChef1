<?php

declare(strict_types=1);

namespace App\Tests\Service\Display\Layout;

use App\Command\ExportDisplayLayoutTemplatesCommand;
use App\Service\Display\Layout\DisplayLayoutTemplateCatalog;
use PHPUnit\Framework\TestCase;

/** Die Fixture für die Vorschaubilder muss den offiziellen Vorlagen entsprechen (Neu erzeugen: app:display:export-templates). */
final class DisplayLayoutTemplateFixtureTest extends TestCase
{
    public function testFixtureMatchesCatalog(): void
    {
        $file = dirname(__DIR__, 5) . '/frontend/src/test-fixtures/displayLayoutTemplates.json';
        if (!is_file($file)) {
            self::markTestSkipped('Frontend-Fixture nicht verfügbar.');
        }
        self::assertSame(
            ExportDisplayLayoutTemplatesCommand::json(new DisplayLayoutTemplateCatalog()),
            file_get_contents($file),
            'frontend/src/test-fixtures/displayLayoutTemplates.json ist veraltet: php bin/console app:display:export-templates <Datei>',
        );
    }
}
