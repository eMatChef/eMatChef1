<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MaterialItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Gleicht dept_js00000-Katalog mit J+S-PDF-Formularzeilen ab (1 Item pro Zeile).
 */
class JsPdfCatalogSyncService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * @return array{renamed: int, remapped: int, retired: int, skipped: int}
     */
    public function sync(bool $dryRun = false): array
    {
        $manifest = $this->loadManifest();
        $stats = ['renamed' => 0, 'remapped' => 0, 'retired' => 0, 'skipped' => 0];

        if (!$dryRun) {
            $this->entityManager->beginTransaction();
        }

        try {
            foreach ($manifest['remap_before_retire'] as $fromId => $toId) {
                $stats['remapped'] += $this->remapOrderItems((string) $fromId, (string) $toId, $dryRun);
            }

            foreach ($manifest['items'] as $row) {
                $id = (string) ($row['id'] ?? '');
                $name = trim((string) ($row['name'] ?? ''));
                if ($id === '' || $name === '') {
                    ++$stats['skipped'];
                    continue;
                }

                $material = $this->entityManager->find(MaterialItem::class, $id);
                if (!$material instanceof MaterialItem || !$material->getIsJsMaterial()) {
                    ++$stats['skipped'];
                    continue;
                }

                if ($material->getName() !== $name) {
                    if (!$dryRun) {
                        $material->setName($name);
                    }
                    ++$stats['renamed'];
                }
            }

            foreach ($manifest['retire_ids'] as $retireId) {
                $material = $this->entityManager->find(MaterialItem::class, (string) $retireId);
                if (!$material instanceof MaterialItem || $material->getDeletedAt() !== null) {
                    continue;
                }
                if (!$dryRun) {
                    $material->setDeletedAt(new \DateTime());
                }
                ++$stats['retired'];
            }

            if (!$dryRun) {
                $this->entityManager->flush();
                $this->entityManager->commit();
            }
        } catch (\Throwable $e) {
            if (!$dryRun && $this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            throw $e;
        }

        return $stats;
    }

    /** @return array{remap_before_retire: array<string, string>, retire_ids: list<string>, items: list<array{id: string, name: string}>} */
    public function loadManifest(): array
    {
        $path = $this->projectDir . '/data/js-order/pdf_catalog_manifest.json';
        if (!is_file($path)) {
            throw new \RuntimeException('Manifest nicht gefunden: ' . $path);
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!\is_array($data)) {
            throw new \RuntimeException('Manifest ungültig');
        }

        return [
            'remap_before_retire' => \is_array($data['remap_before_retire'] ?? null) ? $data['remap_before_retire'] : [],
            'retire_ids' => \is_array($data['retire_ids'] ?? null) ? array_values($data['retire_ids']) : [],
            'items' => \is_array($data['items'] ?? null) ? $data['items'] : [],
        ];
    }

    /** @return list<string> IDs aus bestellformular_lagersport_trekking_d.pdf (Manifest) */
    public function orderableMaterialIds(): array
    {
        $ids = [];
        foreach ($this->loadManifest()['items'] as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** @return array<string, int> Material-ID → Position im PDF-Formular (0-basiert) */
    public function orderableMaterialSortIndex(): array
    {
        $index = [];
        foreach ($this->loadManifest()['items'] as $i => $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $index[$id] = (int) $i;
            }
        }

        return $index;
    }

    private function remapOrderItems(string $fromId, string $toId, bool $dryRun): int
    {
        $conn = $this->entityManager->getConnection();
        $count = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM activity_js_order_item WHERE material_item_id = :from',
            ['from' => $fromId],
        );

        if ($count > 0 && !$dryRun) {
            $conn->executeStatement(
                'UPDATE activity_js_order_item SET material_item_id = :to WHERE material_item_id = :from',
                ['from' => $fromId, 'to' => $toId],
            );
        }

        return $count;
    }
}
