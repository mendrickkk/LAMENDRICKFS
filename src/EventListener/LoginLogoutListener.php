<?php

namespace App\EventListener;

use App\Entity\Users;
use App\Service\ActivityLogService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class LoginLogoutListener
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {
    }

    #[AsEventListener(event: SecurityEvents::INTERACTIVE_LOGIN)]
    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        
        if ($user instanceof Users) {
            $this->activityLogService->logLogin($user);
        }
    }

    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if ($token) {
            $user = $token->getUser();
            if ($user instanceof Users) {
                $this->activityLogService->logLogout($user);
            }
        }
    }
}

