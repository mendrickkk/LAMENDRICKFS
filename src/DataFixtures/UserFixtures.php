<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
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
class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Load admin user fixture
     * 
     * Create the default admin user if it doesn't exist.
     */
    public function load(ObjectManager $manager): void
    {
        // Admin user (backup plan)
        $adminUsername = 'admin';
        $adminEmail = 'kurttruk1234@gmail.com';
        $adminPassword = 'adminkurt';

        // Check if user already exists
        $existingUser = $manager->getRepository(Users::class)->findOneBy([
            'username' => $adminUsername
        ]);

        if ($existingUser) {
            return;
        }

        // Check if email already exists
        $existingEmail = $manager->getRepository(Users::class)->findOneBy([
            'email' => $adminEmail
        ]);

        if ($existingEmail) {
            return;
        }

        // Create admin user
        $admin = new Users();
        $admin->setUsername($adminUsername);
        $admin->setEmail($adminEmail);
        $admin->setRole('ROLE_ADMIN');
        $admin->setIsActive(true);
        $admin->setIsVerified(true);

        // Hash password (Symfony never accepts plain text in the database)
        $hashedPassword = $this->passwordHasher->hashPassword($admin, $adminPassword);
        $admin->setPassword($hashedPassword);

        // Set timestamps
        $admin->setCreatedAt(new \DateTime());
        $admin->setUpdatedAt(new \DateTime());

        $manager->persist($admin);
        $manager->flush();
    }
}
