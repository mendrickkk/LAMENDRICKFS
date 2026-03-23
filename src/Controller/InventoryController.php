<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Entity\Stock;
use App\Form\InventoryType;
use App\Repository\InventoryRepository;
use App\Repository\StockRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inventory')]
final class InventoryController extends AbstractController
{
    #[Route(name: 'app_inventory_index', methods: ['GET'])]
    public function index(InventoryRepository $inventoryRepository): Response
    {
        // Load inventory with products, ordered by most recent first
        $inventories = $inventoryRepository->createQueryBuilder('i')
            ->leftJoin('i.product', 'p')
            ->addSelect('p')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('stock/inventory/index.html.twig', [
            'inventories' => $inventories,
        ]);
    }

    #[Route('/new', name: 'app_inventory_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        StockRepository $stockRepository,
        ActivityLogService $activityLogService
    ): Response {
        $inventory = new Inventory();
        $form = $this->createForm(InventoryType::class, $inventory, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product = $inventory->getProduct();
            $quantity = $inventory->getQuantity();
            
            // Create inventory record (history)
            $entityManager->persist($inventory);
            $entityManager->flush();
            
            // Get inventory ID for logging
            $inventoryId = $inventory->getId();
            
            // Reload inventory with product relationship to ensure it's accessible
            if ($inventoryId) {
                $inventoryForLogging = $entityManager->getRepository(Inventory::class)
                    ->createQueryBuilder('i')
                    ->leftJoin('i.product', 'p')
                    ->addSelect('p')
                    ->where('i.id = :id')
                    ->setParameter('id', $inventoryId)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                if ($inventoryForLogging) {
                    // Manually log inventory creation
                    try {
                        $activityLogService->logCreate($inventoryForLogging);
                    } catch (\Exception $e) {
                        error_log('Failed to log inventory creation: ' . $e->getMessage());
                    }
                }
            }
            
            // Find or create Stock record for this product
            $existingStock = $stockRepository->createQueryBuilder('s')
                ->where('s.product = :product')
                ->setParameter('product', $product)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            if ($existingStock) {
                // Update existing stock quantity
                $newQuantity = $existingStock->getQuantity() + $quantity;
                $existingStock->setQuantity($newQuantity);
                $entityManager->persist($existingStock);
            } else {
                // Create new stock record
                $newStock = new Stock();
                $newStock->setProduct($product);
                $newStock->setQuantity($quantity);
                $entityManager->persist($newStock);
            }
            
            $entityManager->flush();

            $this->addFlash('success', sprintf(
                'Inventory added successfully! Added %d units to %s.',
                $quantity,
                $product->getName()
            ));
            return $this->redirectToRoute('app_inventory_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/inventory/new.html.twig', [
            'inventory' => $inventory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_inventory_show', methods: ['GET'])]
    public function show(Inventory $inventory): Response
    {
        return $this->render('stock/inventory/show.html.twig', [
            'inventory' => $inventory,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_inventory_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Inventory $inventory, 
        EntityManagerInterface $entityManager,
        StockRepository $stockRepository,
        ActivityLogService $activityLogService
    ): Response {
        $oldQuantity = $inventory->getQuantity();
        $oldProduct = $inventory->getProduct();
        
        $form = $this->createForm(InventoryType::class, $inventory, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newProduct = $inventory->getProduct();
            $newQuantity = $inventory->getQuantity();
            
            // Update inventory record
            $entityManager->flush();
            
            // Adjust Stock quantities
            // First, reverse the old inventory movement
            if ($oldProduct) {
                $oldStock = $stockRepository->createQueryBuilder('s')
                    ->where('s.product = :product')
                    ->setParameter('product', $oldProduct)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                if ($oldStock) {
                    $adjustedQuantity = $oldStock->getQuantity() - $oldQuantity;
                    if ($adjustedQuantity < 0) {
                        $adjustedQuantity = 0;
                    }
                    $oldStock->setQuantity($adjustedQuantity);
                    $entityManager->persist($oldStock);
                }
            }
            
            // Then, apply the new inventory movement
            $newStock = $stockRepository->createQueryBuilder('s')
                ->where('s.product = :product')
                ->setParameter('product', $newProduct)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            if ($newStock) {
                $newStockQuantity = $newStock->getQuantity() + $newQuantity;
                $newStock->setQuantity($newStockQuantity);
                $entityManager->persist($newStock);
            } else {
                // Create new stock if it doesn't exist
                $newStock = new Stock();
                $newStock->setProduct($newProduct);
                $newStock->setQuantity($newQuantity);
                $entityManager->persist($newStock);
            }
            
            $entityManager->flush();
            
            // Reload inventory with product relationship for logging
            $inventoryId = $inventory->getId();
            if ($inventoryId) {
                $inventoryForLogging = $entityManager->getRepository(Inventory::class)
                    ->createQueryBuilder('i')
                    ->leftJoin('i.product', 'p')
                    ->addSelect('p')
                    ->where('i.id = :id')
                    ->setParameter('id', $inventoryId)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                if ($inventoryForLogging) {
                    // Manually log inventory update
                    try {
                        $activityLogService->logUpdate($inventoryForLogging);
                    } catch (\Exception $e) {
                        error_log('Failed to log inventory update: ' . $e->getMessage());
                    }
                }
            }

            $this->addFlash('success', 'Inventory updated successfully!');
            return $this->redirectToRoute('app_inventory_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/inventory/edit.html.twig', [
            'inventory' => $inventory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_inventory_delete', methods: ['POST'])]
    public function delete(
        Request $request, 
        Inventory $inventory, 
        EntityManagerInterface $entityManager,
        StockRepository $stockRepository,
        ActivityLogService $activityLogService
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$inventory->getId(), $request->getPayload()->getString('_token'))) {
            $product = $inventory->getProduct();
            $quantity = $inventory->getQuantity();
            
            // Reverse the inventory movement in Stock
            $stock = $stockRepository->createQueryBuilder('s')
                ->where('s.product = :product')
                ->setParameter('product', $product)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            if ($stock) {
                $adjustedQuantity = $stock->getQuantity() - $quantity;
                if ($adjustedQuantity < 0) {
                    $adjustedQuantity = 0;
                }
                $stock->setQuantity($adjustedQuantity);
                $entityManager->persist($stock);
            }
            
            // Capture inventory data before deletion for logging
            $inventoryId = $inventory->getId();
            $product = $inventory->getProduct();
            $quantity = $inventory->getQuantity();
            $productName = $product ? $product->getName() : 'Unknown';
            $description = sprintf('Inventory: Product %s - Quantity: %s (ID: %s)', $productName, $quantity, $inventoryId);
            
            // Manually log inventory deletion BEFORE removal
            try {
                $activityLogService->logDelete($inventory, $description);
            } catch (\Exception $e) {
                error_log('Failed to log inventory deletion: ' . $e->getMessage());
            }
            
            // Delete inventory record
            $entityManager->remove($inventory);
            $entityManager->flush();

            $this->addFlash('success', 'Inventory record deleted successfully!');
        }

        return $this->redirectToRoute('app_inventory_index', [], Response::HTTP_SEE_OTHER);
    }
}

