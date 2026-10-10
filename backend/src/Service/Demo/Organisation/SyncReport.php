<?php

declare(strict_types=1);

namespace App\Service\Demo\Organisation;

final class SyncReport
{
    public int $created = 0;
    public int $updated = 0;
    public int $unchanged = 0;
    public int $adopted = 0;
    public int $recreated = 0;
    public int $retired = 0;
    /** Reset-Modus: manuell geänderte Werte, die auf den Katalog zurückgesetzt wurden («Seed-Schlüssel (Feld, …)»). */
    /** @var list<string> */
    public array $restored = [];

    /** @var list<string> */
    public array $divergences = [];
    /** @var list<string> */
    public array $conflicts = [];
    /** @var list<string> */
    public array $orphans = [];
    /** @var list<string> */
    public array $warnings = [];

    /** @return list<string> */
    public function notes(): array
    {
        $notes = [];
        foreach ($this->divergences as $d) {
            $notes[] = 'Abweichung (manuell geändert, nicht überschrieben): ' . $d;
        }
        foreach ($this->restored as $r) {
            $notes[] = \App\Service\Demo\Reset\DemoSetupResetter::RESTORED_PREFIX . $r;
        }
        foreach ($this->conflicts as $c) {
            $notes[] = 'Konflikt (nicht übernommen): ' . $c;
        }
        foreach ($this->orphans as $o) {
            $notes[] = 'Verwaist (nicht mehr im Katalog, nicht gelöscht): ' . $o;
        }
        foreach ($this->warnings as $w) {
            $notes[] = 'Hinweis: ' . $w;
        }

        return $notes;
    }

    public function summary(): string
    {
        return sprintf(
            'neu %d, aktualisiert %d, übernommen %d, neu angelegt %d, unverändert %d, entfernt %d, zurückgesetzt %d, Abweichungen %d, Konflikte %d, verwaist %d',
            $this->created, $this->updated, $this->adopted, $this->recreated, $this->unchanged, $this->retired,
            \count($this->restored), \count($this->divergences), \count($this->conflicts), \count($this->orphans),
        );
    }
}
