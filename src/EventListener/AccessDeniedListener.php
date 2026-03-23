<?php

namespace App\EventListener;

use App\Entity\Users;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AccessDeniedListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Only handle AccessDeniedException from Security component
        if (!$exception instanceof AccessDeniedException) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        
        // If user is authenticated and is staff, redirect to login
        if ($token && $token->getUser() instanceof Users) {
            $user = $token->getUser();
            $roles = $user->getRoles();
            
            // If user is staff (not admin), redirect to login
            if (in_array('ROLE_STAFF', $roles) && !in_array('ROLE_ADMIN', $roles)) {
                $request = $this->requestStack->getCurrentRequest();
                
                // Add flash message if session is available
                if ($request && $request->hasSession()) {
                    $session = $request->getSession();
                    $session->getFlashBag()->add('error', 'Access denied. Staff members do not have permission to access admin pages.');
                }
                
                // Redirect to login page
                $loginUrl = $this->urlGenerator->generate('app_login');
                $event->setResponse(new RedirectResponse($loginUrl));
                return;
            }
        }

        // For other cases (unauthenticated users, admins, etc.), let Symfony handle it normally
        // This will show the default 403 page or custom error page
    }
}

