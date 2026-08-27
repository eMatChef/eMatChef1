<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassRound;
use App\Entity\ActivityGrossanlassWishLine;
use App\Entity\ActivityGrossanlassWishResponse;
use App\Entity\ActivityGrossanlassWishResponseValue;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\User;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

class GrossanlassAnswerCollectorService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
    ) {}

    /**
     * @return array{
     *     company_tips: list<array<string, mixed>>,
     *     free_ideas: list<array<string, mixed>>,
     *     material_rounds: list<array{id: string, name: string}>
     * }
     */
    public function listInbox(Department $department, User $user): array
    {
        $this->assertManage($department, $user);

        return [
            'company_tips' => $this->listPending($department, ActivityGrossanlassRound::PURPOSE_COMPANY_TIP),
            'free_ideas' => $this->listPending($department, ActivityGrossanlassRound::PURPOSE_FREE),
            'material_rounds' => $this->listOpenMaterialRounds($department),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function importPendingTips(Department $department, User $user): array
    {
        $this->assertManage($department, $user);
        $created = [];
        foreach ($this->loadPendingWishes($department, ActivityGrossanlassRound::PURPOSE_COMPANY_TIP) as $wish) {
            $created[] = $this->createInquiryFromWish($department, $user, $wish, []);
        }
        $this->entityManager->flush();

        return $created;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function assignToInquiry(Department $department, User $user, string $wishId, array $data): array
    {
        $this->assertManage($department, $user);
        $wish = $this->findPendingWish($department, $wishId);
        $purpose = $wish->getRound()->getFormPurpose();
        if (!GrossanlassCollectorDecision::canToInquiry($purpose, $wish->getStatus())) {
            throw new \InvalidArgumentException('Diese Eingabe kann nicht als Firmenvorschlag übernommen werden');
        }
        $inquiry = $this->createInquiryFromWish($department, $user, $wish, $data);
        $this->entityManager->flush();

        return $inquiry;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function assignToMaterial(Department $department, User $user, string $wishId, array $data): array
    {
        $this->assertManage($department, $user);
        $wish = $this->findPendingWish($department, $wishId);
        if (!GrossanlassCollectorDecision::canToMaterial($wish->getRound()->getFormPurpose(), $wish->getStatus())) {
            throw new \InvalidArgumentException('Nur freie Eingaben können in den Materialbedarf');
        }

        $round = $this->resolveMaterialRound($department, $data['target_round_id'] ?? null);
        $clone = $this->cloneAsMaterialWish($wish, $round, $data);
        $this->markHandled($wish, ActivityGrossanlassWishLine::STATUS_ACCEPTED, $user);
        $this->entityManager->persist($clone);
        $this->entityManager->flush();

        return ['wish_id' => $clone->getId(), 'round_id' => $round->getId()];
    }

    /**
     * @return array<string, mixed>
     */
    public function discard(Department $department, User $user, string $wishId): array
    {
        $this->assertManage($department, $user);
        $wish = $this->findPendingWish($department, $wishId);
        if (!GrossanlassCollectorDecision::canDiscard($wish->getRound()->getFormPurpose(), $wish->getStatus())) {
            throw new \InvalidArgumentException('Diese Eingabe kann nicht verworfen werden');
        }
        $this->markHandled($wish, ActivityGrossanlassWishLine::STATUS_DISCARDED, $user);
        $this->entityManager->flush();

        return ['id' => $wish->getId(), 'status' => $wish->getStatus()];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listPending(Department $department, string $purpose): array
    {
        $rows = [];
        foreach ($this->loadPendingWishes($department, $purpose) as $wish) {
            $rows[] = $this->serializeInboxItem($wish);
        }

        return $rows;
    }

    /**
     * @return list<ActivityGrossanlassWishLine>
     */
    private function loadPendingWishes(Department $department, string $purpose): array
    {
        $qb = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)
            ->createQueryBuilder('w')
            ->innerJoin('w.round', 'r')
            ->innerJoin('r.activity', 'a')
            ->innerJoin('w.group', 'g')
            ->innerJoin('w.createdByUser', 'u')
            ->leftJoin('u.profile', 'p')
            ->addSelect('r', 'g', 'u', 'p')
            ->where('a.departmentId = :departmentId')
            ->andWhere('r.formPurpose = :purpose')
            ->andWhere('w.status = :status')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('purpose', $purpose)
            ->setParameter('status', ActivityGrossanlassWishLine::STATUS_REQUESTED)
            ->orderBy('w.createdAt', 'DESC');

        if ($purpose === ActivityGrossanlassRound::PURPOSE_COMPANY_TIP) {
            $qb->andWhere(
                'NOT EXISTS (
                    SELECT 1 FROM App\Entity\DepartmentGrossanlassInquiry i
                    WHERE i.tipWishId = w.id
                )',
            );
        }

        /** @var list<ActivityGrossanlassWishLine> $lines */
        $lines = $qb->getQuery()->getResult();

        return $lines;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function listOpenMaterialRounds(Department $department): array
    {
        $rounds = $this->entityManager->getRepository(ActivityGrossanlassRound::class)
            ->createQueryBuilder('r')
            ->innerJoin('r.activity', 'a')
            ->where('a.departmentId = :departmentId')
            ->andWhere('r.formPurpose = :purpose')
            ->andWhere('r.status = :status')
            ->setParameter('departmentId', $department->getId())
            ->setParameter('purpose', ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH)
            ->setParameter('status', ActivityGrossanlassRound::STATUS_OPEN)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rounds as $round) {
            if ($round instanceof ActivityGrossanlassRound) {
                $out[] = ['id' => $round->getId(), 'name' => $round->getName()];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function createInquiryFromWish(
        Department $department,
        User $user,
        ActivityGrossanlassWishLine $wish,
        array $data,
    ): array
    {
        $existing = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)
            ->findOneBy(['tipWishId' => $wish->getId()]);
        if ($existing instanceof DepartmentGrossanlassInquiry) {
            throw new \InvalidArgumentException('Dieser Vorschlag ist bereits übernommen');
        }

        $extracted = $this->extractTipFields($wish);
        $inquiry = new DepartmentGrossanlassInquiry();
        $inquiry->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::INQUIRY,
            DepartmentGrossanlassInquiry::class,
        ));
        $inquiry->setDepartment($department);
        $name = trim((string) ($data['name'] ?? $extracted['name']));
        $inquiry->setName($name !== '' ? $name : $extracted['name']);
        $email = strtolower(trim((string) ($data['email'] ?? $extracted['email'])));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Ungültige E-Mail-Adresse');
        }
        $inquiry->setEmail($email);
        $place = trim((string) ($data['place'] ?? ($extracted['place'] !== '' ? $extracted['place'] : $wish->getLocation())));
        $inquiry->setPlace($place);
        $categoryIds = $data['category_ids'] ?? $extracted['categories'];
        if (is_string($categoryIds)) {
            $categoryIds = preg_split('/[,;]+/', $categoryIds) ?: [];
        }
        $ids = [];
        if (is_array($categoryIds)) {
            foreach ($categoryIds as $item) {
                $value = trim((string) $item);
                if ($value !== '') {
                    $ids[] = $value;
                }
            }
        }
        $inquiry->setCategoryIds($ids);
        $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_VORSCHLAG);
        $inquiry->setTipWish($wish);
        $inquiry->setTipFrom($wish->getGroup()->getName());
        $this->entityManager->persist($inquiry);
        $this->markHandled($wish, ActivityGrossanlassWishLine::STATUS_ACCEPTED, $user);

        return [
            'id' => $inquiry->getId(),
            'name' => $inquiry->getName(),
            'email' => $inquiry->getEmail(),
            'place' => $inquiry->getPlace(),
            'category_ids' => $inquiry->getCategoryIds(),
            'status' => $inquiry->getStatus(),
            'tip_from' => $inquiry->getTipFrom(),
            'tip_wish_id' => $inquiry->getTipWishId(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function cloneAsMaterialWish(
        ActivityGrossanlassWishLine $source,
        ActivityGrossanlassRound $round,
        array $data,
    ): ActivityGrossanlassWishLine {
        $label = trim((string) ($data['label'] ?? $source->getLabel()));
        if ($label === '') {
            $label = 'Idee';
        }
        $quantity = (int) ($data['quantity'] ?? $source->getQuantity());
        if ($quantity < 1) {
            $quantity = 1;
        }
        $location = trim((string) ($data['location'] ?? $source->getLocation()));
        $kind = (string) ($data['wish_kind'] ?? $source->getWishKind());
        if (!in_array($kind, [
            ActivityGrossanlassWishLine::KIND_MATERIAL,
            ActivityGrossanlassWishLine::KIND_FAHRZEUG,
            ActivityGrossanlassWishLine::KIND_BEIDES,
        ], true)) {
            $kind = ActivityGrossanlassWishLine::KIND_MATERIAL;
        }

        $answers = $this->answerLines($source);
        $notesParts = array_filter([
            $source->getNotes(),
            $answers !== [] ? implode("\n", $answers) : null,
            'Übernommen aus «' . $source->getRound()->getName() . '»',
        ]);

        $clone = new ActivityGrossanlassWishLine();
        $clone->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::WISH_LINE,
            ActivityGrossanlassWishLine::class,
        ));
        $clone->setRound($round);
        $clone->setGroup($source->getGroup());
        $clone->setWishKind($kind);
        $clone->setLabel($label);
        $clone->setQuantity($quantity);
        $clone->setLocation($location !== '' ? $location : $source->getLocation());
        $clone->setValidFrom(clone $source->getValidFrom());
        $clone->setValidTo(clone $source->getValidTo());
        $clone->setTimeframeNotes($source->getTimeframeNotes());
        $clone->setNotes(implode("\n\n", $notesParts) ?: null);
        $clone->setCreatedByUser($source->getCreatedByUser());
        $clone->setStatus(ActivityGrossanlassWishLine::STATUS_REQUESTED);
        $clone->setLastStage(
            GrossanlassMaterialStage::isFein($round->getMaterialStage())
                ? GrossanlassMaterialStage::FEIN
                : GrossanlassMaterialStage::GROB,
        );

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInboxItem(ActivityGrossanlassWishLine $wish): array
    {
        $profile = $wish->getCreatedByUser()->getProfile();
        $answers = $this->loadAnswers($wish);
        $extracted = $this->extractTipFields($wish);

        return [
            'id' => $wish->getId(),
            'form_purpose' => $wish->getRound()->getFormPurpose(),
            'round_id' => $wish->getRoundId(),
            'round_name' => $wish->getRound()->getName(),
            'group_id' => $wish->getGroupId(),
            'group_name' => $wish->getGroup()->getName(),
            'label' => $wish->getLabel() !== '' ? $wish->getLabel() : $extracted['name'],
            'quantity' => $wish->getQuantity(),
            'location' => $wish->getLocation() !== '' ? $wish->getLocation() : $extracted['place'],
            'notes' => $wish->getNotes(),
            'email' => $extracted['email'],
            'suggested_categories' => $extracted['categories'],
            'answers' => $answers,
            'created_by_name' => $profile ? $profile->getDisplayName() : 'Unbekannt',
            'created_at' => $wish->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function loadAnswers(ActivityGrossanlassWishLine $wish): array
    {
        $out = [];
        foreach ($this->loadValues($wish) as $value) {
            $text = $this->formatValue($value);
            if ($text === '') {
                continue;
            }
            $out[] = [
                'label' => $value->getField()?->getLabel() ?? '',
                'value' => $text,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function answerLines(ActivityGrossanlassWishLine $wish): array
    {
        $lines = [];
        foreach ($this->loadAnswers($wish) as $row) {
            $lines[] = trim($row['label'] . ': ' . $row['value']);
        }

        return $lines;
    }

    /**
     * @return list<ActivityGrossanlassWishResponseValue>
     */
    private function loadValues(ActivityGrossanlassWishLine $wish): array
    {
        $response = $wish->getResponse();
        if ($response === null) {
            return [];
        }

        /** @var list<ActivityGrossanlassWishResponseValue> $values */
        $values = $this->entityManager->getRepository(ActivityGrossanlassWishResponseValue::class)
            ->createQueryBuilder('v')
            ->innerJoin('v.field', 'f')
            ->addSelect('f')
            ->where('v.responseId = :responseId')
            ->setParameter('responseId', $response->getId())
            ->getQuery()
            ->getResult();

        return $values;
    }

    private function formatValue(ActivityGrossanlassWishResponseValue $value): string
    {
        if ($value->getValueJson() !== null) {
            $json = $value->getValueJson();
            if (isset($json['from'], $json['to'])) {
                return (string) $json['from'] . ' – ' . (string) $json['to'];
            }
            $encoded = json_encode($json, JSON_UNESCAPED_UNICODE);

            return is_string($encoded) ? $encoded : '';
        }
        if ($value->getValueNumber() !== null) {
            return (string) $value->getValueNumber();
        }

        return trim((string) ($value->getValueText() ?? ''));
    }

    /**
     * @return array{name: string, email: string, place: string, categories: list<string>}
     */
    private function extractTipFields(ActivityGrossanlassWishLine $wish): array
    {
        $name = trim($wish->getLabel());
        $email = '';
        $place = trim($wish->getLocation());
        $categories = [];
        foreach ($this->loadValues($wish) as $value) {
            $text = $this->formatValue($value);
            if ($text === '') {
                continue;
            }
            $label = mb_strtolower($value->getField()?->getLabel() ?? '');
            if (str_contains($label, 'mail') || str_contains($label, 'kontakt')) {
                if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    $email = strtolower($text);
                } elseif ($email === '' && str_contains($text, '@')) {
                    $email = strtolower($text);
                }
            } elseif (str_contains($label, 'kategorie') || str_contains($label, 'bereich')) {
                $categories[] = $text;
            } elseif ((str_contains($label, 'ort') || str_contains($label, 'place')) && $place === '') {
                $place = $text;
            } elseif ((str_contains($label, 'firma') || str_contains($label, 'titel') || str_contains($label, 'company')) && $name === '') {
                $name = $text;
            } elseif (str_contains($label, 'idee') && $name === '') {
                $name = mb_substr($text, 0, 255);
            }
        }
        if ($name === '') {
            $name = $wish->getRound()->getFormPurpose() === ActivityGrossanlassRound::PURPOSE_FREE
                ? 'Idee'
                : 'Firmenvorschlag';
        }

        return [
            'name' => $name,
            'email' => $email,
            'place' => $place,
            'categories' => $categories,
        ];
    }

    private function findPendingWish(Department $department, string $wishId): ActivityGrossanlassWishLine
    {
        $wish = $this->entityManager->getRepository(ActivityGrossanlassWishLine::class)->find($wishId);
        if (!$wish instanceof ActivityGrossanlassWishLine
            || $wish->getRound()->getActivity()->getDepartmentId() !== $department->getId()
        ) {
            throw new \InvalidArgumentException('Eingabe nicht gefunden');
        }

        return $wish;
    }

    private function resolveMaterialRound(Department $department, mixed $roundId): ActivityGrossanlassRound
    {
        $id = is_string($roundId) ? trim($roundId) : '';
        if ($id !== '') {
            $round = $this->entityManager->getRepository(ActivityGrossanlassRound::class)->find($id);
            if (!$round instanceof ActivityGrossanlassRound
                || $round->getActivity()->getDepartmentId() !== $department->getId()
                || $round->getFormPurpose() !== ActivityGrossanlassRound::PURPOSE_MATERIAL_WISH
            ) {
                throw new \InvalidArgumentException('Materialformular nicht gefunden');
            }

            return $round;
        }

        $open = $this->listOpenMaterialRounds($department);
        if ($open === []) {
            throw new \InvalidArgumentException('Zuerst ein offenes Materialformular anlegen');
        }
        $round = $this->entityManager->getRepository(ActivityGrossanlassRound::class)->find($open[0]['id']);
        if (!$round instanceof ActivityGrossanlassRound) {
            throw new \InvalidArgumentException('Materialformular nicht gefunden');
        }

        return $round;
    }

    private function markHandled(ActivityGrossanlassWishLine $wish, string $status, User $user): void
    {
        $wish->setStatus($status);
        $wish->touchUpdatedAt();
        $response = $wish->getResponse();
        if ($response instanceof ActivityGrossanlassWishResponse) {
            $response->setStatus(
                $status === ActivityGrossanlassWishLine::STATUS_DISCARDED
                    ? ActivityGrossanlassWishResponse::STATUS_DISCARDED
                    : ActivityGrossanlassWishResponse::STATUS_ACCEPTED,
            );
            $response->touchUpdatedAt($user);
        }
    }

    private function assertManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für den Sammler');
        }
    }
}
