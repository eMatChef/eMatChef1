<?php

declare(strict_types=1);

namespace App\Service\Demo\Organisation;

/** Beschreibung eines vom Seed verwalteten Datensatzes (Anlegen, Finden, Lesen/Schreiben der verwalteten Felder). */
final class ManagedSpec
{
    /**
     * @param \Closure(): object                         $create    baut eine neue Entity (ohne Persist)
     * @param \Closure(string): ?object                  $find      Entity zur ID oder null
     * @param \Closure(object): string                   $idOf
     * @param \Closure(object): array<string, mixed>     $read      aktuelle verwaltete Felder
     * @param \Closure(object, array<string, mixed>): void $write   verwaltete Felder setzen
     * @param array<string, mixed>                       $desired   Sollwerte aus dem Katalog
     * @param \Closure(): ?object|null                   $adopt     vorhandenen, nachweislich eigenen Datensatz liefern; wirft OwnershipConflictException
     * @param \Closure(object): void|null                $afterCreate
     */
    public function __construct(
        public readonly string $seedKey,
        public readonly \Closure $create,
        public readonly \Closure $find,
        public readonly \Closure $idOf,
        public readonly \Closure $read,
        public readonly \Closure $write,
        public readonly array $desired,
        public readonly bool $global = false,
        public readonly ?\Closure $adopt = null,
        public readonly ?\Closure $afterCreate = null,
        /** Darf ein Szenario-Reset auch manuell geänderte Werte auf den Katalog zurücksetzen? (nur department-eigene Datensätze) */
        public readonly bool $resettable = false,
    ) {
    }
}
