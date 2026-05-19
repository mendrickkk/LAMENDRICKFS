<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\OrderLine;
use App\Entity\Orders;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Creates customer orders (status pending) and reduces Stock.quantity at checkout — not on admin status change.
 */
final class OrderCheckoutProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly StockService $stockService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Orders
    {
        assert($data instanceof Orders);

        return $this->em->wrapInTransaction(function () use ($data): Orders {
            $user = $this->security->getUser();

            if (empty(trim((string) $data->getDeliveryAddress()))) {
                throw new BadRequestHttpException('deliveryAddress is required and must not be empty.');
            }

            $lines = $data->getLines();
            if ($lines->isEmpty()) {
                throw new BadRequestHttpException('lines must not be empty. Send at least one product line.');
            }

            // Stock checks first — no order row and no stock.quantity changes on failure
            $this->stockService->validateOrderLines($lines);

            $total = 0.0;

            foreach ($lines as $line) {
                assert($line instanceof OrderLine);

                $product = $line->getProduct();
                if ($product === null) {
                    throw new BadRequestHttpException('Each line must reference a valid product IRI.');
                }

                $unitPrice = (float) $product->getPrice();
                $line->setUnitPrice($unitPrice);
                $line->setOrder($data);

                $total += $unitPrice * $line->getQuantity();
            }

            $this->stockService->reserveOrderLines($lines);

            $data->setClient($user);
            $data->setCustomerName(method_exists($user, 'getFullName') ? $user->getFullName() : ($user->getUserIdentifier()));
            $data->setStatus('pending');
            $data->setCreatedAt(new \DateTime());
            $data->setTotal(round($total, 2));
            $data->setOrderNumber($this->generateOrderNumber());

            $this->em->persist($data);

            return $data;
        });
    }

    private function generateOrderNumber(): string
    {
        $today = (new \DateTime())->format('Ymd');

        $count = (int) $this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()"
        );

        return sprintf('ORD-%s-%05d', $today, $count + 1);
    }
}
