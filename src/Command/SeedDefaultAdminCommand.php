<?php

namespace App\Command;

use App\Service\DefaultAdminSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-default-admin',
    description: 'Ensure default admin exists (placeholder login for deploy / dev only)',
)]
class SeedDefaultAdminCommand extends Command
{
    public function __construct(
        private DefaultAdminSeeder $seeder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Override admin email')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Override admin password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email');
        $password = $input->getOption('password');

        $result = $this->seeder->seed(
            is_string($email) && $email !== '' ? $email : null,
            is_string($password) && $password !== '' ? $password : null,
        );

        $user = $result['user'];
        $io->success(sprintf(
            '%s admin %s (id %s). Login at /login with that email and password.',
            $result['created'] ? 'Created' : 'Updated',
            $user->getEmail(),
            (string) ($user->getId() ?? '?'),
        ));
        $io->note('Only this admin account is changed. Clients, staff, and other users are not modified.');

        return Command::SUCCESS;
    }
}
