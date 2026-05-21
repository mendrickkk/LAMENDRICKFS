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
 * Aligns config/jwt/*.pem with the active passphrase. Does not read or write the database.
 */
#[AsCommand(
    name: 'app:jwt:ensure-keys',
    description: 'Ensure JWT key files exist and match JWT_PASSPHRASE (regenerates only on mismatch).',
)]
final class EnsureJwtKeysCommand extends Command
{
    private const PLACEHOLDER_JWT_PASSPHRASES = [
        'build_jwt_passphrase',
        'change_me_jwt_passphrase',
        'REPLACE_WITH_JWT_PASSPHRASE',
    ];

    private const PLACEHOLDER_APP_SECRETS = [
        'build-time-secret-set-in-railway-variables',
        'change_me_to_a_long_random_string',
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
        $passphrase = $this->resolvePassphrase();

        if ($passphrase === null) {
            $io->error('Set APP_SECRET (and optionally JWT_PASSPHRASE) in Railway variables.');

            return Command::FAILURE;
        }

        $this->syncPassphraseEnv($passphrase);

        $privatePath = $this->projectDir.'/config/jwt/private.pem';
        $publicPath = $this->projectDir.'/config/jwt/public.pem';

        if (!is_dir(dirname($privatePath))) {
            mkdir(dirname($privatePath), 0775, true);
        }

        if ($this->keysMatchPassphrase($privatePath, $passphrase)) {
            $io->writeln('JWT keys OK.');

            return Command::SUCCESS;
        }

        if (is_file($privatePath)) {
            $io->warning('JWT keys do not match passphrase — regenerating (users must log in again).');
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
            $io->error('Could not create JWT keys.');

            return Command::FAILURE;
        }

        $io->success('JWT keypair ready.');

        return Command::SUCCESS;
    }

    private function resolvePassphrase(): ?string
    {
        $jwt = trim((string) ($_ENV['JWT_PASSPHRASE'] ?? $_SERVER['JWT_PASSPHRASE'] ?? getenv('JWT_PASSPHRASE') ?: ''));
        if ($jwt !== '' && !in_array($jwt, self::PLACEHOLDER_JWT_PASSPHRASES, true)) {
            return $jwt;
        }

        $appSecret = trim((string) ($_ENV['APP_SECRET'] ?? $_SERVER['APP_SECRET'] ?? getenv('APP_SECRET') ?: ''));
        if ($appSecret !== '' && !in_array($appSecret, self::PLACEHOLDER_APP_SECRETS, true)) {
            return $appSecret;
        }

        return null;
    }

    private function syncPassphraseEnv(string $passphrase): void
    {
        putenv('JWT_PASSPHRASE='.$passphrase);
        $_ENV['JWT_PASSPHRASE'] = $passphrase;
        $_SERVER['JWT_PASSPHRASE'] = $passphrase;
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
