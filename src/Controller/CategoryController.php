<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/category')]
final class CategoryController extends AbstractController
{
    #[Route(name: 'app_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        // Check if user is admin
        if (!$this->isGranted('ROLE_ADMIN')) {
            // Staff: Show only own records
            $categories = $categoryRepository->createQueryBuilder('c')
                ->leftJoin('c.createdBy', 'cb')
                ->addSelect('cb')
                ->where('c.createdBy = :user')
                ->setParameter('user', $this->getUser())
                ->orderBy('c.id', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            // Admin: Show all records
            // Load categories with createdBy relationship
            $categories = $categoryRepository->createQueryBuilder('c')
                ->leftJoin('c.createdBy', 'cb')
                ->addSelect('cb')
                ->orderBy('c.id', 'DESC')
                ->getQuery()
                ->getResult();
        }
        
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);

        return $this->render('category/index.html.twig', [
            'categories' => $categories,
            'form' => $form,
        ]);
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogService $activityLogService): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        $isAjax = $request->isXmlHttpRequest();

        if ($form->isSubmitted() && $form->isValid()) {
            // Set createdBy to current user
            $user = $this->getUser();
            if ($user) {
                $category->setCreatedBy($user);
            }
            
            $entityManager->persist($category);
            $entityManager->flush();
            
            // Get category ID for logging
            $categoryId = $category->getId();
            
            // Reload category to ensure it's accessible
            if ($categoryId) {
                $categoryForLogging = $entityManager->getRepository(Category::class)->find($categoryId);
                if ($categoryForLogging) {
                    // Manually log category creation
                    try {
                        $activityLogService->logCreate($categoryForLogging);
                    } catch (\Exception $e) {
                        error_log('Failed to log category creation: ' . $e->getMessage());
                    }
                }
            }

            $this->addFlash('success', 'Category created successfully!');
            if ($isAjax) {
                return new JsonResponse([
                    'ok' => true,
                    'redirectUrl' => $this->generateUrl('app_category_index'),
                ]);
            }

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($isAjax && $form->isSubmitted() && !$form->isValid()) {
            $html = $this->renderView('category/_form.html.twig', [
                'form' => $form,
                'button_label' => 'Create Category',
                'action' => $this->generateUrl('app_category_new'),
                'show_cancel' => false,
            ]);

            return new Response($html, 422);
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_category_show', methods: ['GET'])]
    public function show(Request $request, Category $category): Response
    {
        // Check ownership for staff
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($category->getCreatedBy() !== $this->getUser()) {
                throw $this->createAccessDeniedException('You can only view your own records.');
            }
        }

        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('category/_show_content.html.twig', [
                'category' => $category,
            ]);
            return new Response($html);
        }

        return $this->render('category/show.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager, ActivityLogService $activityLogService): Response
    {
        // Check ownership for staff
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($category->getCreatedBy() !== $this->getUser()) {
                throw $this->createAccessDeniedException('You can only edit your own records.');
            }
        }

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);
        $isAjax = $request->isXmlHttpRequest();

        if ($isAjax && !$form->isSubmitted()) {
            $html = $this->renderView('category/_form.html.twig', [
                'form' => $form,
                'button_label' => 'Update Category',
                'action' => $this->generateUrl('app_category_edit', ['id' => $category->getId()]),
                'show_cancel' => false,
            ]);
            return new Response($html);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            
            // Manually log category update
            try {
                $activityLogService->logUpdate($category);
            } catch (\Exception $e) {
                error_log('Failed to log category update: ' . $e->getMessage());
            }

            $this->addFlash('success', 'Category updated successfully!');
            if ($isAjax) {
                return new JsonResponse([
                    'ok' => true,
                    'redirectUrl' => $this->generateUrl('app_category_index'),
                ]);
            }
            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($isAjax && $form->isSubmitted() && !$form->isValid()) {
            $html = $this->renderView('category/_form.html.twig', [
                'form' => $form,
                'button_label' => 'Update Category',
                'action' => $this->generateUrl('app_category_edit', ['id' => $category->getId()]),
                'show_cancel' => false,
            ]);
            return new Response($html, 422);
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/bulk-delete', name: 'app_category_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request, EntityManagerInterface $entityManager, CategoryRepository $categoryRepository, ActivityLogService $activityLogService): Response
    {
        if (!$this->isCsrfTokenValid('bulk_delete', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        $idsString = $request->getPayload()->getString('ids');
        if (empty($idsString)) {
            $this->addFlash('error', 'No categories selected for deletion.');
            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        $ids = array_filter(array_map('intval', explode(',', $idsString)));
        if (empty($ids)) {
            $this->addFlash('error', 'Invalid category IDs provided.');
            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        $categories = $categoryRepository->findBy(['id' => $ids]);
        $deletedCount = 0;
        $errorCount = 0;

        foreach ($categories as $category) {
            // Check if category has products
            if ($category->getProducts()->count() > 0) {
                $errorCount++;
                continue;
            }

            // Capture category data before deletion for logging
            $categoryId = $category->getId();
            $categoryName = $category->getName();
            $description = sprintf('Category: %s (ID: %s)', $categoryName, $categoryId);
            
            // Manually log category deletion BEFORE removal
            try {
                $activityLogService->logDelete($category, $description);
            } catch (\Exception $e) {
                error_log('Failed to log category deletion: ' . $e->getMessage());
            }
            
            $entityManager->remove($category);
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            $entityManager->flush();

            // Check if all categories were deleted
            $remainingCount = $categoryRepository->count([]);
            if ($remainingCount === 0) {
                $connection = $entityManager->getConnection();
                $tableName = $entityManager->getClassMetadata(Category::class)->getTableName();
                $connection->executeStatement("ALTER TABLE `{$tableName}` AUTO_INCREMENT = 1");
            }

            if ($deletedCount === count($ids)) {
                $this->addFlash('success', sprintf('%d categor%s deleted successfully!', $deletedCount, $deletedCount === 1 ? 'y' : 'ies'));
            } else {
                $this->addFlash('warning', sprintf('%d categor%s deleted. %d categor%s could not be deleted because they have products assigned.', $deletedCount, $deletedCount === 1 ? 'y' : 'ies', $errorCount, $errorCount === 1 ? 'y' : 'ies'));
            }
        } else {
            $this->addFlash('error', 'No categories could be deleted. All selected categories have products assigned to them.');
        }

        return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager, CategoryRepository $categoryRepository, ActivityLogService $activityLogService): Response
    {
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->getPayload()->getString('_token'))) {
            // Check ownership for staff
            if (!$this->isGranted('ROLE_ADMIN')) {
                if ($category->getCreatedBy() !== $this->getUser()) {
                    throw $this->createAccessDeniedException('You can only delete your own records.');
                }
            }

            // Check if category has products
            if ($category->getProducts()->count() > 0) {
                $this->addFlash('error', 'Cannot delete category that has products assigned to it. Please remove or reassign products first.');
                return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
            }

            // Check if this is the last category before deleting
            $totalCategories = $categoryRepository->count([]);
            $isLastCategory = $totalCategories === 1;
            
            // Capture category data before deletion for logging
            $categoryId = $category->getId();
            $categoryName = $category->getName();
            $description = sprintf('Category: %s (ID: %s)', $categoryName, $categoryId);
            
            // Manually log category deletion BEFORE removal
            try {
                $activityLogService->logDelete($category, $description);
            } catch (\Exception $e) {
                error_log('Failed to log category deletion: ' . $e->getMessage());
            }

            $entityManager->remove($category);
            $entityManager->flush();

            // If this was the last category, reset the AUTO_INCREMENT counter
            if ($isLastCategory) {
                $connection = $entityManager->getConnection();
                $tableName = $entityManager->getClassMetadata(Category::class)->getTableName();
                $connection->executeStatement("ALTER TABLE `{$tableName}` AUTO_INCREMENT = 1");
            }

            $this->addFlash('success', 'Category deleted successfully!');
        }

        return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
    }
}

