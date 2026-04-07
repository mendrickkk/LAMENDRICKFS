<?php

namespace App\Controller;

use App\Service\EmailVerificationService;
use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailVerificationController extends AbstractController
{
    #[Route('/verify-email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        EmailVerificationService $emailVerificationService
    ): Response {
        $token = $request->query->get('token');

        if (!$token) {
            $this->addFlash('error', 'Verification token is missing.');
            return $this->redirectToRoute('app_signup');
        }

        $user = $emailVerificationService->verifyToken($token);

        if (!$user) {
            $this->addFlash('error', 'Invalid or expired verification token.');
            return $this->redirectToRoute('app_signup');
        }

        $this->addFlash('success', 'Your email has been verified! You can now log in.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify-email/resend', name: 'app_email_verify_resend', methods: ['POST'])]
    public function resendVerification(
        Request $request,
        EmailVerificationService $emailVerificationService,
        EntityManagerInterface $entityManager,
    ): Response {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('resend_verification', $csrfToken)) {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');

            return $this->redirectToRoute('app_login');
        }

        $identifier = trim((string) $request->request->get('identifier', ''));
        if ($identifier === '') {
            $this->addFlash('error', 'Email is required.');

            return $this->redirectToRoute('app_login');
        }

        /** @var Users|null $user */
        $user = $entityManager->getRepository(Users::class)->findOneBy(['email' => $identifier]);

        // Avoid account enumeration: don't reveal whether email exists.
        if ($user instanceof Users && !$user->isVerified()) {
            $user->setVerificationToken($emailVerificationService->generateVerificationToken());
            $entityManager->flush();

            $verificationUrl = $this->generateUrl(
                'app_verify_email',
                ['token' => $user->getVerificationToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $emailVerificationService->sendVerificationEmail($user, $verificationUrl);
        }

        $this->addFlash('success', 'We sent a verification link to your email. Please verify to activate your account.');

        return $this->redirectToRoute('app_login');
    }
}