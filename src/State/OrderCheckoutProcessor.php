<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\OrderLine;
use App\Entity\Orders;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class OrderCheckoutProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly StockRepository $stockRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Orders
    {
        assert($data instanceof Orders);

        $user = $this->security->getUser();

        // Enforce delivery address
        if (empty(trim((string) $data->getDeliveryAddress()))) {
            throw new BadRequestHttpException('deliveryAddress is required and must not be empty.');
        }

        // Enforce at least one line
        $lines = $data->getLines();
        if ($lines->isEmpty()) {
            throw new BadRequestHttpException('lines must not be empty. Send at least one product line.');
        }

        $total = 0.0;

        foreach ($lines as $line) {
            assert($line instanceof OrderLine);

            $product = $line->getProduct();
            if ($product === null) {
                throw new BadRequestHttpException('Each line must reference a valid product IRI.');
            }

            $qty = $line->getQuantity();
            if ($qty < 1) {
                throw new BadRequestHttpException(sprintf(
                    'Quantity for product "%s" must be at least 1.',
                    $product->getName()
                ));
            }

            // Stock validation — sum all stock entries for this product
            $stocks = $product->getStocks();
            $available = 0;
            foreach ($stocks as $stock) {
                $available += $stock->getQuantity();
            }

            if ($available > 0 && $available < $qty) {
                throw new BadRequestHttpException(sprintf(
                    'Insufficient stock for "%s". Available: %d, requested: %d.',
                    $product->getName(),
                    $available,
                    $qty
                ));
            }

            // Snapshot unit price at order time
            $unitPrice = (float) $product->getPrice();
            $line->setUnitPrice($unitPrice);
            $line->setOrder($data);

            $total += $unitPrice * $qty;
        }

        // Server-side fields — never trust the client body for these
        $data->setClient($user);
        $data->setCustomerName(method_exists($user, 'getFullName') ? $user->getFullName() : ($user->getUserIdentifier()));
        $data->setStatus('pending');
        $data->setCreatedAt(new \DateTime());
        $data->setTotal(round($total, 2));
        $data->setOrderNumber($this->generateOrderNumber());

        $this->em->persist($data);
        $this->em->flush();

        return $data;
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
