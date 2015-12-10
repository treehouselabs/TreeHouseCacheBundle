<?php

namespace TreeHouse\CacheBundle\Tests\DependencyInjection\Configuration;

use TreeHouse\CacheBundle\DependencyInjection\Configuration\MemcachedDsn;

class MemcachedDsnTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dsnValues
     *
     * @param string      $dsn
     * @param string      $host
     * @param int|null    $port
     * @param string|null $password
     * @param int|null    $weight
     */
    public function testDsn($dsn, $host, $port = null, $password = null, $weight = null)
    {
        $dsn = new MemcachedDsn($dsn);

        if (substr($host, -5) === '.sock') {
            $this->assertSame($host, $dsn->getSocket());
        } else {
            $this->assertSame($host, $dsn->getHost());
        }

        $this->assertSame($port, $dsn->getPort());
        $this->assertSame($password, $dsn->getPassword());
        $this->assertSame($weight, $dsn->getWeight());
    }

    /**
     * @return array
     */
    public function dsnValues()
    {
        return [
            # dsn                                                               host                                 port    password                            weight
            ['memcached://localhost',                                        'localhost',                            11211,  null,                               null],
            ['memcached://127.0.0.1',                                        '127.0.0.1',                            11211,  null,                               null],
            ['memcached:///memcached.sock',                                  '/memcached.sock',                      null,   null,                               null],
            ['memcached:///var/run/memcached/memcached-1.sock',              '/var/run/memcached/memcached-1.sock',  null,   null,                               null],
            ['memcached://localhost?weight=1',                               'localhost',                            11211,  null,                               1],
            ['memcached://127.0.0.1',                                        '127.0.0.1',                            11211,  null,                               null],
            ['memcached://localhost:112110',                                 'localhost',                            112110, null,                               null],
            ['memcached://127.0.0.1:112110?weight=2',                        '127.0.0.1',                            112110, null,                               2],
            ['memcached:///memcached.sock:112110?weight=3',                  '/memcached.sock',                      null,   null,                               3],
            ['memcached:///var/run/memcached/memcached-1.sock:112110',       '/var/run/memcached/memcached-1.sock',  null,   null,                               null],
            ['memcached://pw@localhost:112110?weight=5',                     'localhost',                            112110, 'pw',                               5],
            ['memcached://pw@127.0.0.1:112110',                              '127.0.0.1',                            112110, 'pw',                               null],
            ['memcached://pw@/memcached.sock:112110',                        '/memcached.sock',                      null,   'pw',                               null],
            ['memcached://pw@/var/run/memcached/memcached-1.sock:112110',    '/var/run/memcached/memcached-1.sock',  null,   'pw',                               null],
            ['memcached://p\@w@localhost:112110',                            'localhost',                            112110, 'p@w',                              null],
            ['memcached://mB(.z9},6o?zl>v!LM76A]lCg77,;.@localhost:112110',  'localhost',                            112110, 'mB(.z9},6o?zl>v!LM76A]lCg77,;.',   null],
            ['memcached://p\@w@127.0.0.1:112110',                            '127.0.0.1',                            112110, 'p@w',                              null],
            ['memcached://mB(.z9},6o?zl>v!LM76A]lCg77,;.@127.0.0.1:112110',  '127.0.0.1',                            112110, 'mB(.z9},6o?zl>v!LM76A]lCg77,;.',   null],
            ['memcached://p\@w@/memcached.sock?weight=6',                    '/memcached.sock',                      null,   'p@w',                              6],
            ['memcached://mB(.z9},6o?zl>v!LM76A]lCg77,;.@/memcached.sock',   '/memcached.sock',                      null,   'mB(.z9},6o?zl>v!LM76A]lCg77,;.',   null],
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
        new MemcachedDsn($dsn);
    }

    /**
     * @return array
     */
    public function invalidDsnValues()
    {
        return [
            ['localhost'],
            ['localhost/1'],
            ['pw@localhost:112110/10'],
            ['redis://localhost:11211'],
        ];
    }
}
