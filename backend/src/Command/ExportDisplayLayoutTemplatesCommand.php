<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Display\Layout\DisplayLayoutTemplateCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Schreibt die offiziellen Infoscreen-Vorlagen als JSON. Das Ergebnis ist die Grundlage der reproduzierbaren
 * Vorschaubilder (Playwright rendert dieselben Layouts mit dem echten Renderer); kein Datenbankzugriff.
 */
#[AsCommand(name: 'app:display:export-templates', description: 'Exportiert die offiziellen Infoscreen-Vorlagen als JSON')]
final class ExportDisplayLayoutTemplatesCommand extends Command
{
    public function __construct(private DisplayLayoutTemplateCatalog $catalog)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Zieldatei');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        file_put_contents((string) $input->getArgument('file'), self::json($this->catalog));
        $output->writeln('Vorlagen exportiert.');

        return Command::SUCCESS;
    }

    public static function json(DisplayLayoutTemplateCatalog $catalog): string
    {
        return json_encode($catalog->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
