<?php

namespace App\Service;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EmailVerificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(MAILER_FROM_ADDRESS)%')]
        private readonly string $mailFrom,
        #[Autowire('%env(MAILER_FROM_NAME)%')]
        private readonly string $mailFromName,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function sendVerificationEmail(Users $user): void
    {
        if (!$user->getEmail()) {
            return;
        }

        if (!$user->getVerificationToken()) {
            $user->setVerificationToken($this->generateVerificationToken());
            $this->entityManager->flush();
        }

        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => (string) $user->getVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, $this->mailFromName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Verify your email address')
            ->htmlTemplate('emails/verification.html.twig')
            ->context([
                'user' => $user,
                'verifyUrl' => $verificationUrl,
                'expiresInHours' => 24,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send verification email.', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function verifyToken(string $token): ?Users
    {
        $user = $this->entityManager
            ->getRepository(Users::class)
            ->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            return null;
        }

        $user->setIsVerified(true);
        $user->setIsActive(true);
        $user->setVerificationToken(null);

        $this->entityManager->flush();

        return $user;
    }
}