<?php

namespace App\Service;

use App\Entity\Users;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Ensures the project default admin exists with a valid hashed password.
 * Only updates/creates the single account identified by email — other users are never touched.
 */
final class DefaultAdminSeeder
{
    public const DEFAULT_USERNAME = 'admin';
    public const DEFAULT_EMAIL = 'kurttruk1234@gmail.com';
    public const DEFAULT_PASSWORD = 'adminkurt';

    public function __construct(
        private UsersRepository $usersRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function resolveEmail(): string
    {
        $fromEnv = getenv('ADMIN_BOOTSTRAP_EMAIL');

        return trim($fromEnv !== false && $fromEnv !== '' ? $fromEnv : self::DEFAULT_EMAIL);
    }

    public function resolvePassword(): string
    {
        $fromEnv = getenv('ADMIN_BOOTSTRAP_PASSWORD');

        return $fromEnv !== false && $fromEnv !== '' ? $fromEnv : self::DEFAULT_PASSWORD;
    }

    /**
     * @return array{user: Users, created: bool, passwordReset: bool}
     */
    public function seed(?string $email = null, ?string $password = null): array
    {
        $email = trim($email ?? $this->resolveEmail());
        $plainPassword = $password ?? $this->resolvePassword();

        $user = $this->usersRepository->findOneBy(['email' => $email]);
        $created = false;

        if (!$user instanceof Users) {
            $user = new Users();
            $user->setEmail($email);
            $user->setUsername(self::DEFAULT_USERNAME);
            $this->entityManager->persist($user);
            $created = true;
        }

        $user->setRole('ROLE_ADMIN');
        $user->setIsActive(true);
        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->flush();

        return ['user' => $user, 'created' => $created, 'passwordReset' => true];
    }
}
