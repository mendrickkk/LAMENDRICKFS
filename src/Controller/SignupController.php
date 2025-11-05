<?php

namespace App\Controller;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

final class SignupController extends AbstractController
{
    #[Route('/signup', name: 'app_signup')]
    public function signup(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($request->isMethod('POST')) {
            $user = new Users();

            $username = $request->request->get('username');
            $email = $request->request->get('email');
            $plainPassword = $request->request->get('password');
            $selectedRole = $request->request->get('role'); // from dropdown (admin/client)

            // ✅ Convert to proper Symfony role format
            if ($selectedRole === 'admin') {
                $selectedRole = 'ROLE_ADMIN';
            } elseif ($selectedRole === 'client') {
                $selectedRole = 'ROLE_CLIENT';
            } else {
                $selectedRole = 'ROLE_CLIENT'; // default fallback
            }

            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);

            $user->setUsername($username);
            $user->setEmail($email);
            $user->setPassword($hashedPassword);
            $user->setRole($selectedRole); // store ROLE_ADMIN / ROLE_CLIENT

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Account created successfully!');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('signup/index.html.twig');
    }
}
