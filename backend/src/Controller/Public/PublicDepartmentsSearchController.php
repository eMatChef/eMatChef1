<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\Department;
use App\Entity\Organisation;
use App\Service\DepartmentBreadcrumbBuilder;
use App\Service\OrganisationUserPickerFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Organisationsuebergreifende Abteilungssuche fuer die Registrierung.
 */
#[Route('/api/public/departments', name: 'api_public_departments_')]
class PublicDepartmentsSearchController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentBreadcrumbBuilder $breadcrumbBuilder,
    ) {
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) < 2) {
            return new JsonResponse(['in_organisation' => [], 'other_organisations' => []]);
        }

        $preferredOrgId = trim((string) $request->query->get('preferred_organisation_id', ''));
        $qLower = mb_strtolower($query);
        $qExact = '%' . strtoupper($query) . '%';
        $qLike = '%' . $qLower . '%';

        $visibleOrgIds = $this->visibleOrganisationIds();
        if ($visibleOrgIds === []) {
            return new JsonResponse(['in_organisation' => [], 'other_organisations' => []]);
        }

        $departments = $this->entityManager->getRepository(Department::class)
            ->createQueryBuilder('d')
            ->innerJoin('d.organisation', 'o')
            ->addSelect('o')
            ->where('d.organisationId IN (:orgIds)')
            ->andWhere('LOWER(d.name) LIKE :q OR d.id LIKE :qExact')
            ->setParameter('orgIds', $visibleOrgIds)
            ->setParameter('q', $qLike)
            ->setParameter('qExact', $qExact)
            ->setMaxResults(40)
            ->getQuery()
            ->getResult();

        /** @var array<string, Department> $departmentById */
        $departmentById = [];
        foreach ($departments as $department) {
            if ($department instanceof Department) {
                $departmentById[$department->getId()] = $department;
            }
        }

        $scored = [];
        foreach ($departmentById as $department) {
            $org = $department->getOrganisation();
            $nameLower = mb_strtolower($department->getName());
            $score = 0;
            if ($nameLower === $qLower) {
                $score += 100;
            } elseif (str_starts_with($nameLower, $qLower)) {
                $score += 60;
            } elseif (str_contains($nameLower, $qLower)) {
                $score += 30;
            }
            if ($preferredOrgId !== '' && $department->getOrganisationId() === $preferredOrgId) {
                $score += 50;
            }

            $scored[] = [
                'score' => $score,
                'row' => [
                    'id' => $department->getId(),
                    'name' => $department->getName(),
                    'organisation_id' => $department->getOrganisationId(),
                    'organisation_name' => $org->getName(),
                    'parent_id' => $department->getParentId(),
                ],
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $mapsByOrg = $this->breadcrumbBuilder->loadDepartmentMapsByOrganisation(array_values($departmentById));

        $inOrg = [];
        $other = [];
        foreach ($scored as $item) {
            $department = $departmentById[$item['row']['id']] ?? null;
            if (!$department instanceof Department) {
                continue;
            }
            $orgId = $department->getOrganisationId();
            $deptMap = $mapsByOrg[$orgId] ?? [];
            $row = $item['row'];
            $row['breadcrumb'] = $this->breadcrumbBuilder->buildFromMap($department, $deptMap);

            if ($preferredOrgId !== '' && $row['organisation_id'] === $preferredOrgId) {
                if (\count($inOrg) < 12) {
                    $inOrg[] = $row;
                }
            } elseif (\count($other) < 12) {
                $other[] = $row;
            }
        }

        return new JsonResponse([
            'in_organisation' => $inOrg,
            'other_organisations' => $other,
        ]);
    }

    /** @return list<string> */
    private function visibleOrganisationIds(): array
    {
        $organisations = $this->entityManager->getRepository(Organisation::class)->findAll();
        $ids = [];
        foreach ($organisations as $org) {
            if (OrganisationUserPickerFilter::isVisibleForUserPickers($org)) {
                $ids[] = $org->getId();
            }
        }

        return $ids;
    }
}
