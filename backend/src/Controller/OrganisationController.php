<?php

namespace App\Controller;

use App\Config\LanguageConfig;
use App\Entity\Organisation;
use App\Entity\User;
use App\Repository\OrganisationRepository;
use App\Service\Admin\AdminCapabilityChecker;
use App\Service\OrganisationUserPickerFilter;
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
        private EntityManagerInterface $entityManager,
        private LanguageConfig $languageConfig,
        private AdminCapabilityChecker $adminCapabilityChecker,
    ) {}

    private function requireUser(): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }

        return $user;
    }

    /**
     * Lädt alle Organisationen
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(): JsonResponse
    {
        $currentUser = $this->requireUser();
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        $accessibleOrgIds = null;
        if ($this->adminCapabilityChecker->hasGlobalAdminRole($currentUser)
            && !$this->adminCapabilityChecker->isSuperAdmin($currentUser)) {
            $accessibleOrgIds = $this->adminCapabilityChecker->getAccessibleOrganisationIds($currentUser);
        }

        $organisations = $this->organisationRepository->findAll();

        $result = [];
        foreach ($organisations as $org) {
            if (!OrganisationUserPickerFilter::isVisibleForUserPickers($org)) {
                continue;
            }
            if (\is_array($accessibleOrgIds) && !\in_array($org->getId(), $accessibleOrgIds, true)) {
                continue;
            }
            $result[] = [
                'id' => $org->getId(),
                'name' => $org->getName(),
                'allowed_languages' => $org->getAllowedLanguages(),
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
        $currentUser = $this->requireUser();
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        $organisation = $this->organisationRepository->find($id);
        
        if (!$organisation) {
            return new JsonResponse(['error' => 'Organisation not found'], 404);
        }

        if (
            !$this->adminCapabilityChecker->can($currentUser, 'organisations.view')
            && !$this->adminCapabilityChecker->canAccessOrganisation($currentUser, $organisation->getId())
        ) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }

        return new JsonResponse([
            'id' => $organisation->getId(),
            'name' => $organisation->getName(),
            'allowed_languages' => $organisation->getAllowedLanguages(),
        ]);
    }

    /**
     * Erstellt eine neue Organisation
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $currentUser = $this->requireUser();
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }
        if (!$this->adminCapabilityChecker->can($currentUser, 'organisations.create')) {
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
            $organisation->setAllowedLanguages($this->languageConfig->normalizeAllowedLanguages(isset($data['allowed_languages']) && is_array($data['allowed_languages']) ? $data['allowed_languages'] : null));

            $this->entityManager->persist($organisation);
            $this->entityManager->flush();

            // Prüfe ob ID generiert wurde
            if (!$organisation->getId()) {
                return new JsonResponse(['error' => 'ID konnte nicht generiert werden'], 500);
            }

            return new JsonResponse([
                'id' => $organisation->getId(),
                'name' => $organisation->getName(),
                'allowed_languages' => $organisation->getAllowedLanguages(),
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
        $currentUser = $this->requireUser();
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }
        if (!$this->adminCapabilityChecker->can($currentUser, 'organisations.edit')) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }
        
        $organisation = $this->organisationRepository->find($id);
        
        if (!$organisation) {
            return new JsonResponse(['error' => 'Organisation not found'], 404);
        }

        if (!$this->adminCapabilityChecker->canAccessOrganisation($currentUser, $organisation->getId())) {
            return new JsonResponse(['error' => 'Zugriff verweigert'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name']) && !empty($data['name'])) {
            $organisation->setName($data['name']);
        }
        if (array_key_exists('allowed_languages', $data)) {
            $organisation->setAllowedLanguages($this->languageConfig->normalizeAllowedLanguages(is_array($data['allowed_languages']) ? $data['allowed_languages'] : null));
        }
        $organisation->updateTimestamps();

        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $organisation->getId(),
            'name' => $organisation->getName(),
            'allowed_languages' => $organisation->getAllowedLanguages(),
        ]);
    }
}
