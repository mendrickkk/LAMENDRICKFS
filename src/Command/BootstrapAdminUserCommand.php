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
    name: 'app:user:bootstrap-admin',
    description: 'Create or reset an admin account (alias for seed with explicit email/password)',
)]
class BootstrapAdminUserCommand extends Command
{
    public function __construct(
        private DefaultAdminSeeder $seeder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin email (or ADMIN_BOOTSTRAP_EMAIL env)')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Plain password (or ADMIN_BOOTSTRAP_PASSWORD env)')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Ignored — kept for backward compatibility', 'admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email') ?: getenv('ADMIN_BOOTSTRAP_EMAIL') ?: '';
        $plainPassword = $input->getOption('password') ?: getenv('ADMIN_BOOTSTRAP_PASSWORD') ?: '';

        if ($email === '' || $plainPassword === '') {
            $io->error('Provide --email and --password, or set ADMIN_BOOTSTRAP_EMAIL and ADMIN_BOOTSTRAP_PASSWORD, or run app:seed-default-admin.');

            return Command::FAILURE;
        }

        $this->seeder->seed(trim($email), $plainPassword);
        $io->success(sprintf('Admin "%s" is ready.', trim($email)));

        return Command::SUCCESS;
    }
}
