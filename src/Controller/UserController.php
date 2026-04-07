<?php

namespace App\Controller;

use App\Entity\Users;
use App\Form\UserType;
use App\Repository\UsersRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    #[Route(name: 'app_user_index', methods: ['GET'])]
    public function index(UsersRepository $usersRepository): Response
    {
        $users = $usersRepository->createQueryBuilder('u')
            ->leftJoin('u.createdBy', 'c')
            ->addSelect('c')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $user = new Users();
        $user->setIsActive(true);
        $form = $this->createForm(UserType::class, $user, ['is_edit' => false]);

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'form' => $form,
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogService $activityLogService
    ): Response {
        $user = new Users();
        $user->setIsActive(true); // Default to active
        
        $form = $this->createForm(UserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);
        $isAjax = $request->isXmlHttpRequest();

        $renderInvalid = function (string $message) use ($isAjax, $user, $form): Response {
            $this->addFlash('error', $message);
            if ($isAjax) {
                $html = $this->renderView('admin/users/_form.html.twig', [
                    'form' => $form,
                    'button_label' => 'Create User',
                    'action' => $this->generateUrl('app_user_new'),
                    'show_cancel' => false,
                ]);
                return new Response($html, 422);
            }

            return $this->render('admin/users/new.html.twig', [
                'user' => $user,
                'form' => $form,
            ]);
        };

        if ($form->isSubmitted() && $form->isValid()) {
            // Check for duplicate username
            $existingUsername = $entityManager->getRepository(Users::class)
                ->findOneBy(['username' => $user->getUsername()]);
            if ($existingUsername) {
                return $renderInvalid('Username already exists. Please choose a different username.');
            }

            // Check for duplicate email
            $existingEmail = $entityManager->getRepository(Users::class)
                ->findOneBy(['email' => $user->getEmail()]);
            if ($existingEmail) {
                return $renderInvalid('Email already exists. Please use a different email address.');
            }

            // Validate password confirmation
            $plainPassword = $form->get('plainPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();
            
            if ($plainPassword !== $confirmPassword) {
                return $renderInvalid('Passwords do not match.');
            }
            
            // Hash password
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            // Set created by
            $currentUser = $this->getUser();
            if ($currentUser instanceof Users) {
                $user->setCreatedBy($currentUser);
            }

            $entityManager->persist($user);
            $entityManager->flush();
            
            // Get user ID for logging
            $userId = $user->getId();
            
            // Reload user to ensure it's accessible
            if ($userId) {
                $userForLogging = $entityManager->getRepository(Users::class)->find($userId);
                if ($userForLogging) {
                    // Manually log user creation
                    try {
                        $activityLogService->logCreate($userForLogging);
                    } catch (\Exception $e) {
                        error_log('Failed to log user creation: ' . $e->getMessage());
                    }
                }
            }

            $this->addFlash('success', sprintf('User "%s" has been created successfully!', $user->getUsername()));
            if ($isAjax) {
                return new JsonResponse([
                    'ok' => true,
                    'redirectUrl' => $this->generateUrl('app_user_index'),
                ]);
            }
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($isAjax && $form->isSubmitted() && !$form->isValid()) {
            $html = $this->renderView('admin/users/_form.html.twig', [
                'form' => $form,
                'button_label' => 'Create User',
                'action' => $this->generateUrl('app_user_new'),
                'show_cancel' => false,
            ]);
            return new Response($html, 422);
        }

        return $this->render('admin/users/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(Request $request, Users $user): Response
    {
        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('admin/users/_show_content.html.twig', [
                'user' => $user,
            ]);
            return new Response($html);
        }

        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Users $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogService $activityLogService
    ): Response {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);
        $isAjax = $request->isXmlHttpRequest();

        $renderEditInvalid = function (string $message) use ($isAjax, $user, $form): Response {
            $this->addFlash('error', $message);
            if ($isAjax) {
                $html = $this->renderView('admin/users/_form.html.twig', [
                    'form' => $form,
                    'button_label' => 'Update User',
                    'action' => $this->generateUrl('app_user_edit', ['id' => $user->getId()]),
                    'show_cancel' => false,
                    'is_edit' => true,
                ]);
                return new Response($html, 422);
            }

            return $this->render('admin/users/edit.html.twig', [
                'user' => $user,
                'form' => $form,
            ]);
        };

        if ($isAjax && !$form->isSubmitted()) {
            $html = $this->renderView('admin/users/_form.html.twig', [
                'form' => $form,
                'button_label' => 'Update User',
                'action' => $this->generateUrl('app_user_edit', ['id' => $user->getId()]),
                'show_cancel' => false,
                'is_edit' => true,
            ]);
            return new Response($html);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Check for duplicate username (excluding current user)
            $existingUsername = $entityManager->getRepository(Users::class)
                ->createQueryBuilder('u')
                ->where('u.username = :username')
                ->andWhere('u.id != :id')
                ->setParameter('username', $user->getUsername())
                ->setParameter('id', $user->getId())
                ->getQuery()
                ->getOneOrNullResult();
            
            if ($existingUsername) {
                return $renderEditInvalid('Username already exists. Please choose a different username.');
            }

            // Check for duplicate email (excluding current user)
            $existingEmail = $entityManager->getRepository(Users::class)
                ->createQueryBuilder('u')
                ->where('u.email = :email')
                ->andWhere('u.id != :id')
                ->setParameter('email', $user->getEmail())
                ->setParameter('id', $user->getId())
                ->getQuery()
                ->getOneOrNullResult();
            
            if ($existingEmail) {
                return $renderEditInvalid('Email already exists. Please use a different email address.');
            }

            // Update password only if provided
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $confirmPassword = $form->get('confirmPassword')->getData();
                
                if (empty($confirmPassword)) {
                    return $renderEditInvalid('Please confirm your password.');
                }
                
                if ($plainPassword !== $confirmPassword) {
                    return $renderEditInvalid('Passwords do not match.');
                }
                
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $entityManager->flush();
            
            // Manually log user update
            try {
                $activityLogService->logUpdate($user);
            } catch (\Exception $e) {
                error_log('Failed to log user update: ' . $e->getMessage());
            }

            $this->addFlash('success', sprintf('User "%s" has been updated successfully!', $user->getUsername()));
            if ($isAjax) {
                return new JsonResponse([
                    'ok' => true,
                    'redirectUrl' => $this->generateUrl('app_user_index'),
                ]);
            }
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($isAjax && $form->isSubmitted() && !$form->isValid()) {
            $html = $this->renderView('admin/users/_form.html.twig', [
                'form' => $form,
                'button_label' => 'Update User',
                'action' => $this->generateUrl('app_user_edit', ['id' => $user->getId()]),
                'show_cancel' => false,
                'is_edit' => true,
            ]);
            return new Response($html, 422);
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Users $user,
        EntityManagerInterface $entityManager,
        UsersRepository $usersRepository,
        ActivityLogService $activityLogService
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->getPayload()->getString('_token'))) {
            // Prevent deleting yourself
            $currentUser = $this->getUser();
            if ($currentUser instanceof Users && $currentUser->getId() === $user->getId()) {
                $this->addFlash('error', 'You cannot delete your own account!');
                return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
            }

            // Prevent deleting the last admin
            if ($user->getRole() === 'ROLE_ADMIN') {
                $adminCount = $usersRepository->createQueryBuilder('u')
                    ->select('COUNT(u.id)')
                    ->where('u.role = :role')
                    ->setParameter('role', 'ROLE_ADMIN')
                    ->getQuery()
                    ->getSingleScalarResult();

                if ($adminCount <= 1) {
                    $this->addFlash('error', 'Cannot delete the last admin account!');
                    return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
                }
            }

            $username = $user->getUsername();
            $userId = $user->getId();
            $description = sprintf('User: %s (ID: %s)', $username, $userId);
            
            // Manually log user deletion BEFORE removal
            try {
                $activityLogService->logDelete($user, $description);
            } catch (\Exception $e) {
                error_log('Failed to log user deletion: ' . $e->getMessage());
            }
            
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', sprintf('User "%s" has been deleted successfully!', $username));
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'app_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(
        Request $request,
        Users $user,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService
    ): Response {
        if (!$this->isCsrfTokenValid('toggle_status' . $user->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        // Prevent disabling yourself
        $currentUser = $this->getUser();
        if ($currentUser instanceof Users && $currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot disable your own account!');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        // Prevent disabling the last admin
        if ($user->getRole() === 'ROLE_ADMIN' && $user->isActive()) {
            $adminCount = $entityManager->getRepository(Users::class)
                ->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.role = :role')
                ->andWhere('u.isActive = :active')
                ->setParameter('role', 'ROLE_ADMIN')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult();

            if ($adminCount <= 1) {
                $this->addFlash('error', 'Cannot disable the last active admin account!');
                return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        // Toggle status
        $newStatus = !$user->isActive();
        $user->setIsActive($newStatus);
        $entityManager->flush();

        // Log the status change
        try {
            $activityLogService->logUpdate($user);
        } catch (\Exception $e) {
            error_log('Failed to log user status change: ' . $e->getMessage());
        }

        $statusText = $newStatus ? 'enabled' : 'disabled';
        $this->addFlash('success', sprintf('User "%s" has been %s successfully!', $user->getUsername(), $statusText));
        
        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/bulk-delete', name: 'app_user_bulk_delete', methods: ['POST'])]
    public function bulkDelete(
        Request $request,
        EntityManagerInterface $entityManager,
        UsersRepository $usersRepository,
        ActivityLogService $activityLogService
    ): Response {
        $token = $request->getPayload()->getString('_token');
        if (!$this->isCsrfTokenValid('bulk_delete', $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        $userIds = $request->getPayload()->all('user_ids');
        
        if (empty($userIds) || !is_array($userIds)) {
            $this->addFlash('error', 'No users selected for deletion.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        $currentUser = $this->getUser();
        $deletedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($userIds as $userId) {
            $user = $usersRepository->find($userId);
            
            if (!$user) {
                $skippedCount++;
                continue;
            }

            // Prevent deleting yourself
            if ($currentUser instanceof Users && $currentUser->getId() === $user->getId()) {
                $errors[] = sprintf('Cannot delete your own account (%s)', $user->getUsername());
                $skippedCount++;
                continue;
            }

            // Prevent deleting the last admin
            if ($user->getRole() === 'ROLE_ADMIN') {
                $adminCount = $usersRepository->createQueryBuilder('u')
                    ->select('COUNT(u.id)')
                    ->where('u.role = :role')
                    ->setParameter('role', 'ROLE_ADMIN')
                    ->getQuery()
                    ->getSingleScalarResult();

                if ($adminCount <= 1) {
                    $errors[] = sprintf('Cannot delete the last admin account (%s)', $user->getUsername());
                    $skippedCount++;
                    continue;
                }
            }

            $username = $user->getUsername();
            $description = sprintf('User: %s (ID: %s)', $username, $user->getId());
            
            // Log deletion before removal
            try {
                $activityLogService->logDelete($user, $description);
            } catch (\Exception $e) {
                error_log('Failed to log user deletion: ' . $e->getMessage());
            }
            
            $entityManager->remove($user);
            $deletedCount++;
        }

        $entityManager->flush();

        if ($deletedCount > 0) {
            $this->addFlash('success', sprintf('%d user(s) deleted successfully!', $deletedCount));
        }
        
        if ($skippedCount > 0) {
            if (!empty($errors)) {
                $this->addFlash('warning', implode(' ', $errors));
            } else {
                $this->addFlash('warning', sprintf('%d user(s) were skipped.', $skippedCount));
            }
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
