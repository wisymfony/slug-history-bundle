<?php

declare(strict_types=1);

namespace Wisoft\SlugHistoryBundle\EventListener;

use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Wisoft\SlugHistoryBundle\Service\Manager\SlugManager;

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
    public function __construct(private SlugManager $slugManager)
    {
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
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $object = $args->getObject();

        if (!is_object($object)) {
            return;
        }

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
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->slugManager->saveSlugUpdateList();
    }

    /**
     * Handle the `postPersist` Doctrine event.
     *
     * When a new row is inserted for an entity that exposes a slugged route,
     * any existing redirect entries that point to this new path should be
     * removed so the resource is reachable directly. This method computes the
     * entity's current path(s) and asks `SlugManager` to remove them from the
     * slug history cache after persisting.
     *
     * @param PostPersistEventArgs $args
     *
     * @return void
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $object = $args->getObject();
        if (!is_object($object)) {
            return;
        }

        // Persist any pending slug updates first
        $this->slugManager->saveSlugUpdateList();

        // Compute the entity's current path(s) and remove any redirect entries
        $paths = $this->slugManager->getPathsForEntity($object);
        foreach ($paths as $path) {
            $this->slugManager->removePath($path);
        }
    }
}
