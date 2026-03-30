<?php

namespace App\Serializer;

use App\Entity\Card;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CardNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'CARD_NORMALIZER_ALREADY_CALLED';

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data)) {
            return $data;
        }

        unset($data['id']);

        if (isset($data['cardGroup']) && is_array($data['cardGroup'])) {
            $cardGroup = $data['cardGroup'];
            unset($data['cardGroup'], $cardGroup['id'], $cardGroup['slug']);
            $data = array_merge($data, $cardGroup);
        }

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if ($context[self::ALREADY_CALLED] ?? false) {
            return false;
        }

        return $data instanceof Card;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Card::class => false];
    }
}
