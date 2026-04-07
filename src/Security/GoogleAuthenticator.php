<?php

namespace App\Security;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em,
        private RouterInterface $router,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);
        $googleUser = $client->fetchUserFromToken($accessToken);

        if (!$googleUser instanceof GoogleUser) {
            throw new CustomUserMessageAuthenticationException('Unexpected Google account response.');
        }

        $email = $googleUser->getEmail();
        if ($email === null || $email === '') {
            throw new CustomUserMessageAuthenticationException('Google did not return an email address.');
        }

        if ($googleUser->getEmailVerified() !== true) {
            throw new CustomUserMessageAuthenticationException(
                'Your Google account email is not verified. Please verify it with Google and try again.'
            );
        }

        return new SelfValidatingPassport(
            new UserBadge($email, function () use ($googleUser, $email) {
                $user = $this->em->getRepository(Users::class)->findOneBy(['email' => $email]);

                if (!$user) {
                    $user = $this->createStaffFromGoogle($googleUser, $email);
                    $this->em->persist($user);
                } elseif (!in_array($user->getRole(), ['ROLE_STAFF', 'ROLE_ADMIN'], true)) {
                    throw new CustomUserMessageAuthenticationException(
                        'Google sign-in is only available for staff accounts.'
                    );
                }

                $user->setIsVerified(true);
                $user->setIsActive(true);
                $user->setVerificationToken(null);

                $this->em->flush();

                return $user;
            })
        );
    }

    private function createStaffFromGoogle(GoogleUser $googleUser, string $email): Users
    {
        $user = new Users();
        $user->setEmail($email);
        $user->setUsername($email);

        $firstName = $googleUser->getFirstName();
        $lastName = $googleUser->getLastName();
        if (($firstName === null || $firstName === '') && $googleUser->getName() !== '') {
            $parts = preg_split('/\s+/', trim($googleUser->getName()), 2) ?: [];
            $firstName = $parts[0] ?? null;
            $lastName = $lastName ?? ($parts[1] ?? null);
        }
        $user->setFirstName($firstName ?: null);
        $user->setLastName($lastName ?: null);

        $user->setRole('ROLE_STAFF');
        $user->setIsActive(true);
        $user->setIsVerified(true);

        $randomPassword = bin2hex(random_bytes(32));
        $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

        return $user;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('app_login_success'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // CustomUserMessageAuthenticationException provides the user-facing message directly.
        $message = $exception instanceof CustomUserMessageAuthenticationException
            ? $exception->getMessage()
            : strtr($exception->getMessageKey(), $exception->getMessageData());
        $request->getSession()->getFlashBag()->add('error', $message);

        return new RedirectResponse($this->router->generate('app_login'));
    }
}
