<?php

namespace App\Controller;

use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/test', name: 'api_test_')]
class TestController extends AbstractController
{
    public function __construct(
        private ProfileRepository $profileRepository,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function testLogin(): JsonResponse
    {
        try {
            $email = 'admin@example.com';
            $password = 'password';

            // Profile finden
            $profile = $this->profileRepository->findOneBy(['email' => $email]);
            if (!$profile) {
                return new JsonResponse(['error' => 'Profile not found'], 404);
            }

            // User finden
            $user = $this->userRepository->findOneBy(['profileId' => $profile->getId()]);
            if (!$user) {
                return new JsonResponse(['error' => 'User not found'], 404);
            }

            // Passwort prüfen
            $isValid = $this->passwordHasher->isPasswordValid($user, $password);
            if (!$isValid) {
                return new JsonResponse(['error' => 'Invalid password'], 401);
            }

            return new JsonResponse([
                'success' => true,
                'user_id' => $user->getId(),
                'profile_id' => $profile->getId(),
                'email' => $profile->getEmail()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
