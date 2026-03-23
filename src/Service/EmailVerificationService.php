<?php

namespace App\Service;

use App\Entity\Users;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EmailVerificationService
{
    private const DEFAULT_TTL_SECONDS = 60 * 60 * 24; // 24h

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $mailFrom,
        private readonly string $mailFromName,
        private readonly string $appSecret,
    ) {}

    public function createSignedVerificationUrl(Users $user, ?int $ttlSeconds = null): string
    {
        $expires = time() + ($ttlSeconds ?? self::DEFAULT_TTL_SECONDS);

        $token = $user->getVerificationToken();

        $baseUrl = $this->urlGenerator->generate(
            'app_email_verify',
            [
                'id' => $user->getId(),
                'expires' => $expires,
                'token' => $token,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $signature = $this->generateSignature((int) $user->getId(), $expires, (string) $token);

        return $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').'signature='.$signature;
    }

    public function sendVerificationEmail(Users $user): void
    {
        if (!$user->getId() || !$user->getEmail()) {
            return;
        }

        $verifyUrl = $this->createSignedVerificationUrl($user);

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, $this->mailFromName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Verify your email address')
            ->htmlTemplate('emails/verification.html.twig')
            ->context([
                'user' => $user,
                'verifyUrl' => $verifyUrl,
                'expiresInHours' => 24,
            ]);

        $this->mailer->send($email);
    }

    public function isSignatureValid(int $id, int $expires, ?string $token, string $signature): bool
    {
        if ($expires < time()) {
            return false;
        }

        $expected = $this->generateSignature($id, $expires, (string) $token);

        return hash_equals($expected, $signature);
    }

    private function generateSignature(int $id, int $expires, string $token): string
    {
        $payload = $id.'|'.$expires.'|'.$token;

        return hash_hmac('sha256', $payload, $this->appSecret);
    }
}

