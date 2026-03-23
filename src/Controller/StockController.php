<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Entity\Product;
use App\Form\StockType;
use App\Repository\StockRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/stock')]
final class StockController extends AbstractController
{
    #[Route(name: 'app_stock_index', methods: ['GET'])]
    public function index(StockRepository $stockRepository): Response
    {
        // Check if user is admin
        if (!$this->isGranted('ROLE_ADMIN')) {
            // Staff: Show only own records
            $stocks = $stockRepository->createQueryBuilder('s')
                ->leftJoin('s.product', 'p')
                ->leftJoin('p.category', 'c')
                ->leftJoin('s.createdBy', 'cb')
                ->addSelect('p')
                ->addSelect('c')
                ->addSelect('cb')
                ->where('s.createdBy = :user')
                ->setParameter('user', $this->getUser())
                ->orderBy('s.id', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            // Admin: Show all records
            // Load stocks with products, categories, and createdBy to ensure images are available
            $stocks = $stockRepository->createQueryBuilder('s')
                ->leftJoin('s.product', 'p')
                ->leftJoin('p.category', 'c')
                ->leftJoin('s.createdBy', 'cb')
                ->addSelect('p')
                ->addSelect('c')
                ->addSelect('cb')
                ->orderBy('s.id', 'DESC')
                ->getQuery()
                ->getResult();
        }
        
        return $this->render('stock/index.html.twig', [
            'stocks' => $stocks,
        ]);
    }

    #[Route('/new', name: 'app_stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, ActivityLogService $activityLogService): Response
    {
        $stock = new Stock();
        $form = $this->createForm(StockType::class, $stock, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingProduct = $form->get('existingProduct')->getData();
            $productName = $form->get('productName')->getData();
            $imageFile = $form->get('productImage')->getData();
            
            // Handle image upload
            $imageFileName = null;
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $originalExtension = pathinfo($imageFile->getClientOriginalName(), PATHINFO_EXTENSION);
                $safeFilename = $slugger->slug($originalFilename);
                
                // Use original extension or default to jpg if not available
                $extension = $originalExtension ?: 'jpg';
                $newFilename = $safeFilename.'-'.uniqid().'.'.$extension;
                
                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/products',
                        $newFilename
                    );
                    $imageFileName = $newFilename;
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload image: '.$e->getMessage());
                }
            }
            
            if ($existingProduct) {
                // Use existing product and optionally update image
                $product = $existingProduct;
                if ($imageFileName) {
                    $product->setImage($imageFileName);
                    $entityManager->persist($product);
                    $entityManager->flush();
                    
                    // Debug: Check what was saved
                    $entityManager->refresh($product);
                    $savedImage = $product->getImage();
                    if ($savedImage !== $imageFileName) {
                        $this->addFlash('warning', 'Image not saved via ORM, using SQL update. Expected: '.$imageFileName.', Got: '.($savedImage ?? 'NULL'));
                        // Use direct SQL update as fallback
                        $connection = $entityManager->getConnection();
                        $connection->executeStatement(
                            'UPDATE product SET image = ? WHERE id = ?',
                            [$imageFileName, $product->getId()]
                        );
                        $entityManager->refresh($product);
                        $this->addFlash('info', 'Image saved via SQL. Product ID: '.$product->getId().', Image: '.$product->getImage());
                    } else {
                        $this->addFlash('info', 'Image saved successfully via ORM. Product ID: '.$product->getId().', Image: '.$product->getImage());
                    }
                }
            } elseif ($productName) {
                // Create new Product from form data
                $product = new Product();
                $product->setName($productName);
                $product->setDescription($form->get('productDescription')->getData());
                $product->setPrice($form->get('productPrice')->getData());
                $product->setCategory($form->get('productCategory')->getData());
                
                // Set createdBy to current user - ensure user is managed
                $user = $this->getUser();
                if ($user) {
                    // Ensure user entity is managed by Doctrine
                    if (!$entityManager->contains($user)) {
                        $user = $entityManager->getRepository(\App\Entity\Users::class)->find($user->getId());
                    }
                    $product->setCreatedBy($user);
                    $this->addFlash('info', 'Setting createdBy to user: '.$user->getUsername().' (ID: '.$user->getId().')');
                } else {
                    $this->addFlash('error', 'No user found - product createdBy will be NULL');
                }
                
                // CRITICAL: Set image BEFORE persisting
                if ($imageFileName) {
                    $product->setImage($imageFileName);
                    $this->addFlash('info', 'Setting image on product before persist: '.$imageFileName);
                }
                
                // Persist and flush product - image should be included
                $entityManager->persist($product);
                $entityManager->flush();
                
                $productId = $product->getId();
                $this->addFlash('info', 'Product created with ID: '.$productId);
                
                // Verify createdBy is saved in database
                $connection = $entityManager->getConnection();
                $createdByCheck = $connection->fetchOne(
                    'SELECT created_by_id FROM product WHERE id = ?',
                    [$productId]
                );
                
                if ($createdByCheck) {
                    $this->addFlash('success', '✓ Product createdBy saved to database: User ID '.$createdByCheck);
                } else {
                    $this->addFlash('error', '✗ Product createdBy is NULL in database - fixing now...');
                    // Fix it directly in database
                    if ($user) {
                        $connection->executeStatement(
                            'UPDATE product SET created_by_id = ? WHERE id = ?',
                            [$user->getId(), $productId]
                        );
                        $this->addFlash('success', '✓ Fixed: Set created_by_id to '.$user->getId());
                    }
                }
                
                // Refresh product to ensure all relationships are loaded
                $entityManager->refresh($product);
                
                // Verify createdBy is set in entity
                if ($product->getCreatedBy()) {
                    $this->addFlash('info', 'Product createdBy entity: '.$product->getCreatedBy()->getUsername());
                } else {
                    $this->addFlash('warning', 'Product createdBy entity is NULL - reloading from database...');
                    // Reload product with createdBy relationship
                    $product = $entityManager->getRepository(Product::class)
                        ->createQueryBuilder('p')
                        ->leftJoin('p.createdBy', 'cb')
                        ->addSelect('cb')
                        ->where('p.id = :id')
                        ->setParameter('id', $productId)
                        ->getQuery()
                        ->getOneOrNullResult();
                }
                
                // Log product creation when created through stock form
                try {
                    $activityLogService->logCreate($product);
                } catch (\Exception $e) {
                    error_log('Failed to log product creation: ' . $e->getMessage());
                }
                
                // ALWAYS use raw PDO to ensure image is saved (bypass Doctrine completely)
                if ($imageFileName) {
                    try {
                        // Get native PDO connection
                        $connection = $entityManager->getConnection();
                        $pdo = $connection->getNativeConnection();
                        
                        // Use prepared statement with explicit parameter binding
                        $stmt = $pdo->prepare('UPDATE product SET image = :image WHERE id = :id');
                        $stmt->bindValue(':image', $imageFileName, \PDO::PARAM_STR);
                        $stmt->bindValue(':id', $productId, \PDO::PARAM_INT);
                        $executed = $stmt->execute();
                        
                        // Verify immediately with fresh query
                        $verifyStmt = $pdo->prepare('SELECT image FROM product WHERE id = :id');
                        $verifyStmt->bindValue(':id', $productId, \PDO::PARAM_INT);
                        $verifyStmt->execute();
                        $savedImage = $verifyStmt->fetchColumn();
                        
                        if ($savedImage === $imageFileName) {
                            $this->addFlash('success', '✓ Image saved via PDO! Product ID: '.$productId.', Image: '.$savedImage);
                            // Reload product with createdBy relationship to ensure it's available
                            $product = $entityManager->getRepository(Product::class)
                                ->createQueryBuilder('p')
                                ->leftJoin('p.createdBy', 'cb')
                                ->addSelect('cb')
                                ->where('p.id = :id')
                                ->setParameter('id', $productId)
                                ->getQuery()
                                ->getOneOrNullResult();
                        } else {
                            $this->addFlash('error', '✗ PDO update failed! Expected: '.$imageFileName.', Got: '.($savedImage ?? 'NULL').', Executed: '.($executed ? 'true' : 'false'));
                        }
                    } catch (\Exception $e) {
                        $this->addFlash('error', '✗ PDO Error: '.$e->getMessage());
                    }
                }
            } else {
                $this->addFlash('error', 'Please either select an existing product or fill in product details.');
                return $this->render('stock/new.html.twig', [
                    'stock' => $stock,
                    'form' => $form,
                ]);
            }
            
            // Link stock to product (ensure product is managed)
            $stock->setProduct($product);
            
            // Set createdBy to current user
            $user = $this->getUser();
            if ($user) {
                $stock->setCreatedBy($user);
            }
            
            // Persist stock
            $entityManager->persist($stock);
            $entityManager->flush();
            
            // Get stock ID for logging
            $stockId = $stock->getId();
            
            // Reload stock with product relationship to ensure it's accessible
            if ($stockId) {
                $stockForLogging = $entityManager->getRepository(Stock::class)
                    ->createQueryBuilder('s')
                    ->leftJoin('s.product', 'p')
                    ->addSelect('p')
                    ->where('s.id = :id')
                    ->setParameter('id', $stockId)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                if ($stockForLogging) {
                    // Manually log stock creation (fallback if EventSubscriber doesn't fire)
                    try {
                        $activityLogService->logCreate($stockForLogging);
                    } catch (\Exception $e) {
                        // Log error but don't break the flow
                        error_log('Failed to log stock creation: ' . $e->getMessage());
                    }
                }
            }

            // FINAL STEP: Ensure image is saved AFTER stock is created (one more time to be sure)
            if ($imageFileName) {
                $productId = $product->getId();
                
                // Use raw PDO one final time to ensure image is saved
                $pdo = $entityManager->getConnection()->getNativeConnection();
                $finalStmt = $pdo->prepare('UPDATE product SET image = :image WHERE id = :id');
                $finalStmt->bindValue(':image', $imageFileName, \PDO::PARAM_STR);
                $finalStmt->bindValue(':id', $productId, \PDO::PARAM_INT);
                $finalStmt->execute();
                
                // Final verification
                $finalVerify = $pdo->prepare('SELECT image FROM product WHERE id = :id');
                $finalVerify->bindValue(':id', $productId, \PDO::PARAM_INT);
                $finalVerify->execute();
                $finalImage = $finalVerify->fetchColumn();
                
                if ($finalImage === $imageFileName) {
                    $this->addFlash('success', '✓ Final verification: Image saved! Product ID: '.$productId);
                } else {
                    $this->addFlash('error', '✗ Final verification failed! Image: '.($finalImage ?? 'NULL'));
                }
            }

            $this->addFlash('success', 'Stock entry created successfully!');
            return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/new.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_stock_show', methods: ['GET'])]
    public function show(Stock $stock): Response
    {
        // Check ownership for staff
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($stock->getCreatedBy() !== $this->getUser()) {
                throw $this->createAccessDeniedException('You can only view your own records.');
            }
        }

        return $this->render('stock/show.html.twig', [
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_stock_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Stock $stock, EntityManagerInterface $entityManager, SluggerInterface $slugger, ActivityLogService $activityLogService): Response
    {
        // Check ownership for staff
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($stock->getCreatedBy() !== $this->getUser()) {
                throw $this->createAccessDeniedException('You can only edit your own records.');
            }
        }

        $form = $this->createForm(StockType::class, $stock, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingProduct = $form->get('existingProduct')->getData();
            $productName = $form->get('productName')->getData();
            $imageFile = $form->get('productImage')->getData();
            
            // Handle image upload
            $imageFileName = null;
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $originalExtension = pathinfo($imageFile->getClientOriginalName(), PATHINFO_EXTENSION);
                $safeFilename = $slugger->slug($originalFilename);
                
                // Use original extension or default to jpg if not available
                $extension = $originalExtension ?: 'jpg';
                $newFilename = $safeFilename.'-'.uniqid().'.'.$extension;
                
                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/products',
                        $newFilename
                    );
                    $imageFileName = $newFilename;
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload image: '.$e->getMessage());
                }
            }
            
            // Get the product to update
            $product = null;
            
            // If existing product is selected, use it
            if ($existingProduct) {
                $stock->setProduct($existingProduct);
                $product = $existingProduct;
            } else {
                // Otherwise, update the current product
                $product = $stock->getProduct();
            }
            
            // Update product information if product exists
            if ($product) {
                // Get form data
                $productName = $form->get('productName')->getData();
                $productDescription = $form->get('productDescription')->getData();
                $productPrice = $form->get('productPrice')->getData();
                $productCategory = $form->get('productCategory')->getData();
                
                // Always update all product fields from form data
                // This ensures all changes are saved, even if only one field changed
                if ($productName !== null && $productName !== '') {
                    $product->setName(trim($productName));
                }
                
                // Description can be empty, so update if provided (even if empty string)
                if ($productDescription !== null) {
                    $product->setDescription(trim($productDescription));
                }
                
                // Price - always update if provided and not empty
                if ($productPrice !== null && $productPrice !== '') {
                    $product->setPrice((float) $productPrice);
                }
                
                // Category - update (can be null)
                $product->setCategory($productCategory);
                
                // Image - update if new image uploaded
                if ($imageFileName) {
                    $product->setImage($imageFileName);
                }
                
                // Ensure product is managed and persisted
                $entityManager->persist($product);
            }
            
            // Persist stock and flush everything together
            $entityManager->persist($stock);
            $entityManager->flush();
            
            // Manually log stock update (fallback if EventSubscriber doesn't fire)
            try {
                $activityLogService->logUpdate($stock);
            } catch (\Exception $e) {
                // Silently fail if logging doesn't work
            }

            $this->addFlash('success', 'Stock entry updated successfully!');
            return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/edit.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/bulk-delete', name: 'app_stock_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request, EntityManagerInterface $entityManager, StockRepository $stockRepository): Response
    {
        if (!$this->isCsrfTokenValid('bulk_delete', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        $idsString = $request->getPayload()->getString('ids');
        if (empty($idsString)) {
            $this->addFlash('error', 'No stocks selected for deletion.');
            return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        $ids = array_filter(array_map('intval', explode(',', $idsString)));
        if (empty($ids)) {
            $this->addFlash('error', 'Invalid stock IDs provided.');
            return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        $stocks = $stockRepository->findBy(['id' => $ids]);
        $deletedCount = 0;
        $productsToDelete = [];

        foreach ($stocks as $stock) {
            $product = $stock->getProduct();
            
            // Remove stock
            $entityManager->remove($stock);
            $deletedCount++;
            
            // Track products that might need deletion
            if ($product && $product->getStocks()->count() === 1) {
                // This is the last stock for this product, mark for deletion
                $productsToDelete[$product->getId()] = $product;
            }
        }

        if ($deletedCount > 0) {
            $entityManager->flush();

            // Delete products that have no more stocks
            foreach ($productsToDelete as $product) {
                // Double-check that product has no stocks left
                if ($product->getStocks()->isEmpty()) {
                    $entityManager->remove($product);
                }
            }
            $entityManager->flush();

            // Check if all stocks were deleted
            $remainingCount = $stockRepository->count([]);
            if ($remainingCount === 0) {
                $connection = $entityManager->getConnection();
                $tableName = $entityManager->getClassMetadata(Stock::class)->getTableName();
                $connection->executeStatement("ALTER TABLE `{$tableName}` AUTO_INCREMENT = 1");
            }

            $this->addFlash('success', sprintf('%d stock entr%s deleted successfully!', $deletedCount, $deletedCount === 1 ? 'y' : 'ies'));
        }

        return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_stock_delete', methods: ['POST'])]
    public function delete(Request $request, Stock $stock, EntityManagerInterface $entityManager, StockRepository $stockRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$stock->getId(), $request->getPayload()->getString('_token'))) {
            // Check ownership for staff
            if (!$this->isGranted('ROLE_ADMIN')) {
                if ($stock->getCreatedBy() !== $this->getUser()) {
                    throw $this->createAccessDeniedException('You can only delete your own records.');
                }
            }

            $product = $stock->getProduct();
            
            // Check if this is the last stock before deleting
            $totalStocks = $stockRepository->count([]);
            $isLastStock = $totalStocks === 1;
            
            // Remove stock first
            $entityManager->remove($stock);
            $entityManager->flush();
            
            // Check if product has any other stocks, if not, delete the product
            if ($product && $product->getStocks()->isEmpty()) {
                $entityManager->remove($product);
                $entityManager->flush();
            }

            // If this was the last stock, reset the AUTO_INCREMENT counter
            if ($isLastStock) {
                $connection = $entityManager->getConnection();
                $tableName = $entityManager->getClassMetadata(Stock::class)->getTableName();
                $connection->executeStatement("ALTER TABLE `{$tableName}` AUTO_INCREMENT = 1");
            }

            $this->addFlash('success', 'Stock deleted successfully!');
        }

        return $this->redirectToRoute('app_stock_index', [], Response::HTTP_SEE_OTHER);
    }
}

