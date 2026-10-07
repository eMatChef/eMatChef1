<?php

namespace App\Repository;

use App\Entity\Profile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Profile::class);
    }

    /** Primary-Adresse ohne Rücksicht auf Groß-/Kleinschreibung (Altbestände sind nicht durchgehend normalisiert). */
    public function findOneByEmailInsensitive(string $email): ?Profile
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return $this->createQueryBuilder('p')
            ->where('LOWER(p.email) = :email')
            ->setParameter('email', $email)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
