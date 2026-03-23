<?php

namespace App\DataFixtures;

use App\Entity\Users;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * UserFixtures - Backup plan for admin user creation
 * 
 * This fixture creates an admin user with the following credentials:
 * - Username: admin
 * - Email: admin@mendrick.com
 * - Password: adminkurt
 * 
 * Usage:
 * - With DoctrineFixturesBundle: Extend this class and implement load() method
 * - As standalone: Inject this service and call load() method
 * - Via command: Create a command that uses this service
 */
class UserFixtures
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Load admin user fixture
     * 
     * @param ObjectManager $manager
     * @param bool $append If true, skip if user already exists
     * @return bool Returns true if user was created, false if skipped
     */
    public function load(ObjectManager $manager, bool $append = false): bool
    {
        // Admin user (backup plan)
        $adminUsername = 'admin';
        $adminEmail = 'admin@mendrick.com';
        $adminPassword = 'adminkurt';

        // Check if user already exists (only when using --append flag)
        $existingUser = $manager->getRepository(Users::class)->findOneBy([
            'username' => $adminUsername
        ]);

        // When using --append, we skip if user exists to avoid duplicates
        if ($existingUser) {
            // User already exists, skip creation
            return false;
        }

        // Check if email already exists
        $existingEmail = $manager->getRepository(Users::class)->findOneBy([
            'email' => $adminEmail
        ]);

        if ($existingEmail) {
            // Email already exists, skip creation
            return false;
        }

        // Create admin user
        $admin = new Users();
        $admin->setUsername($adminUsername);
        $admin->setEmail($adminEmail);
        $admin->setRole('ROLE_ADMIN');
        $admin->setIsActive(true);
        
        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($admin, $adminPassword);
        $admin->setPassword($hashedPassword);

        // Set timestamps
        $admin->setCreatedAt(new \DateTime());
        $admin->setUpdatedAt(new \DateTime());

        $manager->persist($admin);
        $manager->flush();
        
        return true;
    }
}
