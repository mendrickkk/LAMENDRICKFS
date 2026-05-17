<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Product;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * For API JSON/LD output only: exposes absolute URLs in image and imageUrl.
 * The entity still stores only the filename; Twig continues to use asset('uploads/products/...').
 */
#[AutoconfigureTag('serializer.normalizer', ['priority' => 100])]
final class ProductNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'app.product_normalizer.' . self::class;

    public function __construct(
        private readonly UrlHelper $urlHelper,
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if (!$object instanceof Product) {
            throw new \InvalidArgumentException('Expected instance of ' . Product::class . ', got ' . get_debug_type($object));
        }

        $context[self::ALREADY_CALLED] = true;

        try {
            $data = $this->normalizer->normalize($object, $format, $context);
        } finally {
            unset($context[self::ALREADY_CALLED]);
        }

        if (!\is_array($data)) {
            return $data;
        }

        $filename = $object->getImage();
        if ($filename !== null && $filename !== '') {
            $relativePath = '/uploads/products/' . basename($filename);
            $absolute = $this->urlHelper->getAbsoluteUrl($relativePath);
            $data['imageUrl'] = $absolute;
            // Many mobile apps use `image` as Image.uri; DB still stores filename only.
            $data['image'] = $absolute;
        } else {
            $data['imageUrl'] = null;
        }

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Product && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Product::class => false,
        ];
    }
}
