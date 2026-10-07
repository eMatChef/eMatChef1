<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\PbsGroupTypeClassifier;
use PHPUnit\Framework\TestCase;

final class PbsGroupTypeClassifierTest extends TestCase
{
    public function testTechnicalRootIsIgnored(): void
    {
        self::assertSame(PbsGroupTypeClassifier::IGNORED, (new PbsGroupTypeClassifier())->classify('Group::Root'));
    }

    public function testClassifiesBundAsOrganisationCandidate(): void
    {
        self::assertSame(PbsGroupTypeClassifier::ORGANISATION, (new PbsGroupTypeClassifier())->classify('Group::Bund'));
    }

    public function testClassifiesKantonalverbandRegionAndAbteilungAsDepartmentCandidates(): void
    {
        $classifier = new PbsGroupTypeClassifier();

        self::assertSame(PbsGroupTypeClassifier::DEPARTMENT, $classifier->classify('Group::Kantonalverband'));
        self::assertSame(PbsGroupTypeClassifier::DEPARTMENT, $classifier->classify('Group::Region'));
        self::assertSame(PbsGroupTypeClassifier::DEPARTMENT, $classifier->classify('Group::Abteilung'));
    }

    public function testClassifiesKnownYouthSectionsAsGroupCandidates(): void
    {
        $classifier = new PbsGroupTypeClassifier();

        self::assertSame(PbsGroupTypeClassifier::GROUP, $classifier->classify('Group::Biber'));
        self::assertSame(PbsGroupTypeClassifier::GROUP, $classifier->classify('Group::Woelfe'));
        self::assertSame(PbsGroupTypeClassifier::GROUP, $classifier->classify('Group::Pfadi'));
        self::assertSame(PbsGroupTypeClassifier::GROUP, $classifier->classify('Group::Pio'));
        self::assertSame(PbsGroupTypeClassifier::GROUP, $classifier->classify('Group::Rover'));
    }

    public function testUnknownTypeIsNotClassified(): void
    {
        self::assertNull((new PbsGroupTypeClassifier())->classify('Group::UnknownFutureType'));
    }
}
