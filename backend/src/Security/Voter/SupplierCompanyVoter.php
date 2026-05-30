<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\SupplierCompany;
use App\Entity\User;
use App\Service\Supplier\SupplierCompanyAccessService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, SupplierCompany|string>
 */
class SupplierCompanyVoter extends Voter
{
    public const ACCESS = 'supplier_company.access';

    public function __construct(
        private SupplierCompanyAccessService $accessService,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute !== self::ACCESS) {
            return false;
        }

        return $subject instanceof SupplierCompany || (\is_string($subject) && $subject !== '');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $companyId = $subject instanceof SupplierCompany
            ? (string) $subject->getId()
            : (string) $subject;

        return $this->accessService->canAccessActiveCompany($user, $companyId);
    }
}
