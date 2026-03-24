<?php

namespace App\Controller;

use App\Entity\Users;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class EmailVerificationController extends AbstractController
{
    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyUserEmail(
        Request $request,
        EmailVerificationService $emailVerificationService,
    ): Response {
        $token = (string) $request->query->get('token', '');

        if ($token === '') {
            $this->addFlash('error', 'Invalid verification link.');
            return $this->redirectToRoute('app_login');
        }

        $user = $emailVerificationService->verifyToken($token);

        if (!$user) {
            $this->addFlash('error', 'Invalid or expired verification token.');
            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Your email has been verified! You can now log in.');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify/email/resend', name: 'app_email_verify_resend', methods: ['POST'])]
    public function resend(
        Request $request,
        EntityManagerInterface $em,
        EmailVerificationService $service,
        #[Autowire(service: 'limiter.verify_email_resend')] RateLimiterFactory $rateLimiter,
    ): Response {
        if (!$this->isCsrfTokenValid('resend_verification', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid request. Please try again.');
            return $this->redirectToRoute('app_login');
        }

        $identifier = trim((string) $request->request->get('identifier', ''));
        if ($identifier === '') {
            $this->addFlash('error', 'Please enter your email or username.');
            return $this->redirectToRoute('app_login');
        }

        // Anti-spam: 1 resend per window for the same identifier.
        $limiterKey = mb_strtolower($identifier);
        $limit = $rateLimiter->create($limiterKey)->consume(1);
        if (!$limit->isAccepted()) {
            $this->addFlash('error', 'Please wait a moment before requesting another verification email.');
            return $this->redirectToRoute('app_login');
        }

        /** @var Users|null $user */
        $user = $em->getRepository(Users::class)->findOneBy(['email' => $identifier])
            ?? $em->getRepository(Users::class)->findOneBy(['username' => $identifier]);

        if (!$user) {
            $this->addFlash('success', 'If an account exists, we sent a verification email. Please check your inbox.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->isVerified()) {
            $this->addFlash('success', 'Your email is already verified.');
            return $this->redirectToRoute('app_login');
        }

        // Generate a fresh token so the user gets a new link.
        $user->setVerificationToken($service->generateVerificationToken());
        $em->flush();

        $service->sendVerificationEmail($user);

        $this->addFlash('success', 'Verification email sent. Please check your inbox.');
        return $this->redirectToRoute('app_login');
    }
}