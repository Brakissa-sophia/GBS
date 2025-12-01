<?php 

namespace App\Security;

use App\Entity\User as AppUser;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }

        // ✅ Vérifier si le compte est activé
        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte n\'est pas encore activé. Veuillez vérifier votre boîte e-mail pour le lien d\'activation.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // if (!$user instanceof AppUser) {
        //     return;
        // }

        // // user account is expired, the user may be notified
        // if ($user->isExpired()) {
        //     throw new AccountExpiredException('...');
        // }

        // if (!\in_array('foo', $token->getRoleNames())) {
        //     throw new AccessDeniedException('...');
        // }
    }
}