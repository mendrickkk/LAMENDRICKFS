<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Aligns config/jwt/*.pem with JWT_PASSPHRASE. Does not read or write the database.
 */
#[AsCommand(
    name: 'app:jwt:ensure-keys',
    description: 'Ensure JWT key files exist and match JWT_PASSPHRASE (regenerates only on mismatch).',
)]
final class EnsureJwtKeysCommand extends Command
{
    private const PLACEHOLDER_PASSPHRASES = [
        'build_jwt_passphrase',
        'change_me_jwt_passphrase',
        'REPLACE_WITH_JWT_PASSPHRASE',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $passphrase = (string) ($_ENV['JWT_PASSPHRASE'] ?? $_SERVER['JWT_PASSPHRASE'] ?? getenv('JWT_PASSPHRASE') ?: '');

        if ($passphrase === '' || in_array($passphrase, self::PLACEHOLDER_PASSPHRASES, true)) {
            $io->error('Set JWT_PASSPHRASE in Railway variables (same value as your local .env).');

            return Command::FAILURE;
        }

        $privatePath = $this->projectDir.'/config/jwt/private.pem';
        $publicPath = $this->projectDir.'/config/jwt/public.pem';

        if (!is_dir(dirname($privatePath))) {
            mkdir(dirname($privatePath), 0775, true);
        }

        if ($this->keysMatchPassphrase($privatePath, $passphrase)) {
            $io->writeln('JWT keys OK (match JWT_PASSPHRASE).');

            return Command::SUCCESS;
        }

        if (is_file($privatePath)) {
            $io->warning('JWT keys do not match JWT_PASSPHRASE — regenerating (existing API tokens will stop working until users log in again).');
            @unlink($privatePath);
            @unlink($publicPath);
        } else {
            $io->writeln('JWT keys missing — generating keypair.');
        }

        $generate = $this->getApplication()?->find('lexik:jwt:generate-keypair');
        if ($generate === null) {
            $io->error('lexik:jwt:generate-keypair is not available.');

            return Command::FAILURE;
        }

        $exitCode = $generate->run(new ArrayInput([]), $output);
        if ($exitCode !== Command::SUCCESS || !$this->keysMatchPassphrase($privatePath, $passphrase)) {
            $io->error('Could not create JWT keys that match JWT_PASSPHRASE.');

            return Command::FAILURE;
        }

        $io->success('JWT keypair ready.');

        return Command::SUCCESS;
    }

    private function keysMatchPassphrase(string $privatePath, string $passphrase): bool
    {
        if (!is_file($privatePath) || !is_readable($privatePath)) {
            return false;
        }

        $contents = file_get_contents($privatePath);

        return $contents !== false
            && $contents !== ''
            && openssl_pkey_get_private($contents, $passphrase) !== false;
    }
}
