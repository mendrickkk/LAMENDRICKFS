<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Stock;
use App\Form\ProductType;
use App\Form\StockType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/product')]
final class ProductController extends AbstractController
{
    #[Route(name: 'app_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        // Check if user is admin
        if (!$this->isGranted('ROLE_ADMIN')) {
            // Staff: Show only own records (including products created through stock form)
            $currentUser = $this->getUser();
            if (!$currentUser) {
                $products = [];
            } else {
                $products = $productRepository->createQueryBuilder('p')
                    ->leftJoin('p.createdBy', 'cb')
                    ->addSelect('cb')
                    ->where('p.createdBy = :user')
                    ->setParameter('user', $currentUser)
                    ->orderBy('p.id', 'DESC')
                    ->getQuery()
                    ->getResult();
            }
        } else {
            // Admin: Show all records
            // Load products with createdBy relationship
            $products = $productRepository->createQueryBuilder('p')
                ->leftJoin('p.createdBy', 'cb')
                ->addSelect('cb')
                ->orderBy('p.id', 'DESC')
                ->getQuery()
                ->getResult();
        }
        
        $stock = new Stock();
        $stockForm = $this->createForm(StockType::class, $stock, ['is_edit' => false]);

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'stockForm' => $stockForm,
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set createdBy to current user
            $user = $this->getUser();
            if ($user) {
                $product->setCreatedBy($user);
            }
            
            $entityManager->persist($product);
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Request $request, Product $product): Response
    {
        // Check ownership for staff
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($product->getCreatedBy() !== $this->getUser()) {
                throw $this->createAccessDeniedException('You can only view your own records.');
            }
        }

        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('product/_show_content.html.twig', [
                'product' => $product,
            ]);
            return new Response($html);
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        // Check ownership for staff
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($product->getCreatedBy() !== $this->getUser()) {
                throw $this->createAccessDeniedException('You can only edit your own records.');
            }
        }

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->getString('_token'))) {
            // Check ownership for staff
            if (!$this->isGranted('ROLE_ADMIN')) {
                if ($product->getCreatedBy() !== $this->getUser()) {
                    throw $this->createAccessDeniedException('You can only delete your own records.');
                }
            }

            $entityManager->remove($product);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }
}
