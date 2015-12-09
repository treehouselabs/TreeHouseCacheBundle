<?php

namespace TreeHouse\CacheBundle\DependencyInjection\Configuration;

class RedisDsn extends Dsn
{
    /**
     * @var int
     */
    protected $database;

    /**
     * @var string
     */
    protected $alias;

    /**
     * @return int|null
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @param string $dsn
     */
    protected function parseDsn($dsn)
    {
        // remove scheme
        $dsn = str_replace('redis://', '', $dsn);

        // parse password
        if (false !== $pos = strrpos($dsn, '@')) {
            $this->password = str_replace('\@', '@', substr($dsn, 0, $pos));
            $dsn = substr($dsn, $pos + 1);
        }

        // parse parameters
        $dsn = preg_replace_callback('/\?([^=]+)=[^&]+.*$/', [$this, 'parseParameters'], $dsn);

        // parse database
        if (preg_match('#^(.*)/(\d+)$#', $dsn, $matches)) {
            $this->database = (int) $matches[2];
            $dsn = $matches[1];
        }

        // parse socket/host[:port]
        if (preg_match('#^([^:]+)(:(\d+))?$#', $dsn, $matches)) {
            // parse host/ip or socket
            if (!empty($matches[1])) {
                if ('/' === $matches[1]{0}) {
                    $this->socket = $matches[1];
                } else {
                    $this->host = $matches[1];
                }
            }

            // parse port
            if (null === $this->socket) {
                $this->port = !empty($matches[3]) ? intval($matches[3]) : 6379;
            }
        }
    }

    /**
     * @param array $matches
     *
     * @return string
     */
    protected function parseParameters($matches)
    {
        parse_str(substr($matches[0], 1), $query);

        if (!empty($query)) {
            if (isset($query['weight'])) {
                $this->weight = (int) $query['weight'];
            }
            if (isset($query['alias'])) {
                $this->alias = $query['alias'];
            }
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    protected function getProtocol()
    {
        return 'redis';
    }
}
