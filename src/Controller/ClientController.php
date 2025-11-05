<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientController extends AbstractController
{
    #[Route('/client', name: 'app_client')]
    public function index(ProductRepository $productRepository): Response
    {
        // Fetch sample products for the flower shop
        $products = $productRepository->findAll();
        
        // If no products exist, create some sample data
        if (empty($products)) {
            $products = $this->getSampleProducts();
        }

        return $this->render('client/index.html.twig', [
            'products' => $products,
        ]);
    }

    private function getSampleProducts(): array
    {
        return [
            (object) [
                'name' => 'Sweater Weather',
                'price' => 64,
                'description' => 'A vibrant bouquet of orange, red, and yellow flowers perfect for autumn.',
                'image' => 'sweater-weather.jpg'
            ],
            (object) [
                'name' => 'Buttercream',
                'price' => 59,
                'description' => 'A soft, pastel bouquet of pink, yellow, and cream flowers.',
                'image' => 'buttercream.jpg'
            ],
            (object) [
                'name' => 'Black Magic',
                'price' => 64,
                'description' => 'A dark, rich bouquet composed entirely of deep red/black roses.',
                'image' => 'black-magic.jpg'
            ],
            (object) [
                'name' => 'Glowing',
                'price' => 64,
                'description' => 'A fresh, bright bouquet of white and light green flowers.',
                'image' => 'glowing.jpg'
            ],
            (object) [
                'name' => 'Autumn Bliss',
                'price' => 55,
                'description' => 'Warm tones of orange and burgundy perfect for fall celebrations.',
                'image' => 'autumn-bliss.jpg'
            ],
            (object) [
                'name' => 'Spring Fresh',
                'price' => 48,
                'description' => 'Light and airy arrangement with pastel colors and delicate blooms.',
                'image' => 'spring-fresh.jpg'
            ]
        ];
    }
}