<?php

namespace App\EventListener;

use App\Entity\Card;
use App\Entity\CardGroup;
use App\Entity\MainEffect;
use App\Service\CardSearchUpdater;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final class CardSearchListener
{
    public function __construct(private readonly CardSearchUpdater $updater) {}

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Card) {
            $this->updater->upsertCard($entity->getId());
        }
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Card) {
            $this->updater->upsertCard($entity->getId());
            return;
        }

        if ($entity instanceof CardGroup) {
            $this->updater->upsertByCardGroupId($entity->getId());
            return;
        }

        if ($entity instanceof MainEffect) {
            $this->updater->upsertByMainEffectId($entity->getId());
        }
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Card) {
            $this->updater->deleteCard($entity->getId());
        }
    }
}
