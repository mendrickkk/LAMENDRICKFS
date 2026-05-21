<?php

namespace App\Command;

use App\Entity\Users;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:bootstrap-admin',
    description: 'Create or reset an admin account (for Railway / first deploy)',
)]
class BootstrapAdminUserCommand extends Command
{
    public function __construct(
        private UsersRepository $usersRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin email (or ADMIN_BOOTSTRAP_EMAIL env)')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Plain password (or ADMIN_BOOTSTRAP_PASSWORD env)')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Username when creating a new user', 'admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email') ?: getenv('ADMIN_BOOTSTRAP_EMAIL') ?: '';
        $plainPassword = $input->getOption('password') ?: getenv('ADMIN_BOOTSTRAP_PASSWORD') ?: '';

        if ($email === '' || $plainPassword === '') {
            $io->error('Provide --email and --password, or set ADMIN_BOOTSTRAP_EMAIL and ADMIN_BOOTSTRAP_PASSWORD.');

            return Command::FAILURE;
        }

        $email = trim($email);
        $user = $this->usersRepository->findOneBy(['email' => $email]);

        if (!$user instanceof Users) {
            $user = new Users();
            $user->setEmail($email);
            $user->setUsername((string) $input->getOption('username'));
            $this->entityManager->persist($user);
            $io->writeln(sprintf('Creating new admin user for %s', $email));
        } else {
            $io->writeln(sprintf('Updating existing user %s (id %d)', $email, $user->getId() ?? 0));
        }

        $user->setRole('ROLE_ADMIN');
        $user->setIsActive(true);
        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->flush();

        $io->success(sprintf('Admin "%s" is ready. Sign in at /login with that email and password.', $email));

        return Command::SUCCESS;
    }
}
