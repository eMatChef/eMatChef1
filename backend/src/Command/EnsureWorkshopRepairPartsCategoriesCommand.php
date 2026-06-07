<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Department;
use App\Service\Workshop\WorkshopSparePartsCategoryBootstrapService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:workshop:ensure-repair-parts-categories',
    description: 'Legt pro Department die Kategorie «Repair-Parts» an und verknüpft workshop.spare_parts_category_id',
)]
final class EnsureWorkshopRepairPartsCategoriesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkshopSparePartsCategoryBootstrapService $bootstrap,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $departments = $this->entityManager->getRepository(Department::class)->findAll();
        $count = 0;

        foreach ($departments as $department) {
            if (!$department instanceof Department) {
                continue;
            }
            $this->bootstrap->ensure($department);
            $count++;
        }

        $io->success(sprintf('Repair-Parts-Kategorie für %d Department(s) sichergestellt.', $count));

        return Command::SUCCESS;
    }
}
