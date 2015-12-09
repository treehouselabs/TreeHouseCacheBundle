<?php

namespace TreeHouse\CacheBundle\DependencyInjection\Configuration;

class MemcachedDsn extends Dsn
{
    /**
     * @param string $dsn
     */
    protected function parseDsn($dsn)
    {
        // remove scheme
        $dsn = str_replace('memcached://', '', $dsn);

        // parse password
        if (false !== $pos = strrpos($dsn, '@')) {
            $this->password = str_replace('\@', '@', substr($dsn, 0, $pos));
            $dsn = substr($dsn, $pos + 1);
        }

        // parse parameters
        $dsn = preg_replace_callback('/\?([^=]+)=[^&]+.*$/', [$this, 'parseParameters'], $dsn);

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
                $this->port = !empty($matches[3]) ? intval($matches[3]) : 11211;
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
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    protected function getProtocol()
    {
        return 'memcached';
    }
}
