<?php

namespace App\Controller;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
final class ApiChangePasswordController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/change-password', name: 'api_change_password', methods: ['POST'])]
    public function changePassword(Request $request, #[CurrentUser] ?Users $user): JsonResponse
    {
        if (null === $user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            $data = $request->request->all();
        }

        $current = $this->readField($data, 'current_password', 'currentPassword');
        $new = $this->readField($data, 'new_password', 'newPassword');
        $confirm = $this->readField($data, 'confirm_password', 'confirmPassword');

        if ($current === '' || $new === '') {
            return $this->json([
                'success' => false,
                'message' => 'Current password and new password are required',
            ], 400);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $current)) {
            return $this->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        if (strlen($new) < 8) {
            return $this->json([
                'success' => false,
                'message' => 'New password must be at least 8 characters',
            ], 400);
        }

        if ($confirm !== '' && $new !== $confirm) {
            return $this->json([
                'success' => false,
                'message' => 'New password and confirmation do not match',
            ], 400);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $new));
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readField(array $data, string $snake, string $camel): string
    {
        $value = $data[$snake] ?? $data[$camel] ?? '';

        return \is_string($value) ? trim($value) : '';
    }
}
