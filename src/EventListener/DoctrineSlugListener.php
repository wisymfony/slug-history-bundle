<?php

declare(strict_types=1);

namespace Wisymfony\SlugHistoryBundle\EventListener;

use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Wisymfony\SlugHistoryBundle\Service\SlugManager;

final class DoctrineSlugListener
{

    public function __construct(private SlugManager $slugManager){
    }

    public function preUpdate(PreUpdateEventArgs $args) : void {
        $object = $args->getObject();
        
        if (!is_object($object)) return;
        
        $this->slugManager->applySlugged($object, $args->getEntityChangeSet());
    }

    public function postUpdate(PostUpdateEventArgs $args) : void {
        $this->slugManager->saveSlugUpdateList();
    }
}
