<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ProfileRepository;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private ProfileRepository $profileRepository
    ) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Fall 1: Ist es eine E-Mail? (enthält @)
        if (str_contains($identifier, '@')) {
            // Login Flow: E-Mail → Profile → User
            $profile = $this->profileRepository->findOneBy(['email' => $identifier]);
            
            if (!$profile) {
                throw new UserNotFoundException(sprintf('User with email "%s" not found.', $identifier));
            }

            $user = $this->userRepository->findOneBy(['profileId' => $profile->getId()]);
            
            if (!$user) {
                throw new UserNotFoundException(sprintf('User with email "%s" not found.', $identifier));
            }

            if (!$user->isEmailVerified()) {
                throw new CustomUserMessageAuthenticationException('Bitte bestaetige zuerst deine E-Mail-Adresse.');
            }

            return $user;
        }
        
        // Fall 2: JWT Flow: profileId → User
        $user = $this->userRepository->findOneBy(['profileId' => $identifier]);
        
        if (!$user) {
            throw new UserNotFoundException(sprintf('User with profileId "%s" not found.', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $refreshedUser = $this->userRepository->findOneBy(['id' => $user->getId()]);
        
        if (!$refreshedUser) {
            throw new UserNotFoundException(sprintf('User with id "%s" not found.', $user->getId()));
        }
        
        return $refreshedUser;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
