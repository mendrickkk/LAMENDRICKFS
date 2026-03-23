<?php

namespace App\Controller;

use App\Repository\OrdersRepository;
use App\Repository\StockRepository;
use App\Repository\UsersRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(
        OrdersRepository $ordersRepository,
        StockRepository $stockRepository,
        UsersRepository $usersRepository,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        ActivityLogRepository $activityLogRepository
    ): Response {
        // Check if user is admin or staff
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        // Total Orders Count (visible to both admin and staff)
        $totalOrders = $ordersRepository->count([]);
        
        // Total Revenue (sum of all order totals) - Admin only
        $totalRevenue = null;
        $revenueChange = 0;
        if ($isAdmin) {
            $totalRevenue = $ordersRepository->createQueryBuilder('o')
                ->select('SUM(o.total) as total')
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            // Calculate revenue for this month
            $currentMonth = new \DateTime('first day of this month');
            $revenueThisMonth = $ordersRepository->createQueryBuilder('o')
                ->select('SUM(o.total) as total')
                ->where('o.createdAt >= :monthStart')
                ->setParameter('monthStart', $currentMonth)
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            // Calculate revenue for last month (for comparison)
            $lastMonth = new \DateTime('first day of last month');
            $lastMonthEnd = new \DateTime('last day of last month');
            $revenueLastMonth = $ordersRepository->createQueryBuilder('o')
                ->select('SUM(o.total) as total')
                ->where('o.createdAt >= :monthStart')
                ->andWhere('o.createdAt <= :monthEnd')
                ->setParameter('monthStart', $lastMonth)
                ->setParameter('monthEnd', $lastMonthEnd)
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            // Calculate revenue percentage change
            if ($revenueLastMonth > 0) {
                $revenueChange = (($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100;
            } elseif ($revenueThisMonth > 0) {
                $revenueChange = 100;
            }
        }
        
        // Total Users (all users) - Admin only
        $totalUsers = $isAdmin ? $usersRepository->count([]) : null;
        
        // Total Staff (users with ROLE_STAFF) - Admin only
        $totalStaff = null;
        if ($isAdmin) {
            $totalStaff = $usersRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.role = :role')
                ->setParameter('role', 'ROLE_STAFF')
                ->getQuery()
                ->getSingleScalarResult();
        }
        
        // Low Stock Count (stocks with quantity <= 10) - Visible to both
        $lowStockThreshold = 10;
        $lowStockCount = $stockRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.quantity <= :threshold')
            ->setParameter('threshold', $lowStockThreshold)
            ->getQuery()
            ->getSingleScalarResult();
        
        // Total Categories - Visible to both
        $totalCategories = $categoryRepository->count([]);
        
        // Total Products - Visible to both
        $totalProducts = $productRepository->count([]);
        
        // Total Stocks - Visible to both
        $totalStocks = $stockRepository->count([]);
        
        // Recent Orders (for the table) - Visible to both
        $recentOrders = $ordersRepository->findBy([], ['createdAt' => 'DESC'], 5);

        // Recent Activity Logs - Admin only
        $recentActivities = $isAdmin ? $activityLogRepository->findRecent(10) : null;
        
        return $this->render('admin/index.html.twig', [
            'totalOrders' => $totalOrders,
            'totalRevenue' => $totalRevenue,
            'totalUsers' => $totalUsers,
            'totalStaff' => $totalStaff,
            'lowStockCount' => $lowStockCount,
            'totalCategories' => $totalCategories,
            'totalProducts' => $totalProducts,
            'totalStocks' => $totalStocks,
            'recentOrders' => $recentOrders,
            'recentActivities' => $recentActivities,
            'revenueChange' => round($revenueChange, 1),
            'isAdmin' => $isAdmin,
        ]);
    }
}
