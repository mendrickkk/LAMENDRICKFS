<?php

namespace App\Security;

use App\Entity\Users;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Users) {
            return;
        }

        // Check if user is active
        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Your account has been disabled. Please contact an administrator.');
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Please verify your email address before logging in. Check your inbox for the verification link.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Additional checks after authentication if needed
    }
}
