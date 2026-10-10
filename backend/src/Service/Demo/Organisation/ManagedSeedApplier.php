<?php

declare(strict_types=1);

namespace App\Service\Demo\Organisation;

use App\Service\Demo\Scenario\SeedContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Wendet einen Katalogeintrag idempotent an (Dreiwege-Vergleich Katalog / aktuell / zuletzt vom Seed geschrieben):
 *  - fehlt der Eintrag: anlegen (oder nachweislich eigenen Bestand übernehmen),
 *  - aktuell == Katalog: nichts tun,
 *  - aktuell == zuletzt geschrieben (also nie manuell geändert): auf Katalog aktualisieren,
 *  - sonst manuell geändert: nicht überschreiben, Abweichung melden.
 * Es wird nie gelöscht.
 */
class ManagedSeedApplier
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @param array<string, mixed> $data */
    public static function hash(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function ensure(SeedContext $context, SyncReport $report, ManagedSpec $spec, string $catalogVersion): ?object
    {
        $record = $context->findRecord($spec->seedKey);
        $entity = null;

        if ($record !== null) {
            $entity = ($spec->find)($record->getEntityId());
            if ($entity === null) {
                // Entity wurde gelöscht: fehlendes wird wieder angelegt, der Seed-Schlüssel bleibt gleich.
                $entity = $this->createEntity($spec);
                $record->rebind($entity::class, ($spec->idOf)($entity))->markManaged(self::hash($spec->desired), $catalogVersion);
                ++$report->recreated;

                return $entity;
            }
        } else {
            try {
                $entity = $spec->adopt !== null ? ($spec->adopt)() : null;
            } catch (OwnershipConflictException $e) {
                $report->conflicts[] = $spec->seedKey . ': ' . $e->getMessage();

                return null;
            }
            if ($entity !== null) {
                // Der neue Eintrag ist noch nicht geflusht und über find() nicht sichtbar: Rückgabe verwenden.
                $record = $this->bind($context, $spec, $entity, self::hash(($spec->read)($entity)), $catalogVersion);
                ++$report->adopted;
            } else {
                $entity = $this->createEntity($spec);
                $this->bind($context, $spec, $entity, self::hash($spec->desired), $catalogVersion);
                ++$report->created;
                if ($spec->afterCreate !== null) {
                    ($spec->afterCreate)($entity);
                }

                return $entity;
            }
        }

        $current = ($spec->read)($entity);
        $currentHash = self::hash($current);
        $desiredHash = self::hash($spec->desired);
        $storedHash = $record?->getManagedHash();

        if ($currentHash === $desiredHash) {
            if ($storedHash !== $desiredHash) {
                $record?->markManaged($desiredHash, $catalogVersion);
            }
            ++$report->unchanged;
        } elseif ($storedHash === $currentHash) {
            ($spec->write)($entity, $spec->desired);
            $record?->markManaged($desiredHash, $catalogVersion);
            ++$report->updated;
        } elseif ($context->forcesManaged() && $spec->resettable && !$spec->global) {
            $fields = array_keys(array_filter($spec->desired, static fn ($v, $k) => ($current[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
            ($spec->write)($entity, $spec->desired);
            $record?->markManaged($desiredHash, $catalogVersion);
            $report->restored[] = sprintf('%s (%s)', $spec->seedKey, implode(', ', $fields));
        } else {
            $fields = array_keys(array_filter($spec->desired, static fn ($v, $k) => ($current[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
            $report->divergences[] = sprintf('%s (%s)', $spec->seedKey, implode(', ', $fields));
        }

        return $entity;
    }

    private function createEntity(ManagedSpec $spec): object
    {
        $entity = ($spec->create)();
        ($spec->write)($entity, $spec->desired);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    private function bind(SeedContext $context, ManagedSpec $spec, object $entity, string $hash, string $version): \App\Entity\DemoSeedRecord
    {
        $id = ($spec->idOf)($entity);

        return $spec->global
            ? $context->recordGlobal($spec->seedKey, $entity, $id, $hash, $version)
            : $context->record($spec->seedKey, $entity, $id, $hash, $version);
    }
}
