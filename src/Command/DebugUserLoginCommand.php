<?php

namespace App\Command;

use App\Entity\Users;
use App\Repository\UsersRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:debug-login',
    description: 'Diagnose why an email/password cannot sign in (no secrets printed)',
)]
class DebugUserLoginCommand extends Command
{
    public function __construct(
        private UsersRepository $usersRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email used on the login form')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Test whether this password matches the stored hash');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim((string) $input->getArgument('email'));
        $testPassword = $input->getOption('password');

        $user = $this->usersRepository->findOneBy(['email' => $email]);
        if (!$user instanceof Users) {
            $io->error(sprintf('No row in `users` for email "%s".', $email));
            $io->note('Railway uses its own MySQL — data in local phpMyAdmin is not the same database.');

            return Command::FAILURE;
        }

        $stored = (string) $user->getPassword();
        $looksHashed = $this->looksHashed($stored);

        $io->table(
            ['Field', 'Value'],
            [
                ['id', (string) ($user->getId() ?? '')],
                ['email', (string) $user->getEmail()],
                ['username', (string) $user->getUsername()],
                ['role (DB via getter)', (string) $user->getRole()],
                ['is_active', $user->isActive() ? 'yes' : 'no'],
                ['is_verified', $user->isVerified() ? 'yes' : 'no'],
                ['password looks hashed', $looksHashed ? 'yes' : 'no (plain text — run repair-login)'],
                ['password length', (string) strlen($stored)],
            ]
        );

        if ($testPassword !== null && $testPassword !== '') {
            $valid = $looksHashed
                ? $this->passwordHasher->isPasswordValid($user, $testPassword)
                : ($stored === $testPassword);
            $io->writeln($valid
                ? 'Password check: MATCH — credentials should work after hashing if still plain.'
                : 'Password check: NO MATCH — reset with app:user:bootstrap-admin or repair-login --force -p');
        } else {
            $io->note('Add --password=yourpassword to test the hash without changing the database.');
        }

        return Command::SUCCESS;
    }

    private function looksHashed(string $password): bool
    {
        return str_starts_with($password, '$2y$')
            || str_starts_with($password, '$2a$')
            || str_starts_with($password, '$2b$')
            || str_starts_with($password, '$argon2');
    }
}
