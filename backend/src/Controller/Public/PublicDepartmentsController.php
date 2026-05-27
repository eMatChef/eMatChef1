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
 * Oeffentliche Department-Suche fuer Registrierung (ohne JWT).
 */
#[Route('/api/public/organisations/{orgId}/departments', name: 'api_public_org_departments_')]
class PublicDepartmentsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepartmentBreadcrumbBuilder $breadcrumbBuilder,
    ) {
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(string $orgId, Request $request): JsonResponse
    {
        $org = $this->resolveVisibleOrganisation($orgId);
        if (!$org) {
            return new JsonResponse(['error' => 'Organisation nicht gefunden'], 404);
        }

        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) < 2) {
            return new JsonResponse([]);
        }

        $departments = $this->entityManager->getRepository(Department::class)
            ->createQueryBuilder('d')
            ->where('d.organisationId = :orgId')
            ->andWhere('LOWER(d.name) LIKE :q OR d.id LIKE :qExact')
            ->setParameter('orgId', $org->getId())
            ->setParameter('q', '%' . mb_strtolower($query) . '%')
            ->setParameter('qExact', '%' . strtoupper($query) . '%')
            ->orderBy('d.name', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $deptMap = $this->breadcrumbBuilder->loadDepartmentMapForOrganisation($org->getId());
        $result = [];
        foreach ($departments as $department) {
            $result[] = $this->serializeDepartmentSearch($department, $org->getName(), $deptMap);
        }

        return new JsonResponse($result);
    }

    #[Route('/{departmentId}/breadcrumb', name: 'breadcrumb', methods: ['GET'])]
    public function breadcrumb(string $orgId, string $departmentId): JsonResponse
    {
        $org = $this->resolveVisibleOrganisation($orgId);
        if (!$org) {
            return new JsonResponse(['error' => 'Organisation nicht gefunden'], 404);
        }

        /** @var Department|null $department */
        $department = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$department || $department->getOrganisationId() !== $org->getId()) {
            return new JsonResponse(['error' => 'Abteilung nicht gefunden'], 404);
        }

        return new JsonResponse([
            'segments' => $this->breadcrumbBuilder->buildForDepartment($department),
        ]);
    }

    /** Root-Abteilungen (ohne parent) fuer Auswahl als uebergeordnete Abteilung. */
    #[Route('/parents', name: 'parents', methods: ['GET'])]
    public function parentDepartments(string $orgId): JsonResponse
    {
        $org = $this->resolveVisibleOrganisation($orgId);
        if (!$org) {
            return new JsonResponse(['error' => 'Organisation nicht gefunden'], 404);
        }

        $departments = $this->entityManager->getRepository(Department::class)
            ->createQueryBuilder('d')
            ->where('d.organisationId = :orgId')
            ->andWhere('d.parentId IS NULL')
            ->setParameter('orgId', $org->getId())
            ->orderBy('d.name', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($departments as $department) {
            $childCount = (int) $this->entityManager->getRepository(Department::class)
                ->count(['parentId' => $department->getId()]);
            $result[] = [
                'id' => $department->getId(),
                'name' => $department->getName(),
                'has_children' => $childCount > 0,
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * @param array<string, Department> $departmentMap
     *
     * @return array<string, mixed>
     */
    private function serializeDepartmentSearch(Department $department, string $organisationName, array $departmentMap): array
    {
        return [
            'id' => $department->getId(),
            'name' => $department->getName(),
            'organisation_id' => $department->getOrganisationId(),
            'organisation_name' => $organisationName,
            'parent_id' => $department->getParentId(),
            'breadcrumb' => $this->breadcrumbBuilder->buildFromMap($department, $departmentMap),
        ];
    }

    private function resolveVisibleOrganisation(string $orgId): ?Organisation
    {
        $org = $this->entityManager->getRepository(Organisation::class)->find($orgId);
        if (!$org || !OrganisationUserPickerFilter::isVisibleForUserPickers($org)) {
            return null;
        }

        return $org;
    }
}
