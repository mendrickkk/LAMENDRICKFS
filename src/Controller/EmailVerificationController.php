<?php

namespace App\Controller;

use App\Entity\Users;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Attribute\Route;

final class EmailVerificationController extends AbstractController
{
    #[Route('/verify/email', name: 'app_email_verify', methods: ['GET'])]
    public function verify(
        Request $request,
        EntityManagerInterface $em,
        EmailVerificationService $verificationService,
    ): Response {
        $id = $request->query->getInt('id');
        $expires = $request->query->getInt('expires');
        $token = $request->query->get('token');
        $signature = (string) $request->query->get('signature');

        if (!$id || !$expires || $signature === '' || !is_string($token)) {
            $this->addFlash('error', 'Invalid verification link.');
            return $this->redirectToRoute('app_login');
        }

        if ($expires < time()) {
            $this->addFlash('error', 'This verification link has expired. Please request a new one.');
            return $this->redirectToRoute('app_login');
        }

        if (!$verificationService->isSignatureValid($id, $expires, $token, $signature)) {
            $this->addFlash('error', 'Invalid verification link.');
            return $this->redirectToRoute('app_login');
        }

        /** @var Users|null $user */
        $user = $em->getRepository(Users::class)->find($id);
        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->isVerified()) {
            $this->addFlash('success', 'Your email is already verified.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->getVerificationToken() !== $token) {
            $this->addFlash('error', 'Invalid verification token.');
            return $this->redirectToRoute('app_login');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $em->flush();

        $this->addFlash('success', 'Email verified successfully! You can now log in.');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify/email/resend', name: 'app_email_verify_resend', methods: ['POST'])]
    public function resend(
        Request $request,
        EntityManagerInterface $em,
        EmailVerificationService $service
    ): Response
    {
        if (!$this->isCsrfTokenValid('resend_verification', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid request. Please try again.');
            return $this->redirectToRoute('app_login');
        }

        $identifier = trim((string) $request->request->get('identifier', ''));
        if ($identifier === '') {
            $this->addFlash('error', 'Please enter your email or username.');
            return $this->redirectToRoute('app_login');
        }

        /** @var Users|null $user */
        $user = $em->getRepository(Users::class)->findOneBy(['email' => $identifier])
            ?? $em->getRepository(Users::class)->findOneBy(['username' => $identifier]);

        if (!$user) {
            // Don't reveal whether the account exists
            $this->addFlash('success', 'If an account exists, we sent a verification email. Please check your inbox.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->isVerified()) {
            $this->addFlash('success', 'Your email is already verified.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $service->sendVerificationEmail($user);
        } catch (\Throwable) {
            $this->addFlash('error', 'We could not send the verification email right now. Please try again later.');
            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Verification email sent. Please check your inbox.');
        return $this->redirectToRoute('app_login');
    }
}

