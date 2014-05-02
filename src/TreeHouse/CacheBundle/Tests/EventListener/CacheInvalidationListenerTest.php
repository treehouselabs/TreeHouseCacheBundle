<?php

namespace TreeHouse\CacheBundle\Tests\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use TreeHouse\CacheBundle\EventListener\CacheInvalidationListener;
use TreeHouse\CacheBundle\ORM\EntityCache;
use TreeHouse\FunctionalTestBundle\Entity\EntityMock;

class CacheInvalidationListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|EntityCache
     */
    protected $cache;

    /**
     * @var CacheInvalidationListener
     */
    protected $listener;

    /**
     * @var EntityMock
     */
    protected $entity;

    protected function setUp()
    {
        $this->entity = new EntityMock(1234);

        $this->cache = $this
            ->getMockBuilder(EntityCache::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->listener = new CacheInvalidationListener($this->cache);
    }

    public function testInvalidateEntityOnUpdate()
    {
        $this->cache
            ->expects($this->once())
            ->method('invalidateEntity')
            ->with($this->entity)
        ;

        $this->listener->postUpdate($this->getLifecycleEventArgsMock());
    }

    public function testInvalidateEntityQueriesOnPersist()
    {
        $this->cache
            ->expects($this->once())
            ->method('invalidateEntityQueries')
            ->with($this->entity)
        ;

        $this->listener->postPersist($this->getLifecycleEventArgsMock());
    }

    public function testInvalidateAllOnRemove()
    {
        $this->cache
            ->expects($this->once())
            ->method('invalidateEntity')
            ->with($this->entity)
        ;

        $this->cache
            ->expects($this->once())
            ->method('invalidateEntityQueries')
            ->with($this->entity)
        ;

        $this->listener->preRemove($this->getLifecycleEventArgsMock());
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|LifecycleEventArgs
     */
    protected function getLifecycleEventArgsMock()
    {
        $mock = $this
            ->getMockBuilder(LifecycleEventArgs::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $mock->expects($this->any())
            ->method('getEntity')
            ->will($this->returnValue($this->entity))
        ;

        return $mock;
    }
}
