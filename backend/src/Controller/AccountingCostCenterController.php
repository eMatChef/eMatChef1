<?php

namespace App\Controller;

use App\Entity\AccountingCostCenter;
use App\Controller\Trait\AccountingMwOrDcTrait;
use App\Entity\Department;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/departments/{departmentId}/accounting/cost-centers', name: 'api_accounting_cost_centers_')]
class AccountingCostCenterController extends AbstractController
{
    use AccountingMwOrDcTrait;

    public function __construct(
        private EntityManagerInterface $entityManager
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
        $rows = $this->entityManager->getRepository(AccountingCostCenter::class)
            ->createQueryBuilder('c')
            ->where('c.department = :d')
            ->setParameter('d', $deptRef)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $c) {
            $out[] = $this->serialize($c);
        }

        return new JsonResponse($out);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(string $departmentId, Request $request): JsonResponse
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
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse(['error' => 'Name erforderlich'], 400);
        }

        $c = new AccountingCostCenter();
        // ks + YYYY + hex7 — zentral wie Material-Batches; Präfix „cc“ ist für Kombi-Komponenten reserviert
        $c->setId(IdGenerator::generate13Unique($this->entityManager, AccountingCostCenter::class, 'ks'));
        $c->setDepartment($dept);
        $c->setName(mb_substr($name, 0, 255));
        $c->setAccountCode(isset($data['account_code']) ? mb_substr(trim((string) $data['account_code']), 0, 32) : null);
        if ($c->getAccountCode() === '') {
            $c->setAccountCode(null);
        }
        $c->setDescription(isset($data['description']) ? trim((string) $data['description']) : null);
        if ($c->getDescription() === '') {
            $c->setDescription(null);
        }
        $c->setSortOrder(isset($data['sort_order']) ? (int) $data['sort_order'] : 0);

        $this->entityManager->persist($c);
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($c), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $departmentId, string $id, Request $request): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $c = $this->entityManager->getRepository(AccountingCostCenter::class)->find($id);
        if (!$c || $c->getDepartment()->getId() !== $departmentId) {
            return new JsonResponse(['error' => 'Kostenstelle nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                return new JsonResponse(['error' => 'Name erforderlich'], 400);
            }
            $c->setName(mb_substr($name, 0, 255));
        }
        if (array_key_exists('account_code', $data)) {
            $code = trim((string) $data['account_code']);
            $c->setAccountCode($code === '' ? null : mb_substr($code, 0, 32));
        }
        if (array_key_exists('description', $data)) {
            $desc = trim((string) $data['description']);
            $c->setDescription($desc === '' ? null : $desc);
        }
        if (array_key_exists('sort_order', $data)) {
            $c->setSortOrder((int) $data['sort_order']);
        }

        $c->touchUpdatedAt();
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($c));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $departmentId, string $id): JsonResponse
    {
        $deny = $this->assertAccountingMwOrDc($this->entityManager, $departmentId);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        $c = $this->entityManager->getRepository(AccountingCostCenter::class)->find($id);
        if (!$c || $c->getDepartment()->getId() !== $departmentId) {
            return new JsonResponse(['error' => 'Kostenstelle nicht gefunden'], 404);
        }

        $this->entityManager->remove($c);
        $this->entityManager->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AccountingCostCenter $c): array
    {
        return [
            'id' => $c->getId(),
            'department_id' => $c->getDepartmentId(),
            'name' => $c->getName(),
            'account_code' => $c->getAccountCode(),
            'description' => $c->getDescription(),
            'sort_order' => $c->getSortOrder(),
            'created_at' => $c->getCreatedAt()->format('c'),
            'updated_at' => $c->getUpdatedAt()->format('c'),
        ];
    }
}
