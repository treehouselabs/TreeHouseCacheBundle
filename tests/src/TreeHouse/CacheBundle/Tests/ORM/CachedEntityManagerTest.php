<?php

namespace TreeHouse\CacheBundle\Tests\ORM;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use TreeHouse\CacheBundle\ORM\CachedEntityManager;
use TreeHouse\CacheBundle\ORM\EntityCache;
use TreeHouse\FunctionalTestBundle\Entity\EntityMock;

class CachedEntityManagerTest extends KernelTestCase
{
    /**
     * @var CachedEntityManager
     */
    private $entityManager;

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

    public function testGetCache()
    {
        $this->assertInstanceOf(EntityCache::class, $this->entityManager->getCache());
    }

    public function testCreateQuery()
    {
        $dql = 'SELECT x FROM TreeHouseFunctionalTestBundle:EntityMock x';
        $query = $this->entityManager->createQuery($dql);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertEquals($dql, $query->getDQL());
    }

    public function testCreateQueryBuilder()
    {
        $builder = $this->entityManager->createQueryBuilder(EntityMock::class, 'foo');

        $this->assertInstanceOf(QueryBuilder::class, $builder);
        $this->assertEquals(EntityMock::class, current($builder->getRootEntities()));
        $this->assertEquals('foo', current($builder->getRootAliases()));
    }

    public function testGetQueryCacheKey()
    {
        $query = $this->createQuery();

        $this->assertInternalType('string', $this->entityManager->getQueryCacheKey($query));
    }

    public function testFind()
    {
        // entity is not in cache
        $this->assertFalse($this->entityManager->has($this->entityCacheKey));

        // perform a lookup
        $this->entityManager->find(EntityMock::class, $this->entity->getId());

        // key should now be in cache
        $this->assertTrue($this->entityManager->has($this->entityCacheKey));
    }

    public function testFindWithoutCache()
    {
        // entity is not in cache
        $this->assertFalse($this->entityManager->has($this->entityCacheKey));

        // perform a lookup
        $this->entityManager->find(EntityMock::class, $this->entity->getId(), false);

        // key should not be in cache
        $this->assertFalse($this->entityManager->has($this->entityCacheKey));
    }

    public function testQuery()
    {
        $query = $this->createQuery();
        $key = $this->entityManager->getQueryCacheKey($query);

        // key should not be in cache
        $this->assertFalse($this->entityManager->has($key));

        // perform the query
        $this->entityManager->query($query);

        // key should now be in cache
        $this->assertTrue($this->entityManager->has($key));

        // key should also be in cached list for the specific entity, and the entire entity class
        $cache = $this->entityManager->getCache();
        $this->assertContains(
            $key,
            $cache->getRegisteredKeys($this->entityCacheKey),
            sprintf('Cache for entity "%s" must contain query with cache key "%s"', $this->entityCacheKey, $key)
        );

        $this->assertContains(
            $key,
            $cache->getRegisteredKeys($this->entityCacheClass),
            sprintf('Cache for class "%s" must contain query with cache key "%s"', $this->entityCacheClass, $key)
        );
    }

    public function testQueryWithFixedKey()
    {
        $key = 'test';

        // key should not be in cache
        $this->assertFalse($this->entityManager->has($key));

        // perform the query with a fixed key
        $query = $this->createQuery();
        $this->entityManager->query($query, null, $key);

        // key should now be in cache
        $this->assertTrue($this->entityManager->has($key));
    }

    public function testQueryWithoutCache()
    {
        $key = 'test';

        // key should not be in cache
        $this->assertFalse($this->entityManager->has($key));

        // perform the query without a ttl set
        $query = $this->createQuery();
        $this->entityManager->query($query, false, $key);

        // key should still not be in cache
        $this->assertFalse($this->entityManager->has($key));
    }

    public function testContainsQueryCache()
    {
        $key = 'test';

        // perform a query
        $query = $this->createQuery();
        $this->entityManager->query($query, null, $key);

        // reverse lookup: get the queries that have a result with this entity, and check if they're still cached
        $cache = $this->entityManager->getCache();
        foreach ($cache->getRegisteredKeys($this->entityCacheKey) as $cacheKey) {
            $this->assertTrue(
                $this->entityManager->has($cacheKey),
                sprintf('Cache must contain key "%s" after query', $cacheKey)
            );
        }
    }

    public function testExpiration()
    {
        $key = 'test';

        // perform a query, cache for 1 second
        $query = $this->createQuery();
        $this->entityManager->query($query, 1, $key);

        // key is now in cache
        $this->assertTrue($this->entityManager->has($key));

        // sleep for 2
        sleep(2);

        // key should not be in cache anymore
        $this->assertFalse($this->entityManager->has($key));
    }

    /**
     * @return Query
     */
    protected function createQuery()
    {
        $builder = $this->entityManager->createQueryBuilder(EntityMock::class, 'x');
        $builder->where('x.id >= :id')->setParameter('id', $this->entity->getId());

        return $builder->getQuery();
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

        $doctrine = $container->get('doctrine');
        /** @var EntityManager $em */
        $em = $doctrine->getManager();

        // create database and load schema
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

        /** @var EntityCache $entityCache */
        $entityCache = $container->get('tree_house_cache.orm.entity_cache');
        $entityCache->clear();

        // create new entity manager
        $this->entityManager = new CachedEntityManager($doctrine, $entityCache);

        $this->entity = new EntityMock(1234);
        $this->entityCacheKey = $entityCache->getEntityKey($this->entity);
        $this->entityCacheClass = $entityCache->getEntityClassKey($this->entity);

        $em->persist($this->entity);
        $em->flush($this->entity);
    }

//    public function testInvalidationByKey()
//    {
//        $key = 'test';
//
//        // perform a query
//        $query = $this->createQuery();
//        $this->entityManager->query($query, null, $key);
//
//        // key is now in cache
//        $this->assertTrue($this->entityManager->has($key));
//
//        // expire this key
//        $cache = $this->entityManager->getCache();
//        $cache->invalidate($key);
//
//        $this->assertFalse($this->entityManager->has($key));
//
//        // after expiration, cache no longer holds entity key
//        $this->assertFalse($this->entityManager->has($this->entityCacheKey));
//
//        // queries with this entity in result have to be expired also
//        foreach ($keys as $cacheKey) {
//            $this->assertFalse($this->entityManager->has($cacheKey), sprintf('Cache should not have queries that contain expired entity ("%s" was found)', $cacheKey));
//        }
//    }
//
//
//    public function testClassCacheExpiration()
//    {
//        $key = 'test.cm';
//
//        // perform a query
//        $q = $this->entityManager->createQuery('');
//        $q->setParameter('id', $this->entity->getId());
//        $res = $this->entityManager->query($q, null, $key);
//
//        // remember the cached keys
//        $keys = array();
//        $classKeys = $this->cache->getRegisteredKeys($this->entityClass);
//
//        // cache some individual items
//        foreach ($res as $item) {
//            $itemKeys = $this->cache->getRegisteredKeys($this->cache->getEntityKey($item));
//            $keys[$item->getId()] = $itemKeys;
//
//            $this->assertContains($key, $itemKeys);
//        }
//
//        // expire the entire entity class
//        $this->cache->invalidateEntityQueries($this->entityClass);
//
//        // the query we performed should be removed from cache
//        $this->assertFalse($this->entityManager->has($key), sprintf('Cache should not have query that contains entity of expired class ("%s" was found)', $key));
//
//        // individual items should be removed from cache
//        foreach ($keys as $id => $itemKeys) {
//            foreach ($itemKeys as $key) {
//                $this->assertFalse($this->entityManager->has($key), sprintf('Cache should not have query that contains entity of expired class ("%s" was found)', $key));
//            }
//        }
//
//        // all queries that have these type of entities should be removed
//        foreach ($classKeys as $key) {
//            $this->assertFalse($this->entityManager->has($key));
//        }
//    }
}
