<?php

namespace TreeHouse\CacheBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use TreeHouse\Cache\Cache;
use TreeHouse\Cache\Decorator\InMemoryCache;
use TreeHouse\CacheBundle\DependencyInjection\Configuration\Configuration;
use TreeHouse\CacheBundle\DependencyInjection\Configuration\MemcachedDsn;
use TreeHouse\CacheBundle\DependencyInjection\Configuration\RedisDsn;

class TreeHouseCacheExtension extends Extension
{
    /**
     * @inheritdoc
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        // load clients
        foreach ($config['clients'] as $name => $clientConfig) {
            $id = sprintf('tree_house_cache.client.%s', $name);
            $this->loadCache($id, $clientConfig, $container);
        }

        // load doctrine
        $this->loadDoctrineConfiguration($config, $container);

        // load session
        $this->loadSessionConfiguration($config, $container);
    }

    /**
     * @param string           $id
     * @param array            $clientConfig
     * @param ContainerBuilder $container
     *
     * @throws \LogicException
     */
    protected function loadCache($id, array $clientConfig, ContainerBuilder $container)
    {
        $driver = $this->getDriver($id, $clientConfig, $container);
        $serializer = $this->getSerializer($id, $clientConfig, $container);

        $container->setDefinition(sprintf('%s.driver', $id), $driver);
        $container->setDefinition(sprintf('%s.serializer', $id), $serializer);

        $client = new Definition(Cache::class);
        $client->addArgument(new Reference(sprintf('%s.driver', $id)));
        $client->addArgument(new Reference(sprintf('%s.serializer', $id)));

        if ($clientConfig['in_memory']) {
            $mem = new Definition(InMemoryCache::class);
            $mem->addArgument($client);

            $container->setDefinition($id, $mem);
        } else {
            $container->setDefinition($id, $client);
        }
    }

    /**
     * @param string           $id
     * @param array            $clientConfig
     * @param ContainerBuilder $container
     *
     * @throws \LogicException
     *
     * @return Definition
     */
    protected function getDriver($id, array $clientConfig, ContainerBuilder $container)
    {
        switch ($clientConfig['type']) {
            case 'array':
                $driver = $this->loadArrayDriver($container);
                break;
            case 'apc':
                $driver = $this->loadApcDriver($id, $clientConfig, $container);
                break;
            case 'phpredis':
                $driver = $this->loadPhpredisDriver($id, $clientConfig, $container);
                break;
            case 'memcached':
                $driver = $this->loadMemcachedDriver($id, $clientConfig, $container);
                break;
            default:
                throw new \LogicException(sprintf('Unsupported client type: %s', $clientConfig['type']));
        }

        return $driver;
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return Definition
     */
    protected function loadArrayDriver(ContainerBuilder $container)
    {
        $driver = new Definition($container->getParameter('tree_house_cache.array_driver.class'));

        return $driver;
    }

    /**
     * Loads a APC config using phpredis.
     *
     * @param string           $id
     * @param array            $config    A config configuration
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \LogicException When Memcached extension is not loaded
     *
     * @return Definition
     */
    protected function loadApcDriver($id, array $config, ContainerBuilder $container)
    {
        // Check if the APC extension is loaded
        if (!extension_loaded('apcu')) {
            throw new \LogicException('apcu extension is not loaded');
        }

        $driver = new Definition($container->getParameter('tree_house_cache.apc_driver.class'));

        return $driver;
    }

    /**
     * Loads a redis config using phpredis.
     *
     * @param string           $id
     * @param array            $config    A config configuration
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \LogicException When Redis extension is not loaded
     *
     * @return Definition
     */
    protected function loadPhpredisDriver($id, array $config, ContainerBuilder $container)
    {
        // Check if the Redis extension is loaded
        if (!extension_loaded('redis')) {
            throw new \LogicException('Redis extension is not loaded');
        }

        $dsn = new RedisDsn($config['dsn']);

        $connectMethod = $config['connection']['persistent'] ? 'pconnect' : 'connect';
        $connectParameters = [];
        if (null !== $dsn->getSocket()) {
            $connectParameters[] = $dsn->getSocket();
            $connectParameters[] = null;
        } else {
            $connectParameters[] = $dsn->getHost();
            $connectParameters[] = $dsn->getPort();
        }

        $connectParameters[] = $config['connection']['timeout'];

        if ($config['connection']['persistent']) {
            $connectParameters[] = $id;
        }

        $client = new Definition($container->getParameter('tree_house_cache.phpredis_client.class'));
        $client->addMethodCall($connectMethod, $connectParameters);

        if ($config['prefix']) {
            $client->addMethodCall('setOption', [\Redis::OPT_PREFIX, $config['prefix']]);
        }
        if (null !== $dsn->getPassword()) {
            $client->addMethodCall('auth', [$dsn->getPassword()]);
        }
        if (null !== $dsn->getDatabase()) {
            $client->addMethodCall('select', [$dsn->getDatabase()]);
        }

        $container->setDefinition(sprintf('%s.cache', $id), $client);

        $driver = new Definition($container->getParameter('tree_house_cache.phpredis_driver.class'));
        $driver->addArgument($client);

        return $driver;
    }

    /**
     * Loads a redis config using phpredis.
     *
     * @param string           $id
     * @param array            $config    A config configuration
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \LogicException When Memcached extension is not loaded
     *
     * @return Definition
     */
    protected function loadMemcachedDriver($id, array $config, ContainerBuilder $container)
    {
        // Check if the Memcached extension is loaded
        if (!extension_loaded('memcached')) {
            throw new \LogicException('Memcached extension is not loaded');
        }

        $dsn = new MemcachedDsn($config['dsn']);

        $client = new Definition($container->getParameter('tree_house_cache.memcached_client.class'));
        $client->addArgument($id);

        $client->addMethodCall(
            'addServer',
            [$dsn->getSocket() ?: $dsn->getHost(), $dsn->getPort(), $dsn->getWeight()]
        );

        if ($config['connection']['timeout']) {
            $client->addMethodCall(
                'setOption',
                [\Memcached::OPT_CONNECT_TIMEOUT, $config['connection']['timeout']]
            );
        }

        if ($config['prefix']) {
            $client->addMethodCall(
                'setOption',
                [\Memcached::OPT_PREFIX_KEY, $config['prefix']]
            );
        }

        $container->setDefinition(sprintf('%s.cache', $id), $client);

        $driver = new Definition($container->getParameter('tree_house_cache.memcached_driver.class'));
        $driver->addArgument($client);

        return $driver;
    }

    /**
     * @param string           $id
     * @param array            $config
     * @param ContainerBuilder $container
     *
     * @throws \InvalidArgumentException
     *
     * @return Definition
     */
    protected function getSerializer($id, array $config, ContainerBuilder $container)
    {
        if (!isset($config['serializer_class'])) {
            $config['serializer_class'] = $this->getSerializerClass($config['serializer'], $container);
        }

        if (!class_exists($config['serializer_class'])) {
            throw new \InvalidArgumentException(
                sprintf('Serializer class %s does not exist', $config['serializer_class'])
            );
        }

        $serializer = new Definition($config['serializer_class']);

        return $serializer;
    }

    /**
     * @param string           $serializer
     * @param ContainerBuilder $container
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    protected function getSerializerClass($serializer, ContainerBuilder $container)
    {
        switch ($serializer) {
            case 'auto':
                // determine the type and let it fall through
                $serializer = $this->getAutoDeterminedSerializer();

            case 'json':
            case 'igbinary':
            case 'php':
                return $container->getParameter(sprintf('tree_house_cache.serializer.%s.class', $serializer));
            default:
                throw new \InvalidArgumentException(sprintf('Invalid serialize strategy: %s', $serializer));
        }
    }

    /**
     * @return int
     */
    protected function getAutoDeterminedSerializer()
    {
        if (function_exists('igbinary_serialize')) {
            return 'igbinary';
        }

        if (function_exists('json_encode')) {
            return 'json';
        }

        return 'php';
    }

    /**
     * Loads the Doctrine configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDoctrineConfiguration(array $config, ContainerBuilder $container)
    {
        if (!isset($config['doctrine'])) {
            return;
        }

        if (isset($config['doctrine']['cached_entity_manager'])) {
            $client = sprintf('tree_house_cache.client.%s', $config['doctrine']['cached_entity_manager']['client']);
            $entityCache = $container->getDefinition('tree_house_cache.orm.entity_cache');
            $entityCache->replaceArgument(0, new Reference($client));
        }

        foreach (['metadata_cache', 'result_cache', 'query_cache'] as $type) {
            if (!isset($config['doctrine'][$type])) {
                continue;
            }

            $cache = $config['doctrine'][$type];
            foreach ($cache['entity_managers'] as $em) {
                $definition = $this->getDoctrineCacheDefinition($container, $cache);
                $container->setDefinition(sprintf('doctrine.orm.%s_%s', $em, $type), $definition);
            }
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param array            $cache
     *
     * @return Definition
     */
    protected function getDoctrineCacheDefinition(ContainerBuilder $container, array $cache)
    {
        $client = new Reference(sprintf('tree_house_cache.client.%s', $cache['client']));

        $def = new Definition($container->getParameter('tree_house_cache.doctrine.cache.class'));
        $def->addArgument($client);

        if ($cache['namespace']) {
            $def->addMethodCall('setNamespace', [$cache['namespace']]);
        }

        return $def;
    }

    /**
     * Loads the session configuration.
     *
     * @param array            $config    A configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadSessionConfiguration(array $config, ContainerBuilder $container)
    {
        if (!isset($config['session'])) {
            return;
        }

        $container->setParameter('tree_house_cache.session.client', $config['session']['client']);
        $container->setParameter('tree_house_cache.session.prefix', $config['session']['prefix']);

        $client = sprintf('tree_house_cache.client.%s', $config['session']['client']);

        $definition = new Definition($container->getParameter('tree_house_cache.session.handler.class'));
        $definition->addArgument(new Reference($client));

        if (isset($config['session']['ttl'])) {
            $definition->addArgument(['expiretime' => $config['session']['ttl']]);
        }

        $handlerId = 'tree_house_cache.session.handler';

        $container->setDefinition($handlerId, $definition);
        $container->setAlias('tree_house_cache.session.client', $client);

        if ($config['session']['use_as_default']) {
            $container->setAlias('session.handler', $handlerId);
        }
    }
}
