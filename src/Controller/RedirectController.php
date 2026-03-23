<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RedirectController extends AbstractController
{
    #[Route('/login-success', name: 'app_login_success')]
    public function loginSuccess(): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Check user role and redirect accordingly
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles)) {
            return $this->redirectToRoute('app_admin');
        }

        if (in_array('ROLE_STAFF', $roles)) {
            // Staff should land on the dashboard
            return $this->redirectToRoute('app_admin');
        }

        return $this->redirectToRoute('app_client');
    }
}
