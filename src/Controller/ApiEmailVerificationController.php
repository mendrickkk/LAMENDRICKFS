<?php

namespace App\Controller;

use App\Entity\Users;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
final class ApiEmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerificationService $emailVerificationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;

        if (!is_string($token) || $token === '') {
            return $this->json([
                'success' => false,
                'message' => 'Verification token is required',
            ], 400);
        }

        $user = $this->emailVerificationService->verifyToken($token);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid or expired verification token',
            ], 400);
        }

        return $this->json([
            'success' => true,
            'message' => 'Email verified successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
            ],
        ]);
    }

    #[Route('/resend-verification', name: 'api_resend_verification', methods: ['POST'])]
    public function resendVerification(#[CurrentUser] ?Users $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        if ($user->isVerified()) {
            return $this->json([
                'success' => false,
                'message' => 'Email is already verified',
            ], 400);
        }

        $user->setVerificationToken($this->emailVerificationService->generateVerificationToken());
        $this->entityManager->flush();

        $this->emailVerificationService->sendVerificationEmail($user);

        return $this->json([
            'success' => true,
            'message' => 'Verification email sent successfully',
        ], 200);
    }

    #[Route('/verification-status', name: 'api_verification_status', methods: ['GET'])]
    public function verificationStatus(#[CurrentUser] ?Users $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        return $this->json([
            'success' => true,
            'isVerified' => $user->isVerified(),
            'email' => $user->getEmail(),
        ], 200);
    }
}

