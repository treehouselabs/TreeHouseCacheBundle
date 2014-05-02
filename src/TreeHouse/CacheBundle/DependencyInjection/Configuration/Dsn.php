<?php

namespace TreeHouse\CacheBundle\DependencyInjection\Configuration;

abstract class Dsn
{
    /**
     * @var string
     */
    protected $dsn;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var string
     */
    protected $socket;

    /**
     * @var int
     */
    protected $weight;

    /**
     * Constructor
     *
     * @param string $dsn
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($dsn)
    {
        $protocol = $this->getProtocol();

        if (0 !== strpos($dsn, $protocol . '://')) {
            throw new \InvalidArgumentException(
                sprintf('dsn should start with "%s://" protocol: %s', $protocol, $dsn));
        }

        $this->dsn = $dsn;
        $this->parseDsn($dsn);

        if (is_null($this->socket) && (is_null($this->getHost()) || is_null($this->getPort()))) {
            throw new \InvalidArgumentException(sprintf('Invalid dsn: %s', $dsn));
        }
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @return integer
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param string $dsn
     */
    abstract protected function parseDsn($dsn);

    /**
     * @return string
     */
    abstract protected function getProtocol();
}
