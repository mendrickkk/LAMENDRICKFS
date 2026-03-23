<?php

namespace App\EventListener;

use App\Entity\Users;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $token = $this->tokenStorage->getToken();
        
        // If user is authenticated and is staff, redirect to login
        if ($token && $token->getUser() instanceof Users) {
            $user = $token->getUser();
            $roles = $user->getRoles();
            
            // If user is staff (not admin), redirect to their appropriate page
            if (in_array('ROLE_STAFF', $roles) && !in_array('ROLE_ADMIN', $roles)) {
                // Only show error message if they're trying to access an admin page (not during normal login flow)
                $path = $request->getPathInfo();
                if (str_starts_with($path, '/admin') && $path !== '/admin/profile' && $path !== '/login-success') {
                    // Add flash message if session is available
                    if ($request->hasSession()) {
                        $session = $request->getSession();
                        $session->getFlashBag()->add('error', 'Access denied. Staff members do not have permission to access admin pages.');
                    }
                }
                
                // Redirect staff to products page (their dashboard) instead of login
                $productsUrl = $this->urlGenerator->generate('app_product_index');
                return new RedirectResponse($productsUrl);
            }
        }

        // For other cases, return null to let Symfony handle it normally (show 403 page)
        return null;
    }
}

