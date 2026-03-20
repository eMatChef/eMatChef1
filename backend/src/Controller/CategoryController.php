<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Department;
use App\Entity\User;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/categories', name: 'api_categories_')]
class CategoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Liste aller Kategorien für ein Department
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): JsonResponse
    {
        $departmentId = $request->query->get('department_id');
        
        if (!$departmentId) {
            return new JsonResponse(['error' => 'department_id ist erforderlich'], 400);
        }

        $categories = $this->entityManager->getRepository(Category::class)
            ->createQueryBuilder('c')
            ->where('c.departmentId = :departmentId')
            ->setParameter('departmentId', $departmentId)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Zähle Materialien pro Kategorie
        $materialCounts = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(m.category) as category_id, COUNT(m.id) as material_count')
            ->from('App\Entity\MaterialItem', 'm')
            ->where('m.departmentId = :departmentId')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.category IS NOT NULL')
            ->groupBy('m.category')
            ->setParameter('departmentId', $departmentId)
            ->getQuery()
            ->getResult();

        $countMap = [];
        foreach ($materialCounts as $row) {
            $countMap[$row['category_id']] = (int)$row['material_count'];
        }

        $result = [];
        foreach ($categories as $category) {
            $result[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
                'parent_id' => $category->getParentId(),
                'sort_order' => $category->getSortOrder(),
                'material_count' => $countMap[$category->getId()] ?? 0
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Einzelne Kategorie laden
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $id): JsonResponse
    {
        $category = $this->entityManager->getRepository(Category::class)->find($id);

        if (!$category) {
            return new JsonResponse(['error' => 'Kategorie nicht gefunden'], 404);
        }

        return new JsonResponse([
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'parent_id' => $category->getParentId(),
            'department_id' => $category->getDepartmentId(),
            'sort_order' => $category->getSortOrder()
        ]);
    }

    /**
     * Neue Kategorie erstellen
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['department_id']) || !isset($data['name'])) {
            return new JsonResponse(['error' => 'department_id und name sind erforderlich'], 400);
        }

        // Department prüfen
        $department = $this->entityManager->getRepository(Department::class)
            ->find($data['department_id']);
        if (!$department) {
            return new JsonResponse(['error' => 'Department nicht gefunden'], 404);
        }

        try {
            $category = new Category();
            $category->setId(IdGenerator::generate()); // ID manuell generieren
            $category->setDepartment($department);
            $category->setName($data['name']);
            
            if (isset($data['description'])) {
                $category->setDescription($data['description']);
            }
            
            // Parent-Kategorie
            if (isset($data['parent_id']) && $data['parent_id']) {
                $parent = $this->entityManager->getRepository(Category::class)
                    ->find($data['parent_id']);
                if ($parent && $parent->getDepartmentId() === $department->getId()) {
                    $category->setParent($parent);
                }
            }
            
            if (isset($data['sort_order'])) {
                $category->setSortOrder((int)$data['sort_order']);
            }

            $this->entityManager->persist($category);
            $this->entityManager->flush();

            return new JsonResponse([
                'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
                'parent_id' => $category->getParentId(),
                'department_id' => $category->getDepartmentId(),
                'sort_order' => $category->getSortOrder(),
                'material_count' => 0
            ], 201);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Erstellen der Kategorie: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kategorie aktualisieren
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $category = $this->entityManager->getRepository(Category::class)->find($id);
        
        if (!$category) {
            return new JsonResponse(['error' => 'Kategorie nicht gefunden'], 404);
        }

        $data = json_decode($request->getContent(), true);

        try {
            if (isset($data['name'])) {
                $category->setName($data['name']);
            }
            
            if (isset($data['description'])) {
                $category->setDescription($data['description']);
            }
            
            if (array_key_exists('parent_id', $data)) {
                if ($data['parent_id']) {
                    $parent = $this->entityManager->getRepository(Category::class)
                        ->find($data['parent_id']);
                    if ($parent && $parent->getDepartmentId() === $category->getDepartmentId()) {
                        $category->setParent($parent);
                    }
                } else {
                    $category->setParent(null);
                }
            }
            
            if (isset($data['sort_order'])) {
                $category->setSortOrder((int)$data['sort_order']);
            }

            $category->updateTimestamps();
            $this->entityManager->flush();

            return new JsonResponse([
                'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
                'parent_id' => $category->getParentId(),
                'department_id' => $category->getDepartmentId(),
                'sort_order' => $category->getSortOrder()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Aktualisieren der Kategorie: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kategorie löschen
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $id): JsonResponse
    {
        $category = $this->entityManager->getRepository(Category::class)->find($id);
        
        if (!$category) {
            return new JsonResponse(['error' => 'Kategorie nicht gefunden'], 404);
        }

        // Prüfe ob Materialien in dieser Kategorie existieren
        $materialCount = $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from('App\Entity\MaterialItem', 'm')
            ->where('m.categoryId = :categoryId')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('categoryId', $id)
            ->getQuery()
            ->getSingleScalarResult();

        if ($materialCount > 0) {
            return new JsonResponse([
                'error' => "Diese Kategorie enthält noch $materialCount Materialien und kann nicht gelöscht werden"
            ], 400);
        }

        // Prüfe ob Unter-Kategorien existieren
        $childCount = $this->entityManager->getRepository(Category::class)
            ->count(['parentId' => $id]);

        if ($childCount > 0) {
            return new JsonResponse([
                'error' => "Diese Kategorie hat noch $childCount Unter-Kategorien und kann nicht gelöscht werden"
            ], 400);
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
