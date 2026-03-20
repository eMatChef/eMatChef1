<?php

namespace App\Command;

use App\Entity\Department;
use App\Entity\MaterialTemplate;
use App\Entity\MaterialTemplateComponent;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:templates:import',
    description: 'Importiert Zelt-Vorlagen aus JSON-Dateien (v4-Format)'
)]
class ImportTemplatesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('department_id', InputArgument::OPTIONAL, 'Department-ID (leer = zentrale/globale Vorlagen)')
            ->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Einzelne JSON-Datei importieren (z.B. data/templates/hajk.json)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Alle JSON-Dateien aus data/templates/ importieren')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Bestehende Vorlagen überschreiben')
            ->addOption('global', 'g', InputOption::VALUE_NONE, 'Als zentrale Vorlagen importieren (department_id=NULL)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $departmentId = $input->getArgument('department_id');
        $isGlobal = $input->getOption('global') || !$departmentId;

        $department = null;
        $scope = 'global';

        if ($isGlobal) {
            $io->title('Zelt-Vorlagen Import (ZENTRAL)');
            $io->text('Scope: Globale Vorlagen (sichtbar für alle Departments)');
        } else {
            // Department prüfen
            $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
            if (!$department) {
                $io->error('Department nicht gefunden: ' . $departmentId);
                return Command::FAILURE;
            }
            $scope = 'department';
            $io->title('Zelt-Vorlagen Import (Department)');
            $io->text('Department: ' . $department->getName() . ' (' . $department->getId() . ')');
        }

        $force = $input->getOption('force');
        $files = [];

        if ($input->getOption('all')) {
            $dir = dirname(__DIR__, 2) . '/data/templates';
            if (!is_dir($dir)) {
                $io->error('Template-Verzeichnis nicht gefunden: ' . $dir);
                return Command::FAILURE;
            }
            foreach (glob($dir . '/*.json') as $file) {
                $files[] = $file;
            }
        } elseif ($input->getOption('file')) {
            $file = $input->getOption('file');
            // Relativer oder absoluter Pfad
            if (!file_exists($file)) {
                $file = dirname(__DIR__, 2) . '/' . $file;
            }
            if (!file_exists($file)) {
                $io->error('Datei nicht gefunden: ' . $input->getOption('file'));
                return Command::FAILURE;
            }
            $files[] = $file;
        } else {
            $io->error('Bitte --file oder --all angeben.');
            return Command::FAILURE;
        }

        $totalCreated = 0;
        $totalSkipped = 0;
        $totalUpdated = 0;

        foreach ($files as $filePath) {
            $io->section('Datei: ' . basename($filePath));

            $content = file_get_contents($filePath);
            $json = json_decode($content, true);

            if (!$json || !isset($json['manufacturer']) || !isset($json['templates'])) {
                $io->warning('Ungültiges Format, übersprungen.');
                continue;
            }

            $manufacturer = $json['manufacturer'];
            $io->text('Hersteller: ' . $manufacturer);

            foreach ($json['templates'] as $tplData) {
                $name = $tplData['name'] ?? $tplData['id'] ?? 'Unbenannt';

                // Prüfe ob bereits vorhanden (nach Name + scope)
                $findBy = ['name' => $name];
                if ($isGlobal) {
                    // Zentrale Vorlagen: departmentId IS NULL
                    $findBy['departmentId'] = null;
                } else {
                    $findBy['departmentId'] = $department->getId();
                }

                $existing = $this->entityManager->getRepository(MaterialTemplate::class)
                    ->findOneBy($findBy);

                if ($existing && !$force) {
                    $io->text("  ⏭  $name (existiert bereits)");
                    $totalSkipped++;
                    continue;
                }

                if ($existing && $force) {
                    // Bestehende Komponenten entfernen
                    foreach ($existing->getComponents()->toArray() as $comp) {
                        $this->entityManager->remove($comp);
                    }
                    $template = $existing;
                    $totalUpdated++;
                    $action = '🔄';
                } else {
                    $template = new MaterialTemplate();
                    $template->setId(IdGenerator::generate());
                    $template->setDepartment($department); // NULL für global
                    $template->setScope($scope);
                    $totalCreated++;
                    $action = '✅';
                }

                $template->setName($name);
                $template->setDescription($tplData['description'] ?? null);
                $template->setManufacturer($manufacturer);
                $template->setModel($tplData['model'] ?? null);
                $template->setMaterialType('physical_combo');
                $template->setTentType($tplData['tentType'] ?? null);
                $template->setCapacity(isset($tplData['capacity']) ? (int) $tplData['capacity'] : null);
                $template->setReservationMode($tplData['reservationMode'] ?? null);
                $template->setIsActive($tplData['isActive'] ?? true);
                $template->setSource($manufacturer);
                $template->updateTimestamps();

                // Komponenten
                $compCount = 0;
                if (isset($tplData['components']) && is_array($tplData['components'])) {
                    foreach ($tplData['components'] as $index => $compData) {
                        $comp = new MaterialTemplateComponent();
                        $comp->setId(IdGenerator::generate());
                        $comp->setComponentType($compData['type'] ?? 'unknown');
                        $comp->setName($compData['name'] ?? $compData['type'] ?? 'Unbenannt');
                        $comp->setRequiredQty(isset($compData['required']) ? (int) $compData['required'] : 1);
                        $comp->setIsOptional($compData['optional'] ?? false);
                        $comp->setSortOrder($index);

                        // Tracking: aus JSON oder Heuristik
                        if (isset($compData['tracking'])) {
                            $comp->setTracking($compData['tracking']);
                        } else {
                            $comp->setTracking($comp->getRequiredQty() <= 1 ? 'serialized' : 'bulk');
                        }

                        // Repair Types
                        if (isset($compData['repair_types']) && is_array($compData['repair_types'])) {
                            $comp->setRepairTypes($compData['repair_types']);
                        }

                        $template->addComponent($comp);
                        $compCount++;
                    }
                }

                $this->entityManager->persist($template);

                $capacity = $template->getCapacity() ? $template->getCapacity() . ' Pers.' : '-';
                $io->text("  $action  $name ($capacity, $compCount Komp.)");
            }
        }

        $this->entityManager->flush();

        $io->newLine();
        $io->success(sprintf(
            'Import abgeschlossen: %d erstellt, %d aktualisiert, %d übersprungen',
            $totalCreated,
            $totalUpdated,
            $totalSkipped
        ));

        return Command::SUCCESS;
    }
}
