<?php

namespace TreeHouse\CacheBundle\DependencyInjection\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('treehouse_cache');

        $this->addClientsSection($rootNode);
        $this->addDoctrineSection($rootNode);
        $this->addSessionSection($rootNode);

        return $treeBuilder;
    }

    /**
     * Adds the treehouse_cache.clients configuration.
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addClientsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('client')
            ->children()
                ->arrayNode('clients')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name', false)
                    ->prototype('array')
                        ->children()
                            ->enumNode('type')
                                ->isRequired()
                                ->values(['phpredis', 'memcached', 'apc', 'apcu', 'array', 'file'])
                                ->info('The type of cache')
                            ->end()

                            ->scalarNode('dsn')
                                ->info(
                                    'DSN of the cache, prefix this with the protocol, ' .
                                    'ie: redis:///var/run/redis.sock or memcached://localhost:11211'
                                )
                            ->end()

                            ->scalarNode('directory')
                                ->info('Cache directory, applies to the "file" cache type')
                            ->end()

                            ->arrayNode('connection')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->booleanNode('persistent')
                                        ->defaultFalse()
                                        ->info('Whether to use a persistent connection. Only available for Redis cache')
                                    ->end()
                                    ->scalarNode('timeout')
                                        ->defaultValue(5)
                                        ->info('Timeout to use when connecting')
                                    ->end()
                                ->end()
                            ->end()

                            ->scalarNode('prefix')
                                ->defaultNull()
                                ->info('Prefix to use for the cached keys')
                            ->end()

                            ->enumNode('serializer')
                                ->defaultValue('auto')
                                ->values([
                                    'auto',
                                    'igbinary',
                                    'json',
                                    'php',
                                ])
                            ->end()

                            ->scalarNode('serializer_class')
                            ->end()

                            ->booleanNode('in_memory')
                                ->defaultTrue()
                                ->info('Whether to wrap the cache in an in-memory caching decorator')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end()
        ;
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function addDoctrineSection(ArrayNodeDefinition $rootNode)
    {
        $doctrineNode = $rootNode->children()->arrayNode('doctrine')->canBeUnset();

        $doctrineNode
            ->children()
                ->arrayNode('cached_entity_manager')
                    ->children()
                        ->scalarNode('client')
                            ->info('The cache client you want to use for the ORM cache')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        foreach (['metadata_cache', 'result_cache', 'query_cache'] as $type) {
            $doctrineNode
                ->children()
                    ->arrayNode($type)
                        ->canBeUnset()
                        ->children()
                            ->scalarNode('client')->isRequired()->end()
                            ->scalarNode('namespace')->defaultNull()->end()
                        ->end()
                        ->fixXmlConfig('entity_manager')
                        ->children()
                            ->arrayNode('entity_managers')
                                ->defaultValue(['default'])
                                ->beforeNormalization()->ifString()->then(function ($v) { return (array) $v; })->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ;
        }
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function addSessionSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('session')
                    ->canBeUnset()
                    ->children()
                        ->scalarNode('client')->isRequired()->end()
                        ->scalarNode('prefix')->defaultValue('session')->end()
                        ->scalarNode('ttl')->defaultValue(86400)->end()
                        ->scalarNode('use_as_default')->defaultValue('true')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
