<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Department;
use App\Entity\ExternalStructureIdentity;
use App\Entity\Group;
use App\Entity\Organisation;
use App\Repository\ExternalStructureIdentityRepository;
use App\Service\Auth\PbsGroupTypeClassifier;
use App\Util\IdGenerator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Explicit administrative link between one external group and one existing eMatChef object.
 * Never matches by name and never creates the internal object.
 */
#[AsCommand(
    name: 'app:external-structure:map',
    description: 'Ordnet eine externe Gruppe (z. B. MiData) explizit einer bestehenden Organisation, einem Department oder einer Group zu',
)]
final class MapExternalStructureCommand extends Command
{
    private const PROVIDERS = ['midata'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExternalStructureIdentityRepository $structureIdentities,
        private readonly PbsGroupTypeClassifier $pbsGroupTypes,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('provider', InputArgument::REQUIRED, 'Provider, z. B. midata')
            ->addArgument('external-group-id', InputArgument::REQUIRED, 'Externe Gruppen-ID (positive Ganzzahl)')
            ->addOption('organisation', null, InputOption::VALUE_REQUIRED, 'Interne Organisation-ID')
            ->addOption('department', null, InputOption::VALUE_REQUIRED, 'Interne Department-ID')
            ->addOption('group', null, InputOption::VALUE_REQUIRED, 'Interne Group-ID')
            ->addOption('external-type', null, InputOption::VALUE_REQUIRED, 'Externer Gruppentyp, z. B. Group::Bund')
            ->addOption('external-name', null, InputOption::VALUE_REQUIRED, 'Externer Name (nur Metadaten)')
            ->addOption('external-parent-id', null, InputOption::VALUE_REQUIRED, 'Externe Parent-ID (nur Metadaten)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur prüfen und anzeigen, nichts speichern');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $provider = trim((string) $input->getArgument('provider'));
        if (!in_array($provider, self::PROVIDERS, true)) {
            $io->error(sprintf('Unbekannter Provider «%s». Erlaubt: %s', $provider, implode(', ', self::PROVIDERS)));

            return Command::INVALID;
        }

        $externalGroupId = trim((string) $input->getArgument('external-group-id'));
        if (preg_match('/^[1-9][0-9]{0,17}$/', $externalGroupId) !== 1) {
            $io->error('Die externe Gruppen-ID muss eine positive Ganzzahl sein.');

            return Command::INVALID;
        }

        $externalParentId = $this->optionalString($input, 'external-parent-id');
        if ($externalParentId !== null && preg_match('/^[1-9][0-9]{0,17}$/', $externalParentId) !== 1) {
            $io->error('Die externe Parent-ID muss eine positive Ganzzahl sein.');

            return Command::INVALID;
        }

        $targetOptions = array_filter([
            PbsGroupTypeClassifier::ORGANISATION => $this->optionalString($input, 'organisation'),
            PbsGroupTypeClassifier::DEPARTMENT => $this->optionalString($input, 'department'),
            PbsGroupTypeClassifier::GROUP => $this->optionalString($input, 'group'),
        ], static fn (?string $value): bool => $value !== null);
        if (count($targetOptions) !== 1) {
            $io->error('Genau eines von --organisation, --department oder --group angeben.');

            return Command::INVALID;
        }
        $targetKind = (string) array_key_first($targetOptions);
        $targetId = $targetOptions[$targetKind];

        $externalType = $this->optionalString($input, 'external-type');
        if ($externalType !== null) {
            $expectedKind = $this->pbsGroupTypes->classify($externalType);
            if ($expectedKind === PbsGroupTypeClassifier::IGNORED) {
                $io->error(sprintf('%s wird nie einem eMatChef-Objekt zugeordnet.', $externalType));

                return Command::INVALID;
            }
            if ($expectedKind !== null && $expectedKind !== $targetKind) {
                $io->error(sprintf('%s passt nicht zum Zieltyp %s (erwartet: %s).', $externalType, $targetKind, $expectedKind));

                return Command::INVALID;
            }
        }

        $target = $this->entityManager->getRepository(match ($targetKind) {
            PbsGroupTypeClassifier::ORGANISATION => Organisation::class,
            PbsGroupTypeClassifier::DEPARTMENT => Department::class,
            default => Group::class,
        })->find($targetId);
        if ($target === null) {
            $io->error(sprintf('Interne %s mit ID «%s» existiert nicht.', $targetKind, $targetId));

            return Command::FAILURE;
        }

        $existing = $this->structureIdentities->findOneByProviderAndExternalGroupId($provider, $externalGroupId);
        if ($existing !== null) {
            if ($this->targetsExactly($existing, $targetKind, $targetId)) {
                $io->success(sprintf('%s/%s ist bereits genau dieser %s zugeordnet. Keine Änderung.', $provider, $externalGroupId, $targetKind));

                return Command::SUCCESS;
            }
            $io->error(sprintf('Konflikt: %s/%s ist bereits einem anderen Ziel zugeordnet (Mapping %s).', $provider, $externalGroupId, $existing->getId()));

            return Command::FAILURE;
        }

        foreach ($this->mappingsOfTarget($target) as $mapping) {
            if ($mapping->getProvider() === $provider) {
                $io->error(sprintf(
                    'Konflikt: Das Ziel ist bereits %s/%s zugeordnet (Mapping %s).',
                    $provider,
                    $mapping->getExternalGroupId(),
                    $mapping->getId(),
                ));

                return Command::FAILURE;
            }
        }

        $io->table(['Feld', 'Wert'], [
            ['provider', $provider],
            ['external_group_id', $externalGroupId],
            ['external_type', $externalType ?? '–'],
            ['external_name', $this->optionalString($input, 'external-name') ?? '–'],
            ['external_parent_id', $externalParentId ?? '–'],
            [$targetKind, sprintf('%s (%s)', $targetId, $this->targetName($target))],
        ]);
        if ($dryRun) {
            $io->note('Dry-run: Mapping wäre gültig, es wurde nichts gespeichert.');

            return Command::SUCCESS;
        }

        $mapping = new ExternalStructureIdentity();
        $mapping->setId(IdGenerator::generateUnique($this->entityManager, ExternalStructureIdentity::class));
        $mapping->setProvider($provider);
        $mapping->setExternalGroupId($externalGroupId);
        $mapping->setExternalType($externalType);
        $mapping->setExternalName($this->optionalString($input, 'external-name'));
        $mapping->setExternalParentId($externalParentId);
        match (true) {
            $target instanceof Organisation => $mapping->setOrganisation($target),
            $target instanceof Department => $mapping->setDepartment($target),
            $target instanceof Group => $mapping->setGroup($target),
        };
        $this->entityManager->persist($mapping);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $io->error(sprintf('Konflikt: %s/%s wurde gleichzeitig anderweitig zugeordnet.', $provider, $externalGroupId));

            return Command::FAILURE;
        }

        $io->success(sprintf('%s/%s → %s %s zugeordnet (Mapping %s).', $provider, $externalGroupId, $targetKind, $targetId, $mapping->getId()));

        return Command::SUCCESS;
    }

    private function optionalString(InputInterface $input, string $option): ?string
    {
        $value = $input->getOption($option);
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function targetsExactly(ExternalStructureIdentity $mapping, string $targetKind, string $targetId): bool
    {
        if (!$mapping->hasExactlyOneInternalTarget()) {
            return false;
        }

        return match ($targetKind) {
            PbsGroupTypeClassifier::ORGANISATION => $mapping->getOrganisation()?->getId() === $targetId,
            PbsGroupTypeClassifier::DEPARTMENT => $mapping->getDepartment()?->getId() === $targetId,
            default => $mapping->getGroup()?->getId() === $targetId,
        };
    }

    /**
     * @return list<ExternalStructureIdentity>
     */
    private function mappingsOfTarget(object $target): array
    {
        return match (true) {
            $target instanceof Organisation => $this->structureIdentities->findByOrganisation($target),
            $target instanceof Department => $this->structureIdentities->findByDepartment($target),
            $target instanceof Group => $this->structureIdentities->findByGroup($target),
            default => [],
        };
    }

    private function targetName(object $target): string
    {
        return match (true) {
            $target instanceof Organisation, $target instanceof Department, $target instanceof Group => $target->getName(),
            default => '',
        };
    }
}
