<?php

namespace App\Command;

use App\Entity\Users;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:repair-login',
    description: 'Hash plain-text passwords and fix role/verified flags so users can sign in',
)]
class RepairUserLoginCommand extends Command
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
            ->addArgument('email', InputArgument::OPTIONAL, 'User email (optional when using --all-plain)')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Override plain password to hash (default: use value already stored in DB)')
            ->addOption('verify', null, InputOption::VALUE_NONE, 'Mark the account as email-verified')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Re-hash password even if already hashed (requires --password)')
            ->addOption('all-plain', null, InputOption::VALUE_NONE, 'Repair every user whose password is not hashed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $markVerified = (bool) $input->getOption('verify');

        if ($input->getOption('all-plain')) {
            $users = $this->usersRepository->findAll();
            $repaired = 0;
            foreach ($users as $user) {
                if ($this->repairUser($user, null, $markVerified, $io, false)) {
                    ++$repaired;
                }
            }
            $this->entityManager->flush();
            $io->success(sprintf('Repaired %d user(s).', $repaired));

            return Command::SUCCESS;
        }

        $email = $input->getArgument('email');
        if ($email === null || $email === '') {
            $io->error('Email is required unless you use --all-plain.');

            return Command::FAILURE;
        }

        $user = $this->usersRepository->findOneBy(['email' => $email]);
        if (!$user instanceof Users) {
            $io->error(sprintf('No user found for email "%s".', $email));

            return Command::FAILURE;
        }

        $plainPassword = $input->getOption('password');
        $force = (bool) $input->getOption('force');
        if ($force && ($plainPassword === null || $plainPassword === '')) {
            $io->error('--force requires --password.');

            return Command::FAILURE;
        }
        if (!$this->repairUser($user, $plainPassword, true, $io, $force)) {
            $io->warning('Nothing to change for this user.');

            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        $io->success(sprintf('User "%s" can now sign in with the hashed password.', $email));

        return Command::SUCCESS;
    }

    private function repairUser(Users $user, ?string $plainPassword, bool $markVerified, SymfonyStyle $io, bool $force): bool
    {
        $changed = false;

        $role = $user->getRole() ?? '';
        if (str_starts_with($role, '[')) {
            $decoded = json_decode($role, true);
            if (is_array($decoded) && isset($decoded[0]) && is_string($decoded[0])) {
                $user->setRole($decoded[0]);
                $io->writeln(sprintf('  Fixed role for %s → %s', $user->getEmail(), $decoded[0]));
                $changed = true;
            }
        }

        $stored = (string) $user->getPassword();
        $mustHash = $force || !$this->isHashedPassword($stored);
        if ($mustHash) {
            if ($force && ($plainPassword === null || $plainPassword === '')) {
                $io->warning(sprintf('  Skipping %s: --force needs --password', $user->getEmail()));

                return $changed;
            }
            $plain = ($plainPassword !== null && $plainPassword !== '') ? $plainPassword : $stored;
            if ($plain === '') {
                $io->warning(sprintf('  Skipping %s: empty password in database', $user->getEmail()));

                return $changed;
            }
            $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
            $io->writeln(sprintf(
                '  %s password for %s',
                $force ? 'Reset' : 'Hashed',
                $user->getEmail()
            ));
            $changed = true;
        }

        if ($markVerified && !$user->isVerified()) {
            $user->setIsVerified(true);
            $io->writeln(sprintf('  Marked %s as verified', $user->getEmail()));
            $changed = true;
        }

        if (!$user->isActive()) {
            $user->setIsActive(true);
            $io->writeln(sprintf('  Activated %s', $user->getEmail()));
            $changed = true;
        }

        return $changed;
    }

    private function isHashedPassword(string $password): bool
    {
        return str_starts_with($password, '$2y$')
            || str_starts_with($password, '$2a$')
            || str_starts_with($password, '$2b$')
            || str_starts_with($password, '$argon2');
    }
}
