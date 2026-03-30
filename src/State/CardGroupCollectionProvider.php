<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CardGroup;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Wraps CachedCountCollectionProvider and batch-loads all OneToMany / ManyToMany
 * associations in a single extra query, eliminating N+1 during serialization.
 *
 * Strategy:
 *  1. Let the inner provider run the paginated query (30 results, 1 SQL).
 *  2. Collect the IDs from those results.
 *  3. Run one DQL query with LEFT JOINs for every collection association,
 *     scoped to those IDs. Doctrine's identity map ensures the same entity
 *     instances are updated in-place with the freshly loaded relations.
 *  4. Return the original paginator — the serializer now finds everything loaded.
 */
final class CardGroupCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly CachedCountCollectionProvider $inner,
        private readonly EntityManagerInterface $em,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $result = $this->inner->provide($operation, $uriVariables, $context);

        if (!$result instanceof \Traversable) {
            return $result;
        }

        $ids = [];
        foreach ($result as $item) {
            if ($item instanceof CardGroup) {
                $ids[] = $item->getId();
            }
        }

        if (empty($ids)) {
            return $result;
        }

        // One query to batch-load all serialized collections for the current page.
        // The identity map guarantees the entities inside $result are the exact same
        // object instances that Doctrine hydrates here, so their collections are
        // populated transparently before the serializer ever touches them.
        $this->em->createQueryBuilder()
            ->select('cg, st, t, cr, le, c, cs, ct')
            ->from(CardGroup::class, 'cg')
            ->leftJoin('cg.subTypes', 'st')
            ->leftJoin('cg.translations', 't')
            ->leftJoin('cg.cardRulings', 'cr')
            ->leftJoin('cg.loreEntries', 'le')
            ->leftJoin('cg.cards', 'c')
            ->leftJoin('c.set', 'cs')
            ->leftJoin('c.translations', 'ct')
            ->where('cg.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
