<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
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
     * @return array{renamed: int, skipped: int}
     */
    public function sync(bool $dryRun = false): array
    {
        $manifest = $this->loadManifest();
        $stats = ['renamed' => 0, 'skipped' => 0];

        if (!$dryRun) {
            $this->entityManager->beginTransaction();
        }

        try {
            $category = $this->entityManager->find(Category::class, JsLeihkatalogCatalogService::ORDER_FORM_CATEGORY_ID);

            foreach ($manifest['items'] as $rowIndex => $row) {
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

                $pdfLineNo = $rowIndex + 1;
                if ($material->getNo() !== $pdfLineNo) {
                    if (!$dryRun) {
                        $material->setNo($pdfLineNo);
                    }
                    ++$stats['renamed'];
                }

                if ($category instanceof Category && $material->getCategoryId() !== $category->getId()) {
                    if (!$dryRun) {
                        $material->setCategory($category);
                    }
                    ++$stats['renamed'];
                }

                if ($material->getName() !== $name) {
                    if (!$dryRun) {
                        $material->setName($name);
                    }
                    ++$stats['renamed'];
                }
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

    /** @return array{items: list<array{id: string, name: string}>} */
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
}
