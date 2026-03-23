<?php

namespace App\Repository;

use App\Entity\Settings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settings>
 */
class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settings::class);
    }

    /**
     * Get all settings for a category
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.category = :category')
            ->setParameter('category', $category)
            ->orderBy('s.settingKey', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get single setting by key
     */
    public function findOneByKey(string $key): ?Settings
    {
        return $this->createQueryBuilder('s')
            ->where('s.settingKey = :key')
            ->setParameter('key', $key)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get setting value with type casting
     */
    public function getValue(string $key, $default = null)
    {
        $setting = $this->findOneByKey($key);
        
        if (!$setting) {
            return $default;
        }

        $value = $setting->getSettingValue();
        $type = $setting->getSettingType();

        return $this->castValue($value, $type, $default);
    }

    /**
     * Set/update setting value
     */
    public function setValue(string $key, $value, string $type = 'string', string $category = 'general', ?string $description = null): Settings
    {
        $setting = $this->findOneByKey($key);

        if (!$setting) {
            $setting = new Settings();
            $setting->setSettingKey($key);
            $setting->setCategory($category);
            $setting->setDescription($description);
        }

        $setting->setSettingType($type);
        $setting->setSettingValue($this->stringifyValue($value, $type));

        $this->getEntityManager()->persist($setting);
        $this->getEntityManager()->flush();

        return $setting;
    }

    /**
     * Cast value based on type
     */
    private function castValue(?string $value, string $type, $default = null)
    {
        if ($value === null) {
            return $default;
        }

        return match($type) {
            'integer', 'int' => (int) $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true) ?? $default,
            'float' => (float) $value,
            default => $value,
        };
    }

    /**
     * Convert value to string for storage
     */
    private function stringifyValue($value, string $type): string
    {
        return match($type) {
            'json' => json_encode($value),
            'boolean', 'bool' => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}

