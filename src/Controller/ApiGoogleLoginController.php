<?php

namespace App\Controller;

use App\Entity\Users;
use App\Security\CustomerApiAccess;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ApiGoogleLoginController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('/api/login/google', name: 'api_login_google', methods: ['POST'])]
    public function loginWithGoogle(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['success' => false, 'message' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $idToken = $data['idToken'] ?? null;
        if (!is_string($idToken) || trim($idToken) === '') {
            return $this->json(['success' => false, 'message' => 'Google ID token is required.'], Response::HTTP_BAD_REQUEST);
        }

        $tokenInfo = $this->fetchGoogleTokenInfo($idToken);
        if ($tokenInfo === null) {
            return $this->json(['success' => false, 'message' => 'Invalid Google token.'], Response::HTTP_UNAUTHORIZED);
        }

        $email = isset($tokenInfo['email']) && is_string($tokenInfo['email']) ? trim($tokenInfo['email']) : '';
        $emailVerified = ($tokenInfo['email_verified'] ?? 'false') === 'true';
        $audience = isset($tokenInfo['aud']) && is_string($tokenInfo['aud']) ? $tokenInfo['aud'] : '';
        $expectedAudience = trim((string) ($_ENV['GOOGLE_CLIENT_ID'] ?? $_SERVER['GOOGLE_CLIENT_ID'] ?? ''));

        if ($email === '' || !$emailVerified) {
            return $this->json(
                ['success' => false, 'message' => 'Google account email is missing or not verified.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        if ($expectedAudience !== '' && $audience !== '' && $audience !== $expectedAudience) {
            return $this->json(['success' => false, 'message' => 'Google token audience mismatch.'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->em->getRepository(Users::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $user = $this->createCustomerFromGoogle($email, $tokenInfo);
            $this->em->persist($user);
        } elseif (!$user->isCustomer()) {
            return $this->json(
                ['success' => false, 'message' => CustomerApiAccess::NON_CUSTOMER_MESSAGE],
                Response::HTTP_FORBIDDEN
            );
        }

        $user->setIsVerified(true);
        $user->setIsActive(true);
        $user->setVerificationToken(null);
        $this->em->flush();

        try {
            $jwt = $this->jwtManager->create($user);
        } catch (\Throwable) {
            return $this->json(
                ['success' => false, 'message' => 'Unable to issue login token. Server JWT configuration is invalid.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return $this->json([
            'success' => true,
            'token' => $jwt,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'verified' => $user->isVerified(),
            ],
        ]);
    }

    private function fetchGoogleTokenInfo(string $idToken): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://oauth2.googleapis.com/tokeninfo', [
                'query' => ['id_token' => $idToken],
            ]);
        } catch (TransportExceptionInterface) {
            return null;
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return null;
        }

        $data = $response->toArray(false);

        return is_array($data) ? $data : null;
    }

    private function createCustomerFromGoogle(string $email, array $tokenInfo): Users
    {
        $user = new Users();
        $user->setEmail($email);
        $user->setRole('ROLE_CLIENT');
        $user->setIsActive(true);
        $user->setIsVerified(true);

        $givenName = isset($tokenInfo['given_name']) && is_string($tokenInfo['given_name']) ? trim($tokenInfo['given_name']) : '';
        $familyName = isset($tokenInfo['family_name']) && is_string($tokenInfo['family_name']) ? trim($tokenInfo['family_name']) : '';
        $displayName = isset($tokenInfo['name']) && is_string($tokenInfo['name']) ? trim($tokenInfo['name']) : '';

        if ($givenName === '' && $displayName !== '') {
            $parts = preg_split('/\s+/', $displayName, 2) ?: [];
            $givenName = trim((string) ($parts[0] ?? ''));
            $familyName = $familyName !== '' ? $familyName : trim((string) ($parts[1] ?? ''));
        }

        $username = strstr($email, '@', true) ?: $email;
        $user->setUsername($username);
        $user->setFirstName($givenName !== '' ? $givenName : null);
        $user->setLastName($familyName !== '' ? $familyName : null);

        $randomPassword = bin2hex(random_bytes(32));
        $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

        return $user;
    }
}
