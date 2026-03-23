<?php

namespace App\Service;

use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

class SettingsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SettingsRepository $settingsRepository
    ) {
    }

    /**
     * Get setting value
     */
    public function get(string $key, $default = null)
    {
        return $this->settingsRepository->getValue($key, $default);
    }

    /**
     * Set setting value
     */
    public function set(string $key, $value, string $type = 'string', string $category = 'general', ?string $description = null): void
    {
        $this->settingsRepository->setValue($key, $value, $type, $category, $description);
    }

    /**
     * Get all settings for a category
     */
    public function getAllByCategory(string $category): array
    {
        $settings = $this->settingsRepository->findByCategory($category);
        $result = [];

        foreach ($settings as $setting) {
            $key = $setting->getSettingKey();
            $value = $this->settingsRepository->getValue($key);
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Bulk save settings
     */
    public function saveSettings(array $settings, string $category): void
    {
        foreach ($settings as $key => $value) {
            $setting = $this->settingsRepository->findOneByKey($key);
            
            if ($setting) {
                $type = $setting->getSettingType();
            } else {
                // Default type based on value
                $type = $this->detectType($value);
            }

            $this->set($key, $value, $type, $category);
        }

        $this->entityManager->flush();
    }

    /**
     * Detect type from value
     */
    private function detectType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_array($value)) {
            return 'json';
        }
        return 'string';
    }
}

