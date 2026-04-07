<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\OrdersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/account')]
#[IsGranted('ROLE_CLIENT')]
final class ClientAccountController extends AbstractController
{
    #[Route('', name: 'app_client_account', methods: ['GET'])]
    public function index(
        #[CurrentUser] Users $user,
        OrdersRepository $ordersRepository,
    ): Response {
        $orders = $ordersRepository->findByClientOrdered($user);

        return $this->render('client/accountclient/accountclient.html.twig', [
            'user' => $user,
            'orders' => $orders,
        ]);
    }

    #[Route('/profile', name: 'app_client_account_profile', methods: ['POST'])]
    public function updateProfile(
        Request $request,
        #[CurrentUser] Users $user,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('client_profile', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('app_client_account');
        }

        $first = trim((string) $request->request->get('first_name', ''));
        $last = trim((string) $request->request->get('last_name', ''));

        $user->setFirstName($first !== '' ? $first : null);
        $user->setLastName($last !== '' ? $last : null);

        $entityManager->flush();
        $this->addFlash('success', 'Profile updated.');

        return $this->redirectToRoute('app_client_account');
    }

    #[Route('/password', name: 'app_client_account_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        #[CurrentUser] Users $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        if (!$this->isCsrfTokenValid('client_password', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('app_client_account');
        }

        $current = (string) $request->request->get('current_password', '');
        $new = (string) $request->request->get('new_password', '');
        $confirm = (string) $request->request->get('confirm_password', '');

        if (!$passwordHasher->isPasswordValid($user, $current)) {
            $this->addFlash('error', 'Current password is incorrect.');

            return $this->redirectToRoute('app_client_account');
        }

        if (strlen($new) < 8) {
            $this->addFlash('error', 'New password must be at least 8 characters.');

            return $this->redirectToRoute('app_client_account');
        }

        if ($new !== $confirm) {
            $this->addFlash('error', 'New password and confirmation do not match.');

            return $this->redirectToRoute('app_client_account');
        }

        $user->setPassword($passwordHasher->hashPassword($user, $new));
        $entityManager->flush();
        $this->addFlash('success', 'Password updated.');

        return $this->redirectToRoute('app_client_account');
    }
}
