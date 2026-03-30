<?php

namespace App\Serializer;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CardLocaleNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const LOCALE_CODES = ['fr', 'en', 'de', 'es', 'it'];
    private const ALREADY_CALLED = 'CARD_LOCALE_NORMALIZER_ALREADY_CALLED';

    public function __construct(private readonly RequestStack $requestStack) {}

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        $locale = $this->getRequestedLocale();
        if ($locale === null || !is_array($data)) {
            return $data;
        }

        return $this->flattenLocaleKeys($data, $locale);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if ($context[self::ALREADY_CALLED] ?? false) {
            return false;
        }

        return is_object($data) && str_starts_with(get_class($data), 'App\\Entity\\');
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }

    private function getRequestedLocale(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $locale = $request->query->get('locale');
        if (!$locale) {
            return null;
        }

        $language = strtolower(explode('-', $locale)[0]);

        return in_array($language, self::LOCALE_CODES, true) ? $language : null;
    }

    private function flattenLocaleKeys(array $data, string $locale): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isLocaleKeyedArray($value)) {
                    $data[$key] = $value[$locale] ?? null;
                } else {
                    $data[$key] = $this->flattenLocaleKeys($value, $locale);
                }
            }
        }

        return $data;
    }

    private function isLocaleKeyedArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        foreach (array_keys($array) as $key) {
            if (!in_array($key, self::LOCALE_CODES, true)) {
                return false;
            }
        }

        return true;
    }
}
