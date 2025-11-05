<?php

namespace App\Controller;

use App\Repository\OrdersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrdersController extends AbstractController
{
    #[Route('/orders', name: 'app_orders')]
    public function index(OrdersRepository $ordersRepository): Response
    {
        $recentOrders = $ordersRepository->findBy([], ['createdAt' => 'DESC'], 20);
        
        return $this->render('orders/index.html.twig', [
            'controller_name' => 'OrdersController',
            'recentOrders' => $recentOrders,
        ]);
    }
}
