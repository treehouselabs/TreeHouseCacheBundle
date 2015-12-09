<?php

namespace TreeHouse\CacheBundle\Tests\ORM;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use TreeHouse\CacheBundle\ORM\EntityCache;
use TreeHouse\FunctionalTestBundle\Entity\EntityMock;

class EntityCacheTest extends KernelTestCase
{
    /**
     * @var EntityCache
     */
    private $cache;

    /**
     * @var CacheProvider
     */
    private $ormCache;

    /**
     * @var EntityMock
     */
    private $entity;

    /**
     * @var string
     */
    private $entityCacheKey;

    /**
     * @var string
     */
    private $entityCacheClass;

    public function testEntityClass()
    {
        $class = 'treehouse-functionaltestbundle-entity-entitymock';

        $this->assertEquals($class, $this->cache->getEntityClassKey($this->entity));
        $this->assertEquals($class, $this->cache->getEntityClassKey(EntityMock::class));
        $this->assertEquals($class, $this->cache->getEntityClassKey('TreeHouseFunctionalTestBundle:EntityMock'));
    }

    public function testGetEntityKey()
    {
        $this->assertEquals(
            'treehouse-functionaltestbundle-entity-entitymock:1234',
            $this->cache->getEntityKey($this->entity)
        );
    }

    public function testHas()
    {
        $this->assertFalse($this->cache->has('foo'));
        $this->ormCache->save('foo', 'bar');
        $this->assertTrue($this->cache->has('foo'));
    }

    public function testRegister()
    {
        $this->assertEquals(
            [],
            $this->cache->getRegisteredKeys('foo'),
            '->getRegisteredKeys() must return an array, even when no keys are registered'
        );

        $this->cache->register('foo', 'bar');
        $this->assertContains('bar', $this->cache->getRegisteredKeys('foo'));

        $this->cache->register('foo', 'baz');
        $this->assertContains('bar', $this->cache->getRegisteredKeys('foo'));
        $this->assertContains('baz', $this->cache->getRegisteredKeys('foo'));
    }

    public function testRegisterQueryResult()
    {
        $key = 'test';

        $this->cache->registerQueryResult($this->entity, $key);
        $this->assertContains($key, $this->cache->getRegisteredKeys($this->entityCacheKey));
    }

    public function testRegisterQueryForEntity()
    {
        $key = 'test';

        $this->cache->registerQueryForEntity($this->entity, $key);
        $this->assertContains($key, $this->cache->getRegisteredKeys($this->entityCacheClass));
    }

    public function testInvalidate()
    {
        $key = 'test';

        // create a fake list
        $this->cache->register($key, uniqid());
        $this->cache->register($key, uniqid());

        $this->ormCache->save($key, 'bar');
        $this->cache->invalidate($key);

        // now both the orm cache and our cache should not contain this list
        $this->assertFalse($this->ormCache->contains($key));
        $this->assertNotContains($key, $this->cache->getRegisteredKeys($key));
    }

    public function testInvalidateEntity()
    {
        $key = 'test';

        // cache a result and class
        $this->cache->registerQueryResult($this->entity, $key);
        $this->cache->registerQueryForEntity($this->entity, $key);

        // expire the entity: entity key should be removed, class key not
        $this->cache->invalidateEntity($this->entity);
        $this->assertNotContains($key, $this->cache->getRegisteredKeys($this->entityCacheKey));
        $this->assertContains($key, $this->cache->getRegisteredKeys($this->entityCacheClass));
    }

    public function testInvalidateEntityQueries()
    {
        $key = 'test';

        // cache a result and class
        $this->cache->registerQueryResult($this->entity, $key);
        $this->cache->registerQueryForEntity($this->entity, $key);

        // expire the entity queries: class key should be removed, entity key not
        $this->cache->invalidateEntityQueries($this->entity);
        $this->assertContains($key, $this->cache->getRegisteredKeys($this->entityCacheKey));
        $this->assertNotContains($key, $this->cache->getRegisteredKeys($this->entityCacheClass));
    }

    public function testClear()
    {
        $key = 'foo';

        $this->cache->register($key, 'bar');
        $this->ormCache->save($key, 'bar');

        $this->assertNotEmpty($this->cache->getRegisteredKeys($key));
        $this->assertTrue($this->cache->has($key));

        $this->cache->clear();

        $this->assertEmpty($this->cache->getRegisteredKeys($key));
        $this->assertFalse($this->cache->has($key));
    }

    protected function setUp()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not loaded');
        }

        parent::setUp();

        static::$kernel = static::createKernel();
        static::$kernel->boot();

        $container = static::$kernel->getContainer();

        /** @var ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var EntityManager $em */
        $em = $doctrine->getManager();

        // create database and load schema
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

        /* @var EntityCache $entityCache */
        $this->cache = $container->get('tree_house_cache.orm.entity_cache');
        $this->cache->clear();

        $this->ormCache = $em->getConfiguration()->getResultCacheImpl();

        $this->entity = new EntityMock(1234);
        $this->entityCacheKey = $this->cache->getEntityKey($this->entity);
        $this->entityCacheClass = $this->cache->getEntityClassKey($this->entity);

        $em->persist($this->entity);
        $em->flush($this->entity);
    }
}
