<?php

namespace TreeHouse\CacheBundle\Tests\DependencyInjection\Configuration;

use TreeHouse\CacheBundle\DependencyInjection\Configuration\Dsn;
use TreeHouse\CacheBundle\DependencyInjection\Configuration\RedisDsn;

class RedisDsnTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dsnValues
     *
     * @param string       $dsn
     * @param string       $host
     * @param integer|null $port
     * @param string|null  $database
     * @param string|null  $password
     * @param integer|null $weight
     * @param string|null  $alias
     */
    public function testDsn($dsn, $host, $port = null, $database = null, $password = null, $weight = null, $alias = null)
    {
        $dsn = new RedisDsn($dsn);

        if (substr($host, -5) === '.sock') {
            $this->assertSame($host, $dsn->getSocket());
        } else {
            $this->assertSame($host, $dsn->getHost());
        }

        $this->assertSame($port, $dsn->getPort());
        $this->assertSame($database, $dsn->getDatabase());
        $this->assertSame($password, $dsn->getPassword());
        $this->assertSame($weight, $dsn->getWeight());
        $this->assertSame($alias, $dsn->getAlias());
    }

    /**
     * @return array
     */
    public function dsnValues()
    {
        return [
            # dsn                                                           host                            port    database    password                            weight  alias
            ['redis://localhost',                                           'localhost',                    6379,   null,       null,                               null,   null],
            ['redis://127.0.0.1',                                           '127.0.0.1',                    6379,   null,       null,                               null,   null],
            ['redis:///redis.sock',                                         '/redis.sock',                  null,   null,       null,                               null,   null],
            ['redis:///var/run/redis/redis-1.sock',                         '/var/run/redis/redis-1.sock',  null,   null,       null,                               null,   null],
            ['redis://localhost/1?weight=1&alias=master',                   'localhost',                    6379,   1,          null,                               1,      'master'],
            ['redis://127.0.0.1/1',                                         '127.0.0.1',                    6379,   1,          null,                               null,   null],
            ['redis:///redis.sock/1',                                       '/redis.sock',                  null,   1,          null,                               null,   null],
            ['redis:///var/run/redis/redis-1.sock/1',                       '/var/run/redis/redis-1.sock',  null,   1,          null,                               null,   null],
            ['redis://localhost:63790',                                     'localhost',                    63790,  null,       null,                               null,   null],
            ['redis://127.0.0.1:63790?alias=master&weight=2',               '127.0.0.1',                    63790,  null,       null,                               2,      'master'],
            ['redis:///redis.sock:63790?weight=3',                          '/redis.sock',                  null,   null,       null,                               3,      null],
            ['redis:///var/run/redis/redis-1.sock:63790',                   '/var/run/redis/redis-1.sock',  null,   null,       null,                               null,   null],
            ['redis://localhost:63790/10?alias=master&weight=4',            'localhost',                    63790,  10,         null,                               4,      'master'],
            ['redis://127.0.0.1:63790/10',                                  '127.0.0.1',                    63790,  10,         null,                               null,   null],
            ['redis:///redis.sock:63790/10',                                '/redis.sock',                  null,   10,         null,                               null,   null],
            ['redis:///var/run/redis/redis-1.sock:63790/10',                '/var/run/redis/redis-1.sock',  null,   10,         null,                               null,   null],
            ['redis://pw@localhost:63790/10?weight=5&alias=master',         'localhost',                    63790,  10,         'pw',                               5,      'master'],
            ['redis://pw@127.0.0.1:63790/10',                               '127.0.0.1',                    63790,  10,         'pw',                               null,   null],
            ['redis://pw@/redis.sock:63790/10',                             '/redis.sock',                  null,   10,         'pw',                               null,   null],
            ['redis://pw@/var/run/redis/redis-1.sock:63790/10',             '/var/run/redis/redis-1.sock',  null,   10,         'pw',                               null,   null],
            ['redis://p\@w@localhost:63790/10',                             'localhost',                    63790,  10,         'p@w',                              null,   null],
            ['redis://mB(.z9},6o?zl>v!LM76A]lCg77,;.@localhost:63790/10',   'localhost',                    63790,  10,         'mB(.z9},6o?zl>v!LM76A]lCg77,;.',   null,   null],
            ['redis://p\@w@127.0.0.1:63790/10',                             '127.0.0.1',                    63790,  10,         'p@w',                              null,   null],
            ['redis://mB(.z9},6o?zl>v!LM76A]lCg77,;.@127.0.0.1:63790/10',   '127.0.0.1',                    63790,  10,         'mB(.z9},6o?zl>v!LM76A]lCg77,;.',   null,   null],
            ['redis://p\@w@/redis.sock/10?weight=6&alias=master',           '/redis.sock',                  null,   10,         'p@w',                              6,      'master'],
            ['redis://mB(.z9},6o?zl>v!LM76A]lCg77,;.@/redis.sock/10',       '/redis.sock',                  null,   10,         'mB(.z9},6o?zl>v!LM76A]lCg77,;.',   null,   null],
        ];
    }

    /**
     * @param string $dsn
     *
     * @dataProvider invalidDsnValues
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidDsn($dsn)
    {
        new RedisDsn($dsn);
    }

    /**
     * @return array
     */
    public function invalidDsnValues()
    {
        return [
            ['localhost'],
            ['localhost/1'],
            ['pw@localhost:63790/10'],
            ['memcached://localhost:6379'],
        ];
    }
}
