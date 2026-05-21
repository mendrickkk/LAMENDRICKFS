<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Registers POST /api/login so the API firewall json_login authenticator can handle the request.
 * JWT + user JSON are returned by JWTAuthenticationSuccessHandler, not this controller.
 */
final class ApiLoginController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => 'Invalid credentials.',
        ], 401);
    }
}
