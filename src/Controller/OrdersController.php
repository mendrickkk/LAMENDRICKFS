<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Orders;
use App\Enum\OrderStatus;
use App\Form\OrderStatusType;
use App\Repository\OrdersRepository;
use App\Service\OrderRealtimePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/orders')]
#[IsGranted('ROLE_STAFF')]
final class OrdersController extends AbstractController
{
    #[Route(name: 'app_orders', methods: ['GET'])]
    public function index(OrdersRepository $ordersRepository): Response
    {
        $recentOrders = $ordersRepository->findRecentForAdmin(50);
        $pendingCount = 0;
        foreach ($recentOrders as $order) {
            if (strtolower((string) $order->getStatus()) === 'pending') {
                ++$pendingCount;
            }
        }

        return $this->render('orders/index.html.twig', [
            'recentOrders' => $recentOrders,
            'pendingCount' => $pendingCount,
        ]);
    }

    #[Route('/stream/new', name: 'app_orders_stream_new', methods: ['GET'])]
    public function streamNewOrders(Request $request, OrderRealtimePublisher $publisher): StreamedResponse
    {
        $lastEventId = (int) $request->headers->get('Last-Event-ID', '0');

        $response = new StreamedResponse(function () use ($publisher, $lastEventId): void {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @set_time_limit(0);

            echo ": connected\n\n";
            @ob_flush();
            flush();

            $maxRuntimeSeconds = 10;
            $sleepMicroseconds = 500_000;
            $cursor = $lastEventId;
            $startedAt = time();
            $lastHeartbeatAt = time();

            while ((time() - $startedAt) < $maxRuntimeSeconds) {
                $events = $publisher->readEventsAfter($cursor);

                foreach ($events as $event) {
                    $cursor = max($cursor, (int) $event['eventId']);

                    echo 'id: ' . $event['eventId'] . "\n";
                    echo "event: new-order\n";
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

    #[Route('/realtime/recent', name: 'app_orders_realtime_recent', methods: ['GET'])]
    public function realtimeRecent(OrdersRepository $ordersRepository): JsonResponse
    {
        $orders = $ordersRepository->findRecentForAdmin(20);

        $payload = array_map(
            static fn (Orders $order): array => [
                'orderId' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'total' => (float) $order->getTotal(),
                'createdAt' => $order->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'customerName' => $order->getCustomerName(),
            ],
            $orders
        );

        return new JsonResponse(['orders' => $payload]);
    }

    #[Route('/bulk-delete', name: 'app_orders_bulk_delete', methods: ['POST'])]
    public function bulkDelete(
        Request $request,
        EntityManagerInterface $entityManager,
        OrdersRepository $ordersRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('bulk_delete', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token.');

            return $this->redirectToRoute('app_orders', [], Response::HTTP_SEE_OTHER);
        }

        $idsString = $request->getPayload()->getString('ids');
        if ($idsString === '') {
            $this->addFlash('error', 'No orders selected for deletion.');

            return $this->redirectToRoute('app_orders', [], Response::HTTP_SEE_OTHER);
        }

        $ids = array_filter(array_map('intval', explode(',', $idsString)));
        if ($ids === []) {
            $this->addFlash('error', 'Invalid order IDs provided.');

            return $this->redirectToRoute('app_orders', [], Response::HTTP_SEE_OTHER);
        }

        $orders = $ordersRepository->findBy(['id' => $ids]);
        $deletedCount = 0;

        foreach ($orders as $order) {
            $entityManager->remove($order);
            ++$deletedCount;
        }

        if ($deletedCount > 0) {
            $entityManager->flush();
            $this->addFlash('success', sprintf(
                '%d order%s deleted successfully.',
                $deletedCount,
                $deletedCount === 1 ? '' : 's'
            ));
        } else {
            $this->addFlash('error', 'No matching orders found to delete.');
        }

        return $this->redirectToRoute('app_orders', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_orders_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, int $id, OrdersRepository $ordersRepository): Response
    {
        $order = $ordersRepository->findOneWithDetails($id);
        if ($order === null) {
            throw $this->createNotFoundException('Order not found.');
        }

        if (!$request->isXmlHttpRequest()) {
            return $this->redirectToRoute('app_orders', ['openView' => $id], Response::HTTP_SEE_OTHER);
        }

        return new Response($this->renderView('orders/_show_content.html.twig', [
            'order' => $order,
        ]));
    }

    #[Route('/{id}/edit', name: 'app_orders_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Orders $order,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(OrderStatusType::class, $order);
        $form->handleRequest($request);
        $isAjax = $request->isXmlHttpRequest();

        if ($isAjax && !$form->isSubmitted()) {
            $html = $this->renderView('orders/_status_form.html.twig', [
                'form' => $form,
                'order' => $order,
                'button_label' => 'Update Order Status',
                'action' => $this->generateUrl('app_orders_edit', ['id' => $order->getId()]),
                'show_cancel' => false,
            ]);

            return new Response($html);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $status = $order->getStatus();
            if ($status === null || !OrderStatus::isValid($status)) {
                $this->addFlash('error', 'Invalid order status.');

                if ($isAjax) {
                    return new Response($this->renderView('orders/_status_form.html.twig', [
                        'form' => $form,
                        'order' => $order,
                        'button_label' => 'Update Order Status',
                        'action' => $this->generateUrl('app_orders_edit', ['id' => $order->getId()]),
                        'show_cancel' => false,
                    ]), 422);
                }

                return $this->redirectToRoute('app_orders', ['openEdit' => $order->getId()]);
            }

            $entityManager->flush();

            $this->addFlash('success', sprintf(
                'Order %s updated to "%s".',
                $order->getOrderNumber(),
                $status
            ));

            if ($isAjax) {
                return new JsonResponse([
                    'ok' => true,
                    'redirectUrl' => $this->generateUrl('app_orders'),
                ]);
            }

            return $this->redirectToRoute('app_orders');
        }

        if ($isAjax && $form->isSubmitted() && !$form->isValid()) {
            $html = $this->renderView('orders/_status_form.html.twig', [
                'form' => $form,
                'order' => $order,
                'button_label' => 'Update Order Status',
                'action' => $this->generateUrl('app_orders_edit', ['id' => $order->getId()]),
                'show_cancel' => false,
            ]);

            return new Response($html, 422);
        }

        return $this->redirectToRoute('app_orders', ['openEdit' => $order->getId()], Response::HTTP_SEE_OTHER);
    }
}
