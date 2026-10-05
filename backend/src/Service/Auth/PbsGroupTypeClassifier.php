<?php

declare(strict_types=1);

namespace App\Service\Auth;

/**
 * PBS structure model: Root is ignored, Bund → Organisation, Kantonalverband/Region/Abteilung → Department,
 * sections below the Abteilung → Group.
 */
final class PbsGroupTypeClassifier
{
    /** Technical hitobito root; never an eMatChef object. */
    public const IGNORED = 'ignored';
    public const ORGANISATION = 'organisation';
    public const DEPARTMENT = 'department';
    public const GROUP = 'group';

    private const TYPES = [
        'Group::Root' => self::IGNORED,
        'Group::Bund' => self::ORGANISATION,
        'Group::Kantonalverband' => self::DEPARTMENT,
        'Group::Region' => self::DEPARTMENT,
        'Group::Abteilung' => self::DEPARTMENT,
        'Group::Biber' => self::GROUP,
        'Group::Woelfe' => self::GROUP,
        'Group::Pfadi' => self::GROUP,
        'Group::Pio' => self::GROUP,
        'Group::Rover' => self::GROUP,
        'Group::Pta' => self::GROUP,
        'Group::Silverscouts' => self::GROUP,
    ];

    public function classify(string $externalType): ?string
    {
        return self::TYPES[$externalType] ?? null;
    }
}
