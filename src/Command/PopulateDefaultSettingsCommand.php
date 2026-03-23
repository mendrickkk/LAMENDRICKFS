<?php

namespace App\Command;

use App\Service\SettingsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-default-settings',
    description: 'Populate default system settings',
)]
class PopulateDefaultSettingsCommand extends Command
{
    public function __construct(
        private SettingsService $settingsService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // General Settings
        $this->settingsService->set('store_name', 'Flower Shop', 'string', 'general', 'Store or business name');
        $this->settingsService->set('store_address', '', 'string', 'general', 'Physical address of store');
        $this->settingsService->set('store_phone', '', 'string', 'general', 'Contact phone number');
        $this->settingsService->set('store_email', '', 'string', 'general', 'Contact email address');
        $this->settingsService->set('business_hours', '', 'string', 'general', 'Business operating hours');

        // Email Settings
        $this->settingsService->set('smtp_host', 'smtp.example.com', 'string', 'email', 'SMTP server hostname');
        $this->settingsService->set('smtp_port', 587, 'integer', 'email', 'SMTP server port');
        $this->settingsService->set('smtp_username', '', 'string', 'email', 'SMTP authentication username');
        $this->settingsService->set('smtp_password', '', 'string', 'email', 'SMTP authentication password');
        $this->settingsService->set('smtp_encryption', 'tls', 'string', 'email', 'SMTP encryption method');
        $this->settingsService->set('from_email', 'noreply@example.com', 'string', 'email', 'Default sender email');
        $this->settingsService->set('from_name', 'Flower Shop', 'string', 'email', 'Default sender name');

        // Payment Settings
        $this->settingsService->set('payment_methods', json_encode(['cash']), 'json', 'payment', 'Available payment methods');
        $this->settingsService->set('payment_gateway', 'manual', 'string', 'payment', 'Primary payment gateway');
        $this->settingsService->set('currency', 'PHP', 'string', 'payment', 'Default currency code');

        // Security Settings
        $this->settingsService->set('min_password_length', 8, 'integer', 'security', 'Minimum password length');
        $this->settingsService->set('session_timeout', 60, 'integer', 'security', 'Session timeout in minutes');
        $this->settingsService->set('require_uppercase', true, 'boolean', 'security', 'Require uppercase letter');
        $this->settingsService->set('require_lowercase', true, 'boolean', 'security', 'Require lowercase letter');
        $this->settingsService->set('require_number', true, 'boolean', 'security', 'Require number');
        $this->settingsService->set('require_special_char', false, 'boolean', 'security', 'Require special character');

        // Inventory Settings
        $this->settingsService->set('low_stock_threshold', 10, 'integer', 'inventory', 'Low stock alert threshold');
        $this->settingsService->set('enable_low_stock_alerts', true, 'boolean', 'inventory', 'Enable low stock alerts');
        $this->settingsService->set('alert_email', '', 'string', 'inventory', 'Email for low stock alerts');

        // Orders Settings
        $this->settingsService->set('order_prefix', 'ORD', 'string', 'orders', 'Order number prefix');
        $this->settingsService->set('auto_confirm_orders', false, 'boolean', 'orders', 'Auto confirm orders');
        $this->settingsService->set('default_delivery_days', 3, 'integer', 'orders', 'Default delivery days');

        $io->success('Default settings have been populated successfully!');

        return Command::SUCCESS;
    }
}

