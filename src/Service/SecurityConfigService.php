<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

class SecurityConfigService
{
    private string $securityYamlPath;

    public function __construct(
        private Filesystem $filesystem,
        private UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        $this->securityYamlPath = $projectDir . '/config/packages/security.yaml';
    }

    /**
     * Update in-memory admin password
     */
    public function updateInMemoryAdminPassword(string $username, string $newPassword): bool
    {
        try {
            // Read current security.yaml
            if (!$this->filesystem->exists($this->securityYamlPath)) {
                return false;
            }

            $content = file_get_contents($this->securityYamlPath);
            $config = Yaml::parse($content);

            // Find and update password
            if (isset($config['security']['providers']['admin_memory']['memory']['users'])) {
                $users = $config['security']['providers']['admin_memory']['memory']['users'];
                
                // Create a temporary user to hash password
                $tempUser = new InMemoryUser($username, '', ['ROLE_ADMIN']);
                $hashedPassword = $this->passwordHasher->hashPassword($tempUser, $newPassword);

                // Update password in config
                foreach ($users as $key => $userConfig) {
                    if (isset($userConfig[$username])) {
                        $userConfig[$username]['password'] = $hashedPassword;
                        $config['security']['providers']['admin_memory']['memory']['users'][$key] = $userConfig;
                        break;
                    }
                }

                // Write back to file
                $yaml = Yaml::dump($config, 4, 2);
                file_put_contents($this->securityYamlPath, $yaml);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            // Log error or handle gracefully
            return false;
        }
    }

    /**
     * Check if username is in-memory admin
     */
    public function isInMemoryAdmin(string $username): bool
    {
        try {
            if (!$this->filesystem->exists($this->securityYamlPath)) {
                return false;
            }

            $content = file_get_contents($this->securityYamlPath);
            $config = Yaml::parse($content);

            if (isset($config['security']['providers']['admin_memory']['memory']['users'])) {
                $users = $config['security']['providers']['admin_memory']['memory']['users'];
                
                foreach ($users as $userConfig) {
                    if (isset($userConfig[$username])) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}

