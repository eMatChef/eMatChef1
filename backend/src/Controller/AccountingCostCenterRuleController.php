<?php

namespace App\Controller;

use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Entity\AccountingCostCenter;
use App\Entity\AccountingCostCenterRule;
use App\Entity\Department;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/accounting/cost-center-rules', name: 'api_accounting_cost_center_rules_')]
class AccountingCostCenterRuleController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(string $departmentId): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $deptRef = $this->entityManager->getReference(Department::class, $departmentId);
        $rows = $this->entityManager->getRepository(AccountingCostCenterRule::class)
            ->createQueryBuilder('r')
            ->innerJoin('r.costCenter', 'cc')
            ->where('r.department = :d')
            ->setParameter('d', $deptRef)
            ->orderBy('r.sourceKind', 'ASC')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $r) {
            if ($r instanceof AccountingCostCenterRule) {
                $out[] = $this->serialize($r);
            }
        }

        return new JsonResponse($out);
    }

    #[Route('', name: 'upsert', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function upsert(string $departmentId, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $dept = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$dept) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $sourceKind = trim((string) ($data['source_kind'] ?? ''));
        if (!in_array($sourceKind, AccountingCostCenterRule::SOURCE_KINDS, true)) {
            return new JsonResponse(['error' => 'Ungültiger source_kind'], 400);
        }

        $ccId = trim((string) ($data['cost_center_id'] ?? ''));
        $cc = $this->entityManager->find(AccountingCostCenter::class, $ccId);
        if (!$cc || $cc->getDepartment()->getId() !== $departmentId) {
            return new JsonResponse(['error' => 'Kostenstelle nicht gefunden'], 400);
        }

        $existing = $this->entityManager->getRepository(AccountingCostCenterRule::class)
            ->findOneBy(['department' => $dept, 'sourceKind' => $sourceKind]);

        if ($existing instanceof AccountingCostCenterRule) {
            $rule = $existing;
        } else {
            $rule = new AccountingCostCenterRule();
            $rule->setId(IdGenerator::generate13Unique($this->entityManager, AccountingCostCenterRule::class, 'kr'));
            $rule->setDepartment($dept);
            $rule->setSourceKind($sourceKind);
            $this->entityManager->persist($rule);
        }

        $rule->setCostCenter($cc);
        $rule->setDefaultEntryType($this->optionalEntryType($data['default_entry_type'] ?? null));
        $rule->setDefaultPaymentMethod($this->optionalPaymentMethod($data['default_payment_method'] ?? null));
        $rule->touchUpdatedAt();
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($rule));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $departmentId, string $id): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $rule = $this->entityManager->find(AccountingCostCenterRule::class, $id);
        if (!$rule || $rule->getDepartment()->getId() !== $departmentId) {
            return new JsonResponse(['error' => 'Regel nicht gefunden'], 404);
        }

        $this->entityManager->remove($rule);
        $this->entityManager->flush();

        return new JsonResponse(['ok' => true]);
    }

    private function optionalEntryType(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (string) $value;

        return in_array($v, \App\Entity\AccountingBooking::ENTRY_TYPES, true) ? $v : null;
    }

    private function optionalPaymentMethod(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (string) $value;

        return in_array($v, \App\Entity\AccountingBooking::PAYMENT_METHODS, true) ? $v : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AccountingCostCenterRule $r): array
    {
        return [
            'id' => $r->getId(),
            'department_id' => $r->getDepartment()->getId(),
            'source_kind' => $r->getSourceKind(),
            'cost_center_id' => $r->getCostCenter()->getId(),
            'cost_center_name' => $r->getCostCenter()->getName(),
            'default_entry_type' => $r->getDefaultEntryType(),
            'default_payment_method' => $r->getDefaultPaymentMethod(),
            'created_at' => $r->getCreatedAt()->format('c'),
            'updated_at' => $r->getUpdatedAt()->format('c'),
        ];
    }
}
