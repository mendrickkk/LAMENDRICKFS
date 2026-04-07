<?php

namespace App\Controller;

use App\Entity\Users;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api')]
final class ApiRegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailVerificationService $emailVerificationService,
    ) {
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $username = $data['username'] ?? null;
        $email = $data['email'] ?? null;
        $plainPassword = $data['password'] ?? null;

        if (!is_string($username) || $username === '' || !is_string($email) || $email === '' || !is_string($plainPassword) || $plainPassword === '') {
            return $this->json([
                'success' => false,
                'message' => 'Username, email, and password are required',
            ], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid email address',
            ], 400);
        }

        if (strlen($plainPassword) < 6) {
            return $this->json([
                'success' => false,
                'message' => 'Password must be at least 6 characters long',
            ], 400);
        }

        $existingUser = $this->entityManager->getRepository(Users::class)->findOneBy(['username' => $username]);
        if ($existingUser) {
            return $this->json([
                'success' => false,
                'message' => 'Username already exists',
            ], 409);
        }

        $existingEmail = $this->entityManager->getRepository(Users::class)->findOneBy(['email' => $email]);
        if ($existingEmail) {
            return $this->json([
                'success' => false,
                'message' => 'Email already registered',
            ], 409);
        }

        $user = new Users();
        $user->setUsername($username);
        $user->setEmail($email);

        // Default role for API registration
        $user->setRole('ROLE_CLIENT');
        $user->setIsActive(true);
        $user->setIsVerified(false);

        $user->setVerificationToken($this->emailVerificationService->generateVerificationToken());

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Best-effort: registration should succeed even if mail fails.
        try {
            $verificationUrl = $this->generateUrl(
                'app_verify_email',
                ['token' => $user->getVerificationToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
        } catch (\Throwable) {
            // Swallow mail errors; user can request resend later.
        }

        return $this->json([
            'success' => true,
            'message' => 'Registration successful. Please verify your email address.',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
                'roles' => $user->getRoles(),
            ],
        ], 201);
    }
}

