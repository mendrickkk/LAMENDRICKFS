<?php

namespace App\Controller;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use App\Service\EmailVerificationService;

final class SignupController extends AbstractController
{
    #[Route('/signup', name: 'app_signup')]
    public function signup(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher,
        EmailVerificationService $emailVerificationService
    ): Response {
        if ($request->isMethod('POST')) {
            $user = new Users();

            $firstName = $request->request->get('first_name');
            $lastName = $request->request->get('last_name');
            $username = $request->request->get('username');
            $email = $request->request->get('email');
            $plainPassword = $request->request->get('password');

            // Check for duplicate username
            $existingUsername = $entityManager->getRepository(Users::class)
                ->findOneBy(['username' => $username]);
            if ($existingUsername) {
                $this->addFlash('error', 'This username is already taken. Please choose another one.');
                return $this->render('signup/index.html.twig', [
                    'last_username' => $username,
                    'last_email' => $email,
                    'last_first_name' => $firstName,
                    'last_last_name' => $lastName,
                ]);
            }

            // Check for duplicate email
            $existingEmail = $entityManager->getRepository(Users::class)
                ->findOneBy(['email' => $email]);
            if ($existingEmail) {
                $this->addFlash('error', 'This email is already taken. Please choose another one.');
                return $this->render('signup/index.html.twig', [
                    'last_username' => $username,
                    'last_email' => $email,
                    'last_first_name' => $firstName,
                    'last_last_name' => $lastName,
                ]);
            }

            // Conditional role assignment:
            // - If NO admin exists → create ROLE_ADMIN (first-time setup)
            // - If admin EXISTS → create ROLE_CLIENT (normal client registration)
            $existingAdmin = $entityManager->getRepository(Users::class)
                ->findOneBy(['role' => 'ROLE_ADMIN']);
            
            $role = $existingAdmin ? 'ROLE_CLIENT' : 'ROLE_ADMIN';

            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);

            // generate verification token for email confirmation
            try {
                $verificationToken = bin2hex(random_bytes(32));
            } catch (\Throwable) {
                $verificationToken = bin2hex(random_bytes(16));
            }

            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setPassword($hashedPassword);
            $user->setRole($role);
            $user->setIsActive(true);
            $user->setIsVerified(false);
            $user->setVerificationToken($verificationToken);

            $entityManager->persist($user);
            $entityManager->flush();

            // Send verification email (non-blocking of signup flow if mailer fails)
            try {
                $emailVerificationService->sendVerificationEmail($user);
                $this->addFlash('success', 'We sent a verification link to your email. Please verify to activate your account.');
            } catch (\Throwable) {
                $this->addFlash('warning', 'Account created, but we could not send the verification email right now.');
            }

            // Automatically log in the user after account creation
            // Create authentication token
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            
            // Set the token in the security token storage
            $tokenStorage->setToken($token);
            
            // Fire the interactive login event (this triggers the login listener for activity logging)
            $loginEvent = new InteractiveLoginEvent($request, $token);
            $eventDispatcher->dispatch($loginEvent, 'security.interactive_login');
            
            // Show appropriate success message based on role created
            if ($role === 'ROLE_ADMIN') {
                $this->addFlash('success', 'Admin account created successfully! You have been automatically logged in.');
                // Redirect admin to admin page
                return $this->redirectToRoute('app_admin');
            } else {
                $this->addFlash('success', 'Account created successfully! You have been automatically logged in.');
                // Redirect client to client page
                return $this->redirectToRoute('app_client');
            }
        }

        return $this->render('signup/index.html.twig');
    }
}
