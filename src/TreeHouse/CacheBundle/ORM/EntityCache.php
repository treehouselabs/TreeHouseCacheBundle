<?php

namespace TreeHouse\CacheBundle\ORM;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Proxy\Proxy;
use TreeHouse\Cache\CacheInterface;

/**
 * Service that binds Doctrine's cache provider with our own meta class to
 * cache query results and helps with expiring entities. Used in conjunction
 * with the CachedEntityManager.
 */
class EntityCache
{
    /**
     * Cache to keep track of queries, results, etc. Used to automatically
     * register/invalidate entities.
     *
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var ManagerRegistry
     */
    protected $doctrine;

    /**
     * @param CacheInterface  $cache
     * @param ManagerRegistry $doctrine
     */
    public function __construct(CacheInterface $cache, ManagerRegistry $doctrine)
    {
        $this->cache = $cache;
        $this->doctrine = $doctrine;
    }

    /**
     * Returns format for cache key.
     *
     * @return string
     */
    public function getEntityKeyFormat()
    {
        return '%s:%s';
    }

    /**
     * Returns normalized cache key for entity class name.
     *
     * @param object|string $entity
     *
     * @return string
     */
    public function getEntityClassKey($entity)
    {
        if (is_object($entity)) {
            $entity = get_class($entity);

            $proxyClass = new \ReflectionClass($entity);
            if ($proxyClass->implementsInterface(Proxy::class)) {
                $entity = $proxyClass->getParentClass()->getName();
            }
        }

        // translate colon notation to FQCN
        if (false !== $pos = strrpos($entity, ':')) {
            $meta = $this->doctrine->getManager()->getClassMetadata($entity);
            $entity = $meta->getName();
        }

        return strtr(strtolower($entity), '\\', '-');
    }

    /**
     * Returns normalized cache key for entity instance.
     *
     * @param object $entity
     *
     * @return string
     */
    public function getEntityKey($entity)
    {
        $class = get_class($entity);
        $keys = $this->doctrine->getManagerForClass($class)->getClassMetadata($class)->getIdentifierValues($entity);

        if (sizeof($keys) === 1) {
            $id = reset($keys);
        } else {
            $id = json_encode($keys);
        }

        return sprintf($this->getEntityKeyFormat(), $this->getEntityClassKey($entity), $id);
    }

    /**
     * The ORM cache is used for this, since the actual keys that are
     * used to store the results are changed by Doctrine (due to namespace support).
     *
     * @see https://doctrine-orm.readthedocs.org/en/latest/reference/caching.html#namespaces
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return $this->getOrmCache()->contains($key);
    }

    /**
     * Registers a value to a list of known cache keys. Primarily used to assign
     * entities in a result set to the queries which resulted them. In this
     * case, the entity is the $key and the query is the $value.
     *
     * @param string $key   The list identifier
     * @param string $value The cache key to put in the list
     */
    public function register($key, $value)
    {
        $this->cache->addToList(sprintf('registered:%s', $key), $value);
    }

    /**
     * Registers a query result to the meta cache.
     *
     * @param object $result        An entity from a query result
     * @param string $queryCacheKey The cache key for the query
     */
    public function registerQueryResult($result, $queryCacheKey)
    {
        $this->register($this->getEntityKey($result), $queryCacheKey);
    }

    /**
     * Registers an entity class from a query result to the meta cache.
     *
     * @param string $entityName    An entity class
     * @param string $queryCacheKey The cache key for the query
     */
    public function registerQueryForEntity($entityName, $queryCacheKey)
    {
        $this->register($this->getEntityClassKey($entityName), $queryCacheKey);
    }

    /**
     * Returns a list of cache keys registered to the given list key.
     *
     * @param string $key
     *
     * @return array
     */
    public function getRegisteredKeys($key)
    {
        return $this->cache->getList(sprintf('registered:%s', $key)) ?: [];
    }

    /**
     * Invalidates all cache keys registered to the given list key.
     *
     * @param string $key
     */
    public function invalidate($key)
    {
        // delete the item
        $this->getOrmCache()->delete($key);

        // this is the registration list for this key
        $list = sprintf('registered:%s', $key);

        // now delete all queries which results contain this item
        if (!empty($keys = $this->cache->getList($list))) {
            foreach ($keys as $key) {
                $this->getOrmCache()->delete($key);
            }
        }

        // finally remove the list which held the above queries
        $this->cache->remove($list);
    }

    /**
     * Invalidates a single entity by removing all cached queries registered to
     * this entity.
     *
     * @param object $entity
     */
    public function invalidateEntity($entity)
    {
        $this->invalidate($this->getEntityKey($entity));
    }

    /**
     * Invalidates a complete entity class by removing all cached queries
     * containing this entity.
     *
     * @param object $entity
     */
    public function invalidateEntityQueries($entity)
    {
        $this->invalidate($this->getEntityClassKey($entity));
    }

    /**
     * Clears both the meta cache and Doctrine's entity cache.
     */
    public function clear()
    {
        $this->cache->clear();
        $this->getOrmCache()->flushAll();
    }

    /**
     * @return CacheProvider
     */
    protected function getOrmCache()
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->doctrine->getManager();

        return $entityManager->getConfiguration()->getResultCacheImpl();
    }
}
