<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\Membership;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Event Subscriber: Fügt User & Profile Daten zur JWT Login Response hinzu
 */
class JwtAuthenticationSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ProfileRepository $profileRepository,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private ?LoggerInterface $logger = null
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => ['onAuthenticationSuccess', 0],
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        try {
            $securityUser = $event->getUser();
            
            if (!$securityUser instanceof UserInterface) {
                $this->log('warning', 'No UserInterface found');
                return;
            }
            
            $identifier = $securityUser->getUserIdentifier();
            $this->log('info', 'Processing login for identifier: ' . $identifier);
            
            // DIREKT aus der Entity-Klasse prüfen - $securityUser IST bereits die User-Entity
            // wenn sie UserInterface implementiert und von Doctrine geladen wurde
            $user = null;
            
            // Methode 1: Prüfe ob $securityUser bereits User ist (über Methoden-Check)
            if (method_exists($securityUser, 'getProfileId') && method_exists($securityUser, 'getState')) {
                $user = $securityUser;
                $this->log('info', 'Using securityUser directly as User entity');
            }
            
            // Methode 2: Falls nicht, lade User per ProfileId
            if (!$user) {
                $user = $this->userRepository->findOneByProfileId($identifier);
                $this->log('info', 'Loaded user from repository: ' . ($user ? $user->getId() : 'null'));
            }
            
            if (!$user) {
                $this->log('error', 'Could not find user for identifier: ' . $identifier);
                return;
            }

            // Profile laden
            $profileId = $user->getProfileId();
            $this->log('info', 'Loading profile with ID: ' . $profileId);
            $profile = $this->profileRepository->find($profileId);
            
            if (!$profile) {
                error_log('JwtAuthenticationSuccessSubscriber: Profile not found for user ' . $user->getId());
                return;
            }

            // Response-Daten erweitern
            $data = $event->getData();
            
            $data['user'] = [
                'id' => $user->getId(),
                'state' => $user->getState(),
                'profile_id' => $user->getProfileId()
            ];
            
            $data['profile'] = [
                'id' => $profile->getId(),
                'email' => $profile->getEmail(),
                'first_name' => $profile->getFirstName() ?? null,
                'last_name' => $profile->getLastName() ?? null,
                'nickname' => $profile->getNickname() ?? null,
                'avatar_initials' => $profile->getAvatarInitials() ?? null,
                'pending_email' => $user->getPendingEmail() ?? null,
                'language' => $profile->getLanguage(),
                'roles' => $profile->getRoles(),
                'background_color' => $profile->getBackgroundColor() ?? null,
                'text_color' => $profile->getTextColor() ?? null
            ];
            
            // Memberships laden (mit Department-Relation)
            $memberships = $this->entityManager->getRepository(Membership::class)
                ->createQueryBuilder('m')
                ->innerJoin('m.department', 'd')
                ->addSelect('d')
                ->where('m.userId = :userId')
                ->setParameter('userId', $user->getId())
                ->getQuery()
                ->getResult();

            $departments = [];
            $primaryDepartment = null;
            $primaryOrganisationId = null;

            foreach ($memberships as $m) {
                $department = $m->getDepartment();
                $deptData = [
                    'id' => $department->getId(),
                    'name' => $department->getName(),
                    'organisation_id' => $department->getOrganisationId(),
                    'role' => $m->getRole(),
                    'is_primary' => $m->getIsPrimary()
                ];
                $departments[] = $deptData;

                // Primäres Department ermitteln
                if ($m->getIsPrimary() || !$primaryDepartment) {
                    $primaryDepartment = $deptData;
                    $primaryOrganisationId = $department->getOrganisationId();
                }
            }

            // Falls kein primäres Department, erstes nehmen
            if (!$primaryDepartment && count($departments) > 0) {
                $primaryDepartment = $departments[0];
                $primaryOrganisationId = $departments[0]['organisation_id'];
            }

            $data['departments'] = $departments;
            $data['primary_department'] = $primaryDepartment ? $primaryDepartment['id'] : null;
            $data['last_used_department'] = null; // TODO: Aus User-Settings laden
            
            $event->setData($data);
            $this->log('info', 'Successfully added user/profile data to response');
        } catch (\Exception $e) {
            $this->log('error', 'Exception: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            // Fehler nicht weiterwerfen, damit Login trotzdem funktioniert
        }
    }
    
    private function log(string $level, string $message): void
    {
        $fullMessage = '[JwtAuthSuccess] ' . $message;
        
        if ($this->logger) {
            $this->logger->{$level}($fullMessage);
        }
        
        // Auch in error_log für Docker-Ausgabe
        error_log($fullMessage);
    }
}
