<?php

declare(strict_types=1);

namespace App\Service\Demo\Scenario;

use App\Entity\Department;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Ein unabhängiges Demo-Szenario mit eigenem Department, eigener Uhr und eigenem Reset.
 *
 * Verträge (siehe docs/demo/SEED-KONZEPT.md §7):
 *  - sync(): additiv und idempotent; überschreibt weder Prozesszustände noch den Uhr-Offset.
 *  - reset(): nur wenn supportsReset(); behält die Department-ID; wird nur vom Runner unter Lock + Transaktion aufgerufen.
 *  - verify(): nur lesend; liefert Verstösse als Texte.
 *  - Ein Szenario arbeitet ausschliesslich über den SeedContext in seinem eigenen Department.
 */
#[AutoconfigureTag(self::TAG)]
interface DemoScenarioInterface
{
    public const TAG = 'app.demo_scenario';

    public function key(): string;

    public function label(): string;

    /** Muss das Szenario-Department ein Grossanlass-Department sein? */
    public function expectsGrossanlass(): bool;

    /** Ob reset() freigegeben ist. Phase 1: für alle echten Szenarien false. */
    public function supportsReset(): bool;

    public function sync(SeedContext $context): SeedResult;

    public function reset(SeedContext $context): SeedResult;

    /** @return list<string> Verstösse; leer = in Ordnung */
    public function verify(SeedContext $context): array;

    /** Ausgangspunkt der Szenario-Uhr oder null (= reale Zeit). */
    public function clockOrigin(Department $department): ?\DateTimeInterface;
}
