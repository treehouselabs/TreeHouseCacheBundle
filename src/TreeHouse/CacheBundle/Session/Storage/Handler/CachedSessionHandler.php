<?php

namespace TreeHouse\CacheBundle\Session\Storage\Handler;

use TreeHouse\Cache\CacheInterface;

class CachedSessionHandler implements \SessionHandlerInterface
{
    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var int
     */
    protected $ttl;

    /**
     * Key prefix for shared environments.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Constructor.
     *
     * List of available options:
     *  * prefix:     The prefix to use for the memcached keys in order to avoid collision
     *  * expiretime: The time to live in seconds
     *
     * @param CacheInterface $cache
     * @param array          $options An associative array of Memcached options
     *
     * @throws \InvalidArgumentException When unsupported options are passed
     */
    public function __construct(CacheInterface $cache, array $options = [])
    {
        if ($diff = array_diff(array_keys($options), ['prefix', 'expiretime'])) {
            throw new \InvalidArgumentException(sprintf(
                'The following options are not supported "%s"', implode(', ', $diff)
            ));
        }

        $this->cache = $cache;
        $this->ttl = isset($options['expiretime']) ? (int) $options['expiretime'] : 86400;
        $this->prefix = isset($options['prefix']) ? $options['prefix'] : 'sf2s';
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        return $this->cache->get($this->prefix . $sessionId) ?: '';
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data)
    {
        return $this->cache->set($this->prefix . $sessionId, $data, $this->ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        return $this->cache->remove($this->prefix . $sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime)
    {
        // not required here because redis will auto expire the records anyhow.
        return true;
    }
}
