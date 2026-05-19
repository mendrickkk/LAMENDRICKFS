<?php

namespace App\Security;

use App\Entity\Users;
use App\Security\Exception\CustomerOnlyApiException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * API firewall user checks. Web admin/staff login uses {@see UserChecker} on the main firewall.
 */
final class ApiUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Users) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Your account has been disabled. Please contact an administrator.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof Users) {
            return;
        }

        if (!$user->isCustomer()) {
            throw new CustomerOnlyApiException();
        }
    }
}
