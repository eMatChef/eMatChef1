<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\MapExternalStructureCommand;
use App\Entity\Department;
use App\Entity\ExternalStructureIdentity;
use App\Entity\Organisation;
use App\Repository\ExternalStructureIdentityRepository;
use App\Service\Auth\PbsGroupTypeClassifier;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class MapExternalStructureCommandTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ExternalStructureIdentityRepository&MockObject $structureIdentities;
    private Organisation $organisation;
    /** @var list<object> */
    private array $persisted = [];

    protected function setUp(): void
    {
        $this->organisation = (new Organisation())->setId('8fc745760d75');
        $this->organisation->setName('Pfadibewegung Schweiz');

        $organisations = $this->createMock(EntityRepository::class);
        $organisations->method('find')->willReturnCallback(
            fn (string $id): ?Organisation => $id === '8fc745760d75' ? $this->organisation : null,
        );
        $others = $this->createMock(EntityRepository::class);
        $others->method('find')->willReturn(null);
        $others->method('findOneBy')->willReturn(null);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => $class === Organisation::class ? $organisations : $others,
        );
        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $this->structureIdentities = $this->createMock(ExternalStructureIdentityRepository::class);
        $this->structureIdentities->method('findByOrganisation')->willReturn([]);
    }

    public function testDryRunValidatesWithoutWriting(): void
    {
        $this->entityManager->expects(self::never())->method('flush');

        $tester = $this->runCommand(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Dry-run', $tester->getDisplay());
        self::assertSame([], $this->persisted);
    }

    public function testCreatesTheExplicitOrganisationMapping(): void
    {
        $this->entityManager->expects(self::once())->method('flush');

        $tester = $this->runCommand(['--external-type' => 'Group::Bund', '--external-name' => 'Pfadibewegung Schweiz']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertCount(1, $this->persisted);
        $mapping = $this->persisted[0];
        self::assertInstanceOf(ExternalStructureIdentity::class, $mapping);
        self::assertSame('midata', $mapping->getProvider());
        self::assertSame('1', $mapping->getExternalGroupId());
        self::assertSame('Group::Bund', $mapping->getExternalType());
        self::assertSame('Pfadibewegung Schweiz', $mapping->getExternalName());
        self::assertSame($this->organisation, $mapping->getOrganisation());
        self::assertNull($mapping->getDepartment());
        self::assertNull($mapping->getGroup());
    }

    public function testExactlyTheSameMappingIsIdempotent(): void
    {
        $this->structureIdentities->method('findOneByProviderAndExternalGroupId')->willReturn(
            (new ExternalStructureIdentity())->setProvider('midata')->setExternalGroupId('1')->setOrganisation($this->organisation),
        );
        $this->entityManager->expects(self::never())->method('flush');

        $tester = $this->runCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Keine Änderung', $tester->getDisplay());
        self::assertSame([], $this->persisted);
    }

    public function testExternalIdMappedToAnotherTargetIsAConflict(): void
    {
        $other = (new Organisation())->setId('aaaaaaaaaaaa');
        $this->structureIdentities->method('findOneByProviderAndExternalGroupId')->willReturn(
            (new ExternalStructureIdentity())->setProvider('midata')->setExternalGroupId('1')->setOrganisation($other),
        );

        $tester = $this->runCommand();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Konflikt', $tester->getDisplay());
        self::assertSame([], $this->persisted);
    }

    public function testTargetAlreadyMappedToAnotherExternalGroupIsAConflict(): void
    {
        $this->structureIdentities = $this->createMock(ExternalStructureIdentityRepository::class);
        $this->structureIdentities->method('findByOrganisation')->willReturn([
            (new ExternalStructureIdentity())->setProvider('midata')->setExternalGroupId('2')->setOrganisation($this->organisation),
        ]);

        $tester = $this->runCommand();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([], $this->persisted);
    }

    public function testUnknownOrganisationFails(): void
    {
        $tester = $this->runCommand(['--organisation' => 'doesnotexist']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([], $this->persisted);
    }

    public function testConcurrentInsertIsReportedAsConflict(): void
    {
        $this->entityManager->method('flush')->willThrowException(
            new UniqueConstraintViolationException($this->createMock(DriverException::class), null),
        );

        $tester = $this->runCommand();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Konflikt', $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'unknown provider' => [['provider' => 'cevidb']];
        yield 'non-numeric id' => [['external-group-id' => 'abc']];
        yield 'zero id' => [['external-group-id' => '0']];
        yield 'negative id' => [['external-group-id' => '-1']];
        yield 'no target' => [['--organisation' => null]];
        yield 'two targets' => [['--department' => 'dep000000001']];
        yield 'root type' => [['--external-type' => 'Group::Root']];
        yield 'type does not fit target' => [['--external-type' => 'Group::Kantonalverband']];
        yield 'invalid parent id' => [['--external-parent-id' => 'x']];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidInputs')]
    public function testInvalidInputIsRejectedWithoutWriting(array $override): void
    {
        $this->entityManager->expects(self::never())->method('flush');

        $tester = $this->runCommand($override);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame([], $this->persisted);
    }

    public function testDepartmentTargetIsSupportedWithoutNameMatching(): void
    {
        $department = (new Department())->setId('dep000000001');
        $department->setName('Pfadi Zürich');
        $departments = $this->createMock(EntityRepository::class);
        $departments->method('find')->willReturn($department);
        $others = $this->createMock(EntityRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => $class === Department::class ? $departments : $others,
        );
        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $this->structureIdentities->method('findByDepartment')->willReturn([]);

        $tester = $this->runCommand(['--organisation' => null, '--department' => 'dep000000001', 'external-group-id' => '12']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame($department, $this->persisted[0]->getDepartment());
    }

    /**
     * @param array<string, mixed> $override
     */
    private function runCommand(array $override = []): CommandTester
    {
        $input = array_filter(
            array_merge([
                'provider' => 'midata',
                'external-group-id' => '1',
                '--organisation' => '8fc745760d75',
            ], $override),
            static fn (mixed $value): bool => $value !== null,
        );
        $tester = new CommandTester(new MapExternalStructureCommand(
            $this->entityManager,
            $this->structureIdentities,
            new PbsGroupTypeClassifier(),
        ));
        $tester->execute($input);

        return $tester;
    }
}
