<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Form\ActivityLogFilterType;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/activity-logs')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    #[Route('/', name: 'app_activity_log_index', methods: ['GET'])]
    public function index(Request $request, ActivityLogRepository $activityLogRepository): Response
    {
        $form = $this->createForm(ActivityLogFilterType::class);
        $form->handleRequest($request);

        $filters = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $filters = $form->getData();
        }

        // Sorting
        $allowedSortFields = ['id', 'userId', 'username', 'role', 'action', 'targetEntity', 'createdAt'];
        $sort = $request->query->get('sort', 'createdAt');
        $order = strtoupper($request->query->get('order', 'DESC'));
        
        // Validate sort field and order
        if (!in_array($sort, $allowedSortFields)) {
            $sort = 'createdAt';
        }
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }

        // Pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 50; // Max 50 items per page
        $offset = ($page - 1) * $limit;

        // Get paginated logs
        $logs = $activityLogRepository->findByFilters($filters, $limit, $offset, $sort, $order);
        $totalLogs = $activityLogRepository->countByFilters($filters);
        $totalPages = (int) ceil($totalLogs / $limit);

        // Fetch statistics
        $stats = [
            'total' => $activityLogRepository->countTotalLogs(),
            'today' => $activityLogRepository->countLogsToday(),
            'week' => $activityLogRepository->countLogsThisWeek(),
            'login' => $activityLogRepository->countLogsByAction('LOGIN'),
            'created' => $activityLogRepository->countLogsByAction('CREATE'),
            'updated' => $activityLogRepository->countLogsByAction('UPDATE'),
            'deleted' => $activityLogRepository->countLogsByAction('DELETE'),
        ];

        // Build query parameters for pagination (preserve filters and sorting)
        // Start with existing query parameters
        $queryParams = $request->query->all();
        
        // Always update sort and order
        $queryParams['sort'] = $sort;
        $queryParams['order'] = $order;
        
        // If form was submitted, update filter parameters
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            if (!empty($formData['user'])) {
                $queryParams['activity_log_filter[user]'] = $formData['user'];
            } else {
                unset($queryParams['activity_log_filter[user]']);
            }
            if (!empty($formData['action'])) {
                $queryParams['activity_log_filter[action]'] = $formData['action'];
            } else {
                unset($queryParams['activity_log_filter[action]']);
            }
            if (!empty($formData['targetEntity'])) {
                $queryParams['activity_log_filter[targetEntity]'] = $formData['targetEntity'];
            } else {
                unset($queryParams['activity_log_filter[targetEntity]']);
            }
            if (!empty($formData['dateFrom'])) {
                $queryParams['activity_log_filter[dateFrom]'] = $formData['dateFrom']->format('Y-m-d');
            } else {
                unset($queryParams['activity_log_filter[dateFrom]']);
            }
            if (!empty($formData['dateTo'])) {
                $queryParams['activity_log_filter[dateTo]'] = $formData['dateTo']->format('Y-m-d');
            } else {
                unset($queryParams['activity_log_filter[dateTo]']);
            }
        }

        return $this->render('admin/activity_log/index.html.twig', [
            'logs' => $logs,
            'form' => $form->createView(),
            'stats' => $stats,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalLogs' => $totalLogs,
            'limit' => $limit,
            'filters' => $filters,
            'queryParams' => $queryParams,
            'currentSort' => $sort,
            'currentOrder' => $order,
        ]);
    }

    #[Route('/{id}', name: 'app_activity_log_show', methods: ['GET'])]
    public function show(ActivityLog $activityLog): Response
    {
        return $this->render('admin/activity_log/show.html.twig', [
            'log' => $activityLog,
        ]);
    }
}

