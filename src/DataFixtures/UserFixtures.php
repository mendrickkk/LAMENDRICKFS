<?php

namespace App\DataFixtures;

use App\Service\DefaultAdminSeeder;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Default admin placeholder (same as app:seed-default-admin).
 *
 * - Email: kurttruk1234@gmail.com
 * - Password: adminkurt
 *
 * Run: php bin/console doctrine:fixtures:load --append
 * Or: php bin/console app:seed-default-admin
 */
class UserFixtures extends Fixture
{
    public function __construct(
        private DefaultAdminSeeder $defaultAdminSeeder,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->defaultAdminSeeder->seed();
    }
}
