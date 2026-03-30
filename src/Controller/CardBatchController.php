<?php

namespace App\Controller;

use App\Repository\CardRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class CardBatchController extends AbstractController
{
    public function __construct(
        private readonly CardRepository      $cardRepository,
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('/api/cards/batch', name: 'card_batch', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $references = $body['references'] ?? [];

        if (empty($references) || !is_array($references)) {
            return new JsonResponse(['error' => 'references array is required'], Response::HTTP_BAD_REQUEST);
        }

        if (count($references) > 200) {
            return new JsonResponse(['error' => 'Maximum 200 references per request'], Response::HTTP_BAD_REQUEST);
        }

        $cards = $this->cardRepository->findByReferences($references);

        $locale = $request->query->get('locale');
        $context = ['groups' => ['card:read']];
        if ($locale) {
            $context['locale'] = explode('-', $locale)[0];
        }

        $data = json_decode($this->serializer->serialize($cards, 'json', $context), true);

        return new JsonResponse(array_values($data));
    }
}
