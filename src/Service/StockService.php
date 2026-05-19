<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OrderLine;
use App\Entity\Product;
use App\Entity\Stock;
use App\Repository\StockRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Stock rules (same as admin Stock Management list):
 * - Available qty = SUM(stock.quantity) for all Stock rows linked to the Product.
 * - On checkout: deduct from oldest Stock row first (FIFO by stock.id).
 * - Field decremented: Stock.quantity (int on table `stock`).
 *
 * Status changes (pending → processing → completed) do NOT change stock.
 * Phase 2 (not implemented): restore quantity when order is cancelled.
 */
final class StockService
{
    public function __construct(
        private readonly StockRepository $stockRepository,
    ) {
    }

    public function getAvailableQuantity(Product $product): int
    {
        $productId = $product->getId();
        if ($productId === null) {
            return 0;
        }

        return $this->stockRepository->getAvailableQuantityForProduct($productId);
    }

    /**
     * Validate stock for every line — no DB stock changes.
     *
     * @param iterable<OrderLine> $lines
     *
     * @throws BadRequestHttpException HTTP 400 with message for mobile / API Platform hydra:description
     */
    public function validateOrderLines(iterable $lines): void
    {
        foreach ($lines as $line) {
            $this->validateLine($line);
        }
    }

    /**
     * Decrease stock after all lines passed validation.
     *
     * @param iterable<OrderLine> $lines
     */
    public function reserveOrderLines(iterable $lines): void
    {
        foreach ($lines as $line) {
            $product = $line->getProduct();
            if ($product === null) {
                continue;
            }

            $this->consumeStock($product, $line->getQuantity());
        }
    }

    /**
     * @param iterable<OrderLine> $lines
     */
    public function assertAndReserveForOrderLines(iterable $lines): void
    {
        $this->validateOrderLines($lines);
        $this->reserveOrderLines($lines);
    }

    private function validateLine(OrderLine $line): void
    {
        $product = $line->getProduct();
        if ($product === null) {
            throw new BadRequestHttpException('Each line must reference a valid product.');
        }

        $qty = $line->getQuantity();
        if ($qty < 1) {
            throw new BadRequestHttpException(sprintf(
                'Quantity for product "%s" must be at least 1.',
                $this->productLabel($product)
            ));
        }

        $available = $this->getAvailableQuantity($product);
        $label = $this->productLabel($product);

        if ($available === 0) {
            throw new BadRequestHttpException(sprintf('%s is out of stock.', $label));
        }

        if ($qty > $available) {
            throw new BadRequestHttpException(sprintf(
                'Not enough stock for %s. Only %d available.',
                $label,
                $available
            ));
        }
    }

    private function productLabel(Product $product): string
    {
        $name = $product->getName();

        return $name !== null && $name !== '' ? $name : 'Product';
    }

    private function consumeStock(Product $product, int $quantity): void
    {
        $productId = $product->getId();
        if ($productId === null || $quantity < 1) {
            return;
        }

        $remaining = $quantity;

        /** @var Stock[] $stocks */
        $stocks = $this->stockRepository->findByProductOrdered($productId);

        foreach ($stocks as $stock) {
            if ($remaining <= 0) {
                break;
            }

            $onHand = $stock->getQuantity() ?? 0;
            if ($onHand <= 0) {
                continue;
            }

            $deduct = min($remaining, $onHand);
            $stock->setQuantity($onHand - $deduct);
            $remaining -= $deduct;
        }

        if ($remaining > 0) {
            throw new BadRequestHttpException(sprintf(
                'Not enough stock for %s. Only %d available.',
                $this->productLabel($product),
                $this->getAvailableQuantity($product)
            ));
        }
    }
}
