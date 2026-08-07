<?php

declare(strict_types=1);

namespace Wisymfony\SlugHistoryBundle\EventListener;

use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Wisymfony\SlugHistoryBundle\Service\SlugManager;

/**
 * Doctrine event listener that detects slug changes on entities.
 *
 * Listens to Doctrine lifecycle events and delegates slug processing to `SlugManager`.
 */
final class DoctrineSlugListener
{

    /**
     * Constructor.
     *
     * @param SlugManager $slugManager Service responsible for managing slug mappings.
     */
    public function __construct(private SlugManager $slugManager){
    }

    /**
     * Handle the `preUpdate` Doctrine event.
     *
     * This method inspects the entity about to be updated and asks `SlugManager`
     * to prepare any slug mappings based on the provided entity change set.
     *
     * @param PreUpdateEventArgs $args Event args containing the entity and change set.
     *
     * @return void
     */
    public function preUpdate(PreUpdateEventArgs $args) : void {
        $object = $args->getObject();
        
        if (!is_object($object)) return;
        
        $this->slugManager->applySlugged($object, $args->getEntityChangeSet());
    }

    /**
     * Handle the `postUpdate` Doctrine event.
     *
     * After an entity update is persisted, persist any slug mappings previously collected.
     *
     * @param PostUpdateEventArgs $args Event args (not used directly).
     *
     * @return void
     */
    public function postUpdate(PostUpdateEventArgs $args) : void {
        $this->slugManager->saveSlugUpdateList();
    }
}
