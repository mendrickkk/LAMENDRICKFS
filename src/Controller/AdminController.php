<?php

namespace App\Controller;

use App\Repository\OrdersRepository;
use App\Repository\StockRepository;
use App\Repository\UsersRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\AdminMetricsRealtimePublisher;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    #[IsGranted('ROLE_STAFF')]
    public function index(
        OrdersRepository $ordersRepository,
        StockRepository $stockRepository,
        UsersRepository $usersRepository,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        ActivityLogRepository $activityLogRepository
    ): Response {
        $metrics = $this->buildMetrics(
            $ordersRepository,
            $stockRepository,
            $usersRepository,
            $categoryRepository,
            $productRepository,
            $activityLogRepository,
        );

        return $this->render('admin/index.html.twig', $metrics);
    }

    #[Route('/admin/stream/metrics', name: 'app_admin_stream_metrics', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function streamMetrics(Request $request, AdminMetricsRealtimePublisher $publisher): StreamedResponse
    {
        $lastEventId = (int) $request->headers->get('Last-Event-ID', '0');

        $response = new StreamedResponse(function () use ($publisher, $lastEventId): void {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @set_time_limit(0);

            echo ": connected\n\n";
            @ob_flush();
            flush();

            $maxRuntimeSeconds = 60;
            $sleepMicroseconds = 1_000_000;
            $cursor = $lastEventId;
            $startedAt = time();
            $lastHeartbeatAt = time();

            while ((time() - $startedAt) < $maxRuntimeSeconds) {
                $events = $publisher->readEventsAfter($cursor);

                foreach ($events as $event) {
                    $cursor = max($cursor, (int) $event['eventId']);

                    echo 'id: ' . $event['eventId'] . "\n";
                    echo "event: metrics-changed\n";
                    echo 'data: ' . json_encode($event, JSON_UNESCAPED_SLASHES) . "\n\n";
                }

                if ((time() - $lastHeartbeatAt) >= 5) {
                    echo ": heartbeat\n\n";
                    $lastHeartbeatAt = time();
                }

                @ob_flush();
                flush();

                usleep($sleepMicroseconds);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    #[Route('/admin/realtime/metrics', name: 'app_admin_realtime_metrics', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function realtimeMetrics(
        OrdersRepository $ordersRepository,
        StockRepository $stockRepository,
        UsersRepository $usersRepository,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        ActivityLogRepository $activityLogRepository,
    ): JsonResponse {
        $metrics = $this->buildMetrics(
            $ordersRepository,
            $stockRepository,
            $usersRepository,
            $categoryRepository,
            $productRepository,
            $activityLogRepository,
            includeRecent: false,
        );

        return new JsonResponse(['metrics' => $metrics]);
    }

    private function buildMetrics(
        OrdersRepository $ordersRepository,
        StockRepository $stockRepository,
        UsersRepository $usersRepository,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        ActivityLogRepository $activityLogRepository,
        bool $includeRecent = true,
    ): array {
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
        $recentOrders = $includeRecent ? $ordersRepository->findBy([], ['createdAt' => 'DESC'], 5) : [];

        // Recent Activity Logs - Admin only
        $recentActivities = ($includeRecent && $isAdmin) ? $activityLogRepository->findRecent(10) : null;
        
        return [
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
        ];
    }
}
