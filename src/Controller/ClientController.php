<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientController extends AbstractController
{
    #[Route('/', name: 'app_client')]
    public function index(ProductRepository $productRepository): Response
    {
        // Fetch products for the flower shop
        $products = $productRepository->findAll();

        return $this->render('client/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/shop', name: 'app_shop')]
    public function shop(ProductRepository $productRepository): Response
    {
        return $this->render('client/shop/index.html.twig', [
            'products' => $productRepository->findAll(),
        ]);
    }

    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('client/about/index.html.twig');
    }

    #[Route('/faq', name: 'app_faq')]
    public function faq(): Response
    {
        return $this->render('client/faq/index.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('client/contact/index.html.twig', [
            // Google Form (third-party): submissions & confirmation are handled by Google Forms.
            'googleContactFormUrl' => 'https://docs.google.com/forms/d/e/1FAIpQLSfHy5yXvudMYdqULVF2F0gas32ZRDeH1kqKfpmkHrOJykTH6g/viewform?usp=header',
        ]);
    }
}
