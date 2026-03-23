<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Service\SecurityConfigService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/settings')]
#[IsGranted('ROLE_ADMIN')]
class SettingsController extends AbstractController
{
    public function __construct(
        private SettingsService $settingsService,
        private SecurityConfigService $securityConfigService,
        private UserPasswordHasherInterface $passwordHasher,
        private UsersRepository $usersRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'app_settings_index', methods: ['GET'])]
    public function index(): Response
    {
        try {
            // Load all settings with error handling
            $paymentMethods = $this->settingsService->get('payment_methods', '[]');
            $paymentMethodsArray = [];
            if (is_string($paymentMethods)) {
                $decoded = json_decode($paymentMethods, true);
                $paymentMethodsArray = is_array($decoded) ? $decoded : [];
            } elseif (is_array($paymentMethods)) {
                $paymentMethodsArray = $paymentMethods;
            }

            $settings = [
                'store_name' => $this->settingsService->get('store_name', '') ?? '',
                'store_address' => $this->settingsService->get('store_address', '') ?? '',
                'store_phone' => $this->settingsService->get('store_phone', '') ?? '',
                'store_email' => $this->settingsService->get('store_email', '') ?? '',
                'business_hours' => $this->settingsService->get('business_hours', '') ?? '',
                'smtp_host' => $this->settingsService->get('smtp_host', 'smtp.example.com') ?? 'smtp.example.com',
                'smtp_port' => $this->settingsService->get('smtp_port', 587) ?? 587,
                'smtp_username' => $this->settingsService->get('smtp_username', '') ?? '',
                'smtp_encryption' => $this->settingsService->get('smtp_encryption', 'tls') ?? 'tls',
                'from_email' => $this->settingsService->get('from_email', 'noreply@example.com') ?? 'noreply@example.com',
                'from_name' => $this->settingsService->get('from_name', 'Flower Shop') ?? 'Flower Shop',
                'payment_methods' => $paymentMethodsArray,
                'payment_gateway' => $this->settingsService->get('payment_gateway', 'manual') ?? 'manual',
                'currency' => $this->settingsService->get('currency', 'PHP') ?? 'PHP',
                'min_password_length' => $this->settingsService->get('min_password_length', 8) ?? 8,
                'session_timeout' => $this->settingsService->get('session_timeout', 60) ?? 60,
                'low_stock_threshold' => $this->settingsService->get('low_stock_threshold', 10) ?? 10,
                'enable_low_stock_alerts' => $this->settingsService->get('enable_low_stock_alerts', true) ?? true,
                'alert_email' => $this->settingsService->get('alert_email', '') ?? '',
                'order_prefix' => $this->settingsService->get('order_prefix', 'ORD') ?? 'ORD',
                'auto_confirm_orders' => $this->settingsService->get('auto_confirm_orders', false) ?? false,
                'default_delivery_days' => $this->settingsService->get('default_delivery_days', 3) ?? 3,
            ];

            return $this->render('admin/settings/index.html.twig', [
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error loading settings: ' . $e->getMessage());
            return $this->render('admin/settings/index.html.twig', [
                'settings' => [],
            ]);
        }
    }

    #[Route('/save', name: 'app_settings_save', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function save(Request $request): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'You need Admin role to edit settings.');
            return $this->redirectToRoute('app_settings_index');
        }

        $data = $request->request->all();

        // Handle password change
        if (!empty($data['admin_password'])) {
            $adminUsername = $this->getAdminUsername();
            if ($this->isInMemoryAdmin()) {
                $success = $this->securityConfigService->updateInMemoryAdminPassword($adminUsername, $data['admin_password']);
                if (!$success) {
                    $this->addFlash('error', 'Failed to update admin password. Please check file permissions.');
                }
            } else {
                $adminUser = $this->usersRepository->findOneBy(['username' => $adminUsername, 'role' => 'ROLE_ADMIN']);
                if ($adminUser) {
                    $hashedPassword = $this->passwordHasher->hashPassword($adminUser, $data['admin_password']);
                    $adminUser->setPassword($hashedPassword);
                    $this->entityManager->flush();
                }
            }
        }

        // Save general settings
        $generalSettings = [
            'store_name' => $data['store_name'] ?? '',
            'store_address' => $data['store_address'] ?? '',
            'store_phone' => $data['store_phone'] ?? '',
            'store_email' => $data['store_email'] ?? '',
            'business_hours' => $data['business_hours'] ?? '',
        ];
        $this->settingsService->saveSettings($generalSettings, 'general');

        // Save email settings (skip password if blank)
        $emailSettings = [
            'smtp_host' => $data['smtp_host'] ?? 'smtp.example.com',
            'smtp_port' => (int)($data['smtp_port'] ?? 587),
            'smtp_username' => $data['smtp_username'] ?? '',
            'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
            'from_email' => $data['from_email'] ?? 'noreply@example.com',
            'from_name' => $data['from_name'] ?? 'Flower Shop',
        ];
        if (!empty($data['smtp_password'])) {
            $emailSettings['smtp_password'] = $data['smtp_password'];
        }
        $this->settingsService->saveSettings($emailSettings, 'email');

        // Save payment settings
        $paymentMethods = $data['payment_methods'] ?? [];
        $paymentSettings = [
            'payment_methods' => json_encode($paymentMethods),
            'payment_gateway' => $data['payment_gateway'] ?? 'manual',
            'currency' => $data['currency'] ?? 'PHP',
        ];
        $this->settingsService->saveSettings($paymentSettings, 'payment');

        // Save security settings
        $securitySettings = [
            'min_password_length' => (int)($data['min_password_length'] ?? 8),
            'session_timeout' => (int)($data['session_timeout'] ?? 60),
        ];
        $this->settingsService->saveSettings($securitySettings, 'security');

        // Save inventory settings
        $inventorySettings = [
            'low_stock_threshold' => (int)($data['low_stock_threshold'] ?? 10),
            'enable_low_stock_alerts' => isset($data['enable_low_stock_alerts']),
            'alert_email' => $data['alert_email'] ?? '',
        ];
        $this->settingsService->saveSettings($inventorySettings, 'inventory');

        // Save orders settings
        $ordersSettings = [
            'order_prefix' => $data['order_prefix'] ?? 'ORD',
            'auto_confirm_orders' => isset($data['auto_confirm_orders']),
            'default_delivery_days' => (int)($data['default_delivery_days'] ?? 3),
        ];
        $this->settingsService->saveSettings($ordersSettings, 'orders');

        $this->addFlash('success', 'Settings have been saved successfully!');
        return $this->redirectToRoute('app_settings_index');
    }


    private function isInMemoryAdmin(): bool
    {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }

        $username = $user->getUserIdentifier();
        return $this->securityConfigService->isInMemoryAdmin($username);
    }

    private function getAdminUsername(): string
    {
        $user = $this->getUser();
        if ($user) {
            return $user->getUserIdentifier();
        }
        return 'admin';
    }
}

