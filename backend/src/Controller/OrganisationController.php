<?php

namespace App\Controller;

use App\Entity\Organisation;
use App\Repository\OrganisationRepository;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/organisations', name: 'api_organisations_')]
class OrganisationController extends AbstractController
{
    public function __construct(
        private OrganisationRepository $organisationRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Lädt alle Organisationen
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(): JsonResponse
    {
        $organisations = $this->organisationRepository->findAll();

        $result = [];
        foreach ($organisations as $org) {
            $result[] = [
                'id' => $org->getId(),
                'name' => $org->getName()
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Lädt eine einzelne Organisation
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get(string $id): JsonResponse
    {
        $organisation = $this->organisationRepository->find($id);
        
        if (!$organisation) {
            return new JsonResponse(['error' => 'Organisation not found'], 404);
        }

        return new JsonResponse([
            'id' => $organisation->getId(),
            'name' => $organisation->getName()
        ]);
    }

    /**
     * Erstellt eine neue Organisation
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Prüfe auf ROLE_ORGANISATIONSCHEF oder ROLE_SUPERADMIN
        if (!$this->isGranted('ROLE_ORGANISATIONSCHEF') && !$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }
        
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['name']) || empty($data['name'])) {
            return new JsonResponse(['error' => 'Name ist erforderlich'], 400);
        }

        try {
            // Neue Organisation erstellen
            $organisation = new Organisation();
            
            // ID muss VOR persist() gesetzt werden (GeneratedValue strategy: 'NONE')
            $organisation->setId(IdGenerator::generateUnique($this->entityManager, Organisation::class));
            $organisation->setName($data['name']);

            $this->entityManager->persist($organisation);
            $this->entityManager->flush();

            // Prüfe ob ID generiert wurde
            if (!$organisation->getId()) {
                return new JsonResponse(['error' => 'ID konnte nicht generiert werden'], 500);
            }

            return new JsonResponse([
                'id' => $organisation->getId(),
                'name' => $organisation->getName()
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Fehler beim Erstellen der Organisation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aktualisiert eine Organisation
     */
    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        // Prüfe auf ROLE_ORGANISATIONSCHEF oder ROLE_SUPERADMIN
        if (!$this->isGranted('ROLE_ORGANISATIONSCHEF') && !$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }
        
        $organisation = $this->organisationRepository->find($id);
        
        if (!$organisation) {
            return new JsonResponse(['error' => 'Organisation not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name']) && !empty($data['name'])) {
            $organisation->setName($data['name']);
            $organisation->updateTimestamps();
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $organisation->getId(),
            'name' => $organisation->getName()
        ]);
    }
}
