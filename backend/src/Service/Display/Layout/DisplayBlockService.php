<?php

namespace App\Service\Display\Layout;

use App\Entity\DepartmentDisplayBlock;
use App\Entity\User;
use App\Service\Display\DepartmentDisplayScreenService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Eigene Bausteine eines Grossanlasses: speichern, umbenennen, löschen. Inhalte laufen durch denselben Validator wie
 * Layouts (feste Widget-Schemas, begrenzte Verschachtelung, kein HTML/JavaScript). Ein Baustein gehört genau einem
 * Department; Zugriff hat nur, wer dessen Infoscreens verwalten darf (prüft der Controller).
 */
final class DisplayBlockService
{
    public const MAX_BLOCKS = 100;
    public const MAX_NAME = 80;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DisplayLayoutService $layoutService,
        private DisplayLayoutValidator $validator,
        private DepartmentDisplayScreenService $screenService,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(string $departmentId): array
    {
        $this->requireEnabled($departmentId);
        $rows = $this->entityManager->getRepository(DepartmentDisplayBlock::class)->findBy(['departmentId' => $departmentId], ['name' => 'ASC']);

        return array_map($this->serialize(...), $rows);
    }

    /**
     * @param mixed $content
     *
     * @return array<string, mixed>
     */
    public function create(string $departmentId, User $user, string $name, mixed $content, mixed $width, mixed $height): array
    {
        $this->requireEnabled($departmentId);
        $name = $this->cleanName($name);
        $count = $this->entityManager->getRepository(DepartmentDisplayBlock::class)->count(['departmentId' => $departmentId]);
        if ($count >= self::MAX_BLOCKS) {
            throw new DisplayLayoutException(409, 'too_many_blocks', 'Es sind bereits zu viele eigene Bausteine gespeichert.');
        }

        $checked = $this->validator->validateBlock($content, $this->layoutService->contextOf($departmentId));
        if ($checked['content'] === null) {
            throw new DisplayLayoutException(422, 'block_invalid', 'Der Baustein ist ungültig.', $checked['errors']);
        }
        $normalized = $checked['content'];

        $block = new DepartmentDisplayBlock(
            $this->screenService->generateUnusedId(DepartmentDisplayBlock::class, 'dbl', 'id'),
            $departmentId,
            $name,
            match ($normalized['type']) {
                'group' => DepartmentDisplayBlock::KIND_GROUP,
                'rotation' => DepartmentDisplayBlock::KIND_ROTATION,
                default => DepartmentDisplayBlock::KIND_WIDGET,
            },
            $normalized,
            $this->size($width, DisplayLayoutValidator::COLUMNS, 6),
            $this->size($height, DisplayLayoutValidator::MAX_ROWS, 4),
            $user->getId(),
        );
        $this->entityManager->persist($block);
        $this->entityManager->flush();

        return $this->serialize($block);
    }

    /**
     * @return array<string, mixed>
     */
    public function rename(string $departmentId, string $blockId, string $name): array
    {
        $block = $this->find($departmentId, $blockId);
        $block->rename($this->cleanName($name));
        $this->entityManager->flush();

        return $this->serialize($block);
    }

    public function delete(string $departmentId, string $blockId): void
    {
        $block = $this->find($departmentId, $blockId);
        $this->entityManager->remove($block);
        $this->entityManager->flush();
    }

    private function find(string $departmentId, string $blockId): DepartmentDisplayBlock
    {
        $this->requireEnabled($departmentId);
        $block = $this->entityManager->getRepository(DepartmentDisplayBlock::class)->find($blockId);
        // Department-Isolation: Bausteine anderer Departments sind nie sichtbar (404 statt 403).
        if (!$block instanceof DepartmentDisplayBlock || $block->getDepartmentId() !== $departmentId) {
            throw new DisplayLayoutException(404, 'block_not_found', 'Baustein nicht gefunden.');
        }

        return $block;
    }

    private function requireEnabled(string $departmentId): void
    {
        if (!$this->layoutService->isEnabledFor($departmentId)) {
            throw new DisplayLayoutException(409, 'layout_not_available', 'Layouts sind für dieses Department noch nicht verfügbar.');
        }
    }

    private function cleanName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > self::MAX_NAME) {
            throw new DisplayLayoutException(422, 'name_invalid', 'Der Name muss 1 bis ' . self::MAX_NAME . ' Zeichen lang sein.');
        }

        return $name;
    }

    private function size(mixed $value, int $max, int $fallback): int
    {
        return \is_int($value) && $value >= 1 && $value <= $max ? $value : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DepartmentDisplayBlock $block): array
    {
        return [
            'id' => $block->getId(),
            'name' => $block->getName(),
            'kind' => $block->getKind(),
            'content' => $block->getContent(),
            'default_size' => [$block->getDefaultW(), $block->getDefaultH()],
            'created_by_user_id' => $block->getCreatedByUserId(),
            'created_at' => $block->getCreatedAt()->format('c'),
            'updated_at' => $block->getUpdatedAt()->format('c'),
        ];
    }
}
