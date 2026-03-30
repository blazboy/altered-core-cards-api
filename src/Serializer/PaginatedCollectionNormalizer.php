<?php

namespace App\Serializer;

use ApiPlatform\State\Pagination\PaginatorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Wraps a PaginatorInterface into a JSON envelope when the format is plain JSON.
 *
 * Output:
 * {
 *   "member":      [...],          // the page items
 *   "totalItems":  1067194,
 *   "currentPage": 1,
 *   "itemsPerPage": 30,
 *   "lastPage":    35573
 * }
 *
 * Runs at priority 64, well above the generic ObjectNormalizer (-1000) and
 * ArrayDenormalizer (0), so it fires before anything that would simply iterate
 * the paginator into a flat array.
 */
#[AutoconfigureTag('serializer.normalizer', ['priority' => 64])]
final class PaginatedCollectionNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'APP_PAGINATED_COLLECTION_NORMALIZER_ALREADY_CALLED';

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof PaginatorInterface
            && $format === 'json'
            && !($context[self::ALREADY_CALLED] ?? false);
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        assert($data instanceof PaginatorInterface);

        $context[self::ALREADY_CALLED] = true;

        $items = [];
        foreach ($data as $item) {
            $items[] = $this->normalizer->normalize($item, $format, $context);
        }

        return [
            'member'       => $items,
            'totalItems'   => (int) $data->getTotalItems(),
            'currentPage'  => (int) $data->getCurrentPage(),
            'itemsPerPage' => (int) $data->getItemsPerPage(),
            'lastPage'     => (int) $data->getLastPage(),
        ];
    }

    public function getSupportedTypes(?string $format): array
    {
        return [PaginatorInterface::class => false];
    }
}
