<?php

namespace TreeHouse\CacheBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use TreeHouse\CacheBundle\ORM\EntityCache;

class CacheInvalidationListener
{
    /**
     * @var EntityCache
     */
    protected $entityCache;

    /**
     * @param EntityCache $entityCache
     */
    public function __construct(EntityCache $entityCache)
    {
        $this->entityCache = $entityCache;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        $this->entityCache->invalidateEntity($entity);
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        $this->entityCache->invalidateEntityQueries($entity);
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        $this->entityCache->invalidateEntity($entity);
        $this->entityCache->invalidateEntityQueries($entity);
    }
}
