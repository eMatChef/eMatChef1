<?php

namespace App\Service\Display\Layout;

use App\Entity\Department;
use App\Entity\DepartmentDisplayDevice;
use App\Entity\DepartmentDisplayLayoutRevision;
use App\Entity\DepartmentDisplayScreen;
use App\Entity\User;
use App\Service\Display\DepartmentDisplayScreenService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Entwurf, Vorschau, Veröffentlichung und Revisionen der Infoscreen-Layouts.
 *
 * Der Entwurf liegt am Screen und wird nie an Geräte ausgeliefert. Eine Veröffentlichung erzeugt eine unveränderliche
 * Revision (inklusive aller Varianten und Seiten); Geräte verwenden ausschliesslich die aktive Revision.
 */
final class DisplayLayoutService
{
    /** Kontexte, in denen Layouts verwendet werden. Normale Departments behalten vorerst die Checkbox-Konfiguration. */
    public const ENABLED_CONTEXTS = [DisplayWidgetRegistry::CONTEXT_GROSSANLASS];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DisplayLayoutValidator $validator,
        private DisplayLayoutTemplateCatalog $templates,
        private DisplayWidgetRegistry $registry,
        private DisplayDeviceProfileService $profiles,
        private DepartmentDisplayScreenService $screenService,
        private ?DisplayBlockCatalog $blocks = null,
    ) {
        $this->blocks ??= new DisplayBlockCatalog();
    }

    public function contextOf(string $departmentId): string
    {
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);

        return $department?->isGrossanlass() ? DisplayWidgetRegistry::CONTEXT_GROSSANLASS : DisplayWidgetRegistry::CONTEXT_DEPARTMENT;
    }

    public function isEnabledFor(string $departmentId): bool
    {
        return \in_array($this->contextOf($departmentId), self::ENABLED_CONTEXTS, true);
    }

    /**
     * Katalog für den Editor: Widgets und Vorlagen des Kontexts (Fachwidgets nie vermischt).
     *
     * @return array<string, mixed>
     */
    public function catalog(string $departmentId): array
    {
        $context = $this->contextOf($departmentId);
        $enabled = $this->isEnabledFor($departmentId);

        return [
            'enabled' => $enabled,
            'context' => $context,
            'limits' => [
                'columns' => DisplayLayoutValidator::COLUMNS,
                'max_columns' => DisplayLayoutValidator::MAX_COLUMNS,
                'default_rows' => DisplayLayoutValidator::DEFAULT_ROWS,
                'max_rows' => DisplayLayoutValidator::MAX_ROWS,
                'max_depth' => DisplayLayoutValidator::MAX_DEPTH,
                'max_variants' => DisplayLayoutValidator::MAX_VARIANTS,
                'max_pages' => DisplayLayoutValidator::MAX_PAGES,
                'max_items' => DisplayLayoutValidator::MAX_ITEMS,
                'max_steps' => DisplayLayoutValidator::MAX_STEPS,
                'min_interval_sec' => DisplayLayoutValidator::MIN_INTERVAL,
                'max_interval_sec' => DisplayLayoutValidator::MAX_INTERVAL,
                'default_interval_sec' => DisplayLayoutValidator::DEFAULT_INTERVAL,
            ],
            'widgets' => $enabled ? $this->registry->forContext($context) : [],
            'templates' => $enabled ? $this->templates->forContext($context) : [],
            'blocks' => $enabled ? $this->blocks->forContext($context) : [],
        ];
    }

    // --- Entwurf -------------------------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function state(DepartmentDisplayScreen $screen): array
    {
        $this->requireEnabled($screen);
        $draft = $screen->getLayoutDraft();
        $published = $this->publishedRevision($screen);

        return [
            'draft' => $draft,
            'draft_version' => $screen->getLayoutDraftVersion(),
            'draft_updated_at' => $screen->getLayoutDraftUpdatedAt()?->format('c'),
            'origin' => $screen->getLayoutDraftOrigin(),
            'published' => $published ? $this->serializeRevision($published) : null,
            'has_unpublished_changes' => $draft !== null && ($published === null || $this->canonical($draft) !== $this->canonical($published->getLayout())),
        ];
    }

    /**
     * Ausgangslayout aus einer Vorlage ("blank" = leeres Layout) oder aus dem Layout eines anderen Screens desselben
     * Departments. Das Ergebnis ist eine vollständig unabhängige, normalisierte Kopie.
     *
     * @return array{layout: array<string, mixed>, origin: array<string, mixed>}
     */
    public function seed(string $departmentId, ?string $templateId, ?string $sourceScreenId): array
    {
        if (!$this->isEnabledFor($departmentId)) {
            throw new DisplayLayoutException(409, 'layout_not_available', 'Layouts sind für dieses Department noch nicht verfügbar.');
        }
        $context = $this->contextOf($departmentId);

        if ($sourceScreenId !== null && $sourceScreenId !== '') {
            $source = $this->entityManager->getRepository(DepartmentDisplayScreen::class)->find($sourceScreenId);
            if (!$source instanceof DepartmentDisplayScreen || $source->getDepartmentId() !== $departmentId) {
                throw new DisplayLayoutException(404, 'source_not_found', 'Quell-Infoscreen nicht gefunden.');
            }
            $layout = $source->getLayoutDraft() ?? $this->publishedRevision($source)?->getLayout();
            if ($layout === null) {
                throw new DisplayLayoutException(409, 'source_has_no_layout', 'Der Quell-Infoscreen hat noch kein Layout.');
            }
            $origin = ['screen_id' => $source->getId()];
        } else {
            $template = $this->templates->find($templateId ?: DisplayLayoutTemplateCatalog::BLANK);
            if ($template === null || !\in_array($context, $template['contexts'], true)) {
                throw new DisplayLayoutException(404, 'template_not_found', 'Vorlage nicht gefunden.');
            }
            $layout = $template['layout'];
            $origin = ['template_id' => $template['id'], 'template_version' => $template['version']];
        }

        return ['layout' => $this->validate($layout, $context, false), 'origin' => $origin];
    }

    /**
     * Entwurf aus Vorlage oder anderem Screen setzen. Ersetzt einen vorhandenen Entwurf nur bei passender Version.
     *
     * @return array<string, mixed>
     */
    public function initialize(DepartmentDisplayScreen $screen, ?string $templateId, ?string $sourceScreenId, int $expectedVersion): array
    {
        $seed = $this->seed($screen->getDepartmentId(), $templateId, $sourceScreenId);

        return $this->writeDraft($screen, $seed['layout'], $expectedVersion, $seed['origin']);
    }

    /**
     * @param mixed $layout
     *
     * @return array<string, mixed>
     */
    public function saveDraft(DepartmentDisplayScreen $screen, mixed $layout, int $expectedVersion): array
    {
        $this->requireEnabled($screen);
        $normalized = $this->validate($layout, $this->contextOf($screen->getDepartmentId()), false);

        return $this->writeDraft($screen, $normalized, $expectedVersion, $screen->getLayoutDraftOrigin());
    }

    /**
     * Prüft den Entwurf wie bei der Veröffentlichung (strenge Regeln), ohne zu veröffentlichen.
     *
     * @return list<array{path: string, code: string, message: string}>
     */
    public function validateDraftForPublish(DepartmentDisplayScreen $screen): array
    {
        $this->requireEnabled($screen);
        $draft = $screen->getLayoutDraft();
        if ($draft === null) {
            return [['path' => 'layout', 'code' => 'no_draft', 'message' => 'Es gibt noch keinen Entwurf.']];
        }

        return $this->validator->validate($draft, $this->contextOf($screen->getDepartmentId()), true)['errors'];
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(DepartmentDisplayScreen $screen, int $expectedVersion, ?User $user): array
    {
        $this->requireEnabled($screen);
        if ($screen->isRevoked()) {
            throw new DisplayLayoutException(409, 'screen_revoked', 'Der Infoscreen ist widerrufen.');
        }
        $this->assertVersion($screen, $expectedVersion);
        $draft = $screen->getLayoutDraft();
        if ($draft === null) {
            throw new DisplayLayoutException(409, 'no_draft', 'Es gibt noch keinen Entwurf.');
        }
        $normalized = $this->validate($draft, $this->contextOf($screen->getDepartmentId()), true);
        $this->createRevision($screen, $normalized, $user, null);

        return $this->state($screen);
    }

    // --- Revisionen ----------------------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public function listRevisions(DepartmentDisplayScreen $screen): array
    {
        $this->requireEnabled($screen);
        $rows = $this->entityManager->getRepository(DepartmentDisplayLayoutRevision::class)
            ->findBy(['screenId' => $screen->getId()], ['number' => 'DESC']);

        return array_map(fn (DepartmentDisplayLayoutRevision $r) => $this->serializeRevision($r), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function revisionDetail(DepartmentDisplayScreen $screen, int $number): ?array
    {
        $this->requireEnabled($screen);
        $revision = $this->entityManager->getRepository(DepartmentDisplayLayoutRevision::class)
            ->findOneBy(['screenId' => $screen->getId(), 'number' => $number]);

        return $revision ? $this->serializeRevision($revision) + ['layout' => $revision->getLayout()] : null;
    }

    /**
     * Frühere Revision wiederherstellen: ihr Layout wird zum Entwurf; optional sofort als neue Revision veröffentlicht.
     * Die alte Revision bleibt unverändert, es entsteht nie eine Überschreibung der Historie.
     *
     * @return array<string, mixed>
     */
    public function restore(DepartmentDisplayScreen $screen, int $number, int $expectedVersion, bool $publish, ?User $user): array
    {
        $this->requireEnabled($screen);
        $revision = $this->entityManager->getRepository(DepartmentDisplayLayoutRevision::class)
            ->findOneBy(['screenId' => $screen->getId(), 'number' => $number]);
        if (!$revision instanceof DepartmentDisplayLayoutRevision) {
            throw new DisplayLayoutException(404, 'revision_not_found', 'Revision nicht gefunden.');
        }
        $context = $this->contextOf($screen->getDepartmentId());
        $normalized = $this->validate($revision->getLayout(), $context, false);
        $this->writeDraft($screen, $normalized, $expectedVersion, ['restored_from_revision' => $number]);

        if ($publish) {
            $strict = $this->validate($normalized, $context, true);
            $this->createRevision($screen, $strict, $user, $number);
        }

        return $this->state($screen);
    }

    // --- Auslieferung --------------------------------------------------------------------------------------

    /**
     * Layout-Teil des Anzeige-Payloads. `published` liefert ausschliesslich die aktive Revision (öffentliche Geräte);
     * `draft` ist nur für die authentifizierte Vorschau gedacht. Null = Layouts sind für den Kontext nicht aktiv.
     *
     * @return array<string, mixed>|null
     */
    public function payload(DepartmentDisplayScreen $screen, ?DepartmentDisplayDevice $device, string $source = 'published', ?string $variantOverride = null): ?array
    {
        if (!$this->isEnabledFor($screen->getDepartmentId())) {
            return null;
        }
        $profile = $device?->getDisplayProfile();

        if ($source === 'draft') {
            $draft = $screen->getLayoutDraft();
            if ($draft === null) {
                return ['state' => 'unpublished', 'source' => 'draft'];
            }
            $layout = $draft;
            $meta = ['source' => 'draft', 'revision' => null];
        } else {
            $revision = $this->publishedRevision($screen);
            if ($revision === null) {
                return ['state' => 'unpublished', 'source' => 'published'];
            }
            $layout = $revision->getLayout();
            $meta = ['source' => 'published', 'revision' => $revision->getNumber(), 'published_at' => $revision->getPublishedAt()->format('c')];
        }

        $variantIds = array_column($layout['variants'] ?? [], 'id');
        $variantId = $variantOverride !== null && \in_array($variantOverride, $variantIds, true)
            ? $variantOverride
            : $this->profiles->selectVariant($layout, $profile);

        return [
            'state' => 'ready',
            'layout' => $layout,
            'variant_id' => $variantId,
            'font_scale' => $this->profiles->fontScale($profile),
        ] + $meta;
    }

    // --- intern --------------------------------------------------------------------------------------------

    private function requireEnabled(DepartmentDisplayScreen $screen): void
    {
        if (!$this->isEnabledFor($screen->getDepartmentId())) {
            throw new DisplayLayoutException(409, 'layout_not_available', 'Layouts sind für dieses Department noch nicht verfügbar.');
        }
    }

    private function assertVersion(DepartmentDisplayScreen $screen, int $expectedVersion): void
    {
        if ($screen->getLayoutDraftVersion() !== $expectedVersion) {
            throw new DisplayLayoutException(409, 'version_conflict', 'Der Entwurf wurde inzwischen geändert.', [], ['current_version' => $screen->getLayoutDraftVersion()]);
        }
    }

    /**
     * @param mixed $layout
     *
     * @return array<string, mixed>
     */
    private function validate(mixed $layout, string $context, bool $strict): array
    {
        $result = $this->validator->validate($layout, $context, $strict);
        if ($result['layout'] === null) {
            throw new DisplayLayoutException(422, 'layout_invalid', 'Das Layout ist ungültig.', $result['errors']);
        }

        return $result['layout'];
    }

    /**
     * Schreibt den Entwurf atomar mit Versionsprüfung (kein stilles Überschreiben bei gleichzeitiger Bearbeitung).
     *
     * @param array<string, mixed> $layout
     * @param array<string, mixed>|null $origin
     *
     * @return array<string, mixed>
     */
    private function writeDraft(DepartmentDisplayScreen $screen, array $layout, int $expectedVersion, ?array $origin): array
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $updated = $this->entityManager->getConnection()->executeStatement(
            'UPDATE department_display_screen SET layout_draft = ?, layout_draft_origin = ?, layout_draft_version = layout_draft_version + 1,'
            . ' layout_draft_updated_at = ?, updated_at = ? WHERE id = ? AND layout_draft_version = ?',
            [json_encode($layout), $origin === null ? null : json_encode($origin), $now, $now, $screen->getId(), $expectedVersion],
        );
        if ($updated !== 1) {
            $this->entityManager->refresh($screen);
            throw new DisplayLayoutException(409, 'version_conflict', 'Der Entwurf wurde inzwischen geändert.', [], ['current_version' => $screen->getLayoutDraftVersion()]);
        }
        $this->entityManager->refresh($screen);

        return $this->state($screen);
    }

    /**
     * @param array<string, mixed> $layout
     */
    private function createRevision(DepartmentDisplayScreen $screen, array $layout, ?User $user, ?int $restoredFrom): DepartmentDisplayLayoutRevision
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $next = (int) $connection->fetchOne('SELECT COALESCE(MAX(number), 0) FROM department_display_layout_revision WHERE screen_id = ?', [$screen->getId()]) + 1;
            $revision = new DepartmentDisplayLayoutRevision(
                $this->screenService->generateUnusedId(DepartmentDisplayLayoutRevision::class, 'dlr', 'id'),
                $screen->getId(),
                $next,
                $layout,
                $user?->getId(),
                $restoredFrom,
            );
            $this->entityManager->persist($revision);
            $screen->setLayoutPublishedNumber($next);
            $screen->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();
            $connection->commit();

            return $revision;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    private function publishedRevision(DepartmentDisplayScreen $screen): ?DepartmentDisplayLayoutRevision
    {
        $number = $screen->getLayoutPublishedNumber();
        if ($number === null) {
            return null;
        }

        return $this->entityManager->getRepository(DepartmentDisplayLayoutRevision::class)
            ->findOneBy(['screenId' => $screen->getId(), 'number' => $number]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRevision(DepartmentDisplayLayoutRevision $revision): array
    {
        return [
            'number' => $revision->getNumber(),
            'published_at' => $revision->getPublishedAt()->format('c'),
            'published_by_user_id' => $revision->getPublishedByUserId(),
            'restored_from_number' => $revision->getRestoredFromNumber(),
        ];
    }

    /**
     * @param array<string, mixed> $layout
     */
    private function canonical(array $layout): string
    {
        return (string) json_encode($layout);
    }
}
