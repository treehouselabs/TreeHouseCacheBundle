<?php

namespace TreeHouse\CacheBundle\ORM;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

/**
 * Wrapper around Doctrine's entitymanager, which automatically caches queries
 * and individual entities. It also handles cache invalidation based on entity
 * events.
 *
 * There are two main ways to use this:
 *
 * 1. Use the get() method to find a cached instance of an entity, by id. This
 *    is meant as a super-fast, almost key-value store like, repository of
 *    entities.
 * 2. Use the query() method to query the database, but to use cached results
 *    where possible.
 *
 * The manager keeps track of cached queries and entities. Because it handles
 * entity lifecycle events, it knows when to invalidate a single entity or even
 * queries. For instance: when an entity is saved, it is purged from the cache.
 * When a new instance of that entity is added to the database, all cached
 * queries (and their result) that reference that entity are purged, so the
 * query may contain the new entity when executed again.
 */
class CachedEntityManager
{
    /**
     * @var ManagerRegistry
     */
    protected $doctrine;

    /**
     * @var EntityCache
     */
    protected $cache;

    /**
     * @param ManagerRegistry $doctrine
     * @param EntityCache     $cache
     */
    public function __construct(ManagerRegistry $doctrine, EntityCache $cache)
    {
        $this->doctrine = $doctrine;
        $this->cache    = $cache;
    }

    /**
     * @return EntityCache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param string $dql
     *
     * @return Query
     */
    public function createQuery($dql)
    {
        return $this->getEntityManager()->createQuery($dql);
    }

    /**
     * @param string $entity
     * @param string $alias
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder($entity, $alias)
    {
        return $this->getRepository($entity)->createQueryBuilder($alias);
    }

    /**
     * @param Query $query
     *
     * @return string
     */
    public function getQueryCacheKey(Query $query)
    {
        $hints = $query->getHints();
        ksort($hints);

        return md5(
            $query->getDql() . var_export($query->getParameters(), true) . var_export($hints, true) .
            '&firstResult=' . $query->getFirstResult() . '&maxResult=' . $query->getMaxResults() .
            '&hydrationMode=' . $query->getHydrationMode()
        );
    }

    /**
     * @param string $key
     *
     * @return boolean
     */
    public function has($key)
    {
        return $this->cache->has($key);
    }

    /**
     * @param string       $entity
     * @param integer      $id
     * @param integer|null $ttl
     *
     * @return object|null
     */
    public function find($entity, $id, $ttl = null)
    {
        $query = $this->createQueryBuilder($entity, 'x')
            ->select('x')
            ->where('x.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
        ;

        if ($ttl !== false) {
            $key = sprintf($this->cache->getEntityKeyFormat(), $this->cache->getEntityClass($entity), $id);
            $query->useResultCache(true, $ttl, $key);
        }

        return $query->getOneOrNullResult();
    }

    /**
     * @param Query        $query
     * @param integer|null $ttl
     * @param string|null  $key
     *
     * @return array
     */
    public function query(Query $query, $ttl = null, $key = null)
    {
        if (is_null($key)) {
            $key = $this->getQueryCacheKey($query);
        }

        if ($ttl !== false) {
            $query->useResultCache(true, $ttl, $key);
        }

        $hasKey = $this->cache->has($key);

        // load via entitymanager
        $res = $query->getResult();

        // remember which queries contain these entities
        if (!empty($res) && ($hasKey === false) && ($ttl !== false)) {
            $this->cache->registerQueryForEntity($res[0], $key);

            foreach ($res as $entity) {
                $this->cache->registerQueryResult($entity, $key);
            }
        }

        return $res;
    }

    /**
     * @return EntityManagerInterface
     */
    protected function getEntityManager()
    {
        return $this->doctrine->getManager();
    }

    /**
     * @param string $entity
     *
     * @return EntityRepository
     */
    protected function getRepository($entity)
    {
        return $this->doctrine->getRepository($entity);
    }
}
