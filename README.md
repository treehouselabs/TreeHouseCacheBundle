Cache bundle
============

Symfony2 bundle for our [cache client][client].

[client]: https://github.com/treehouselabs/cache

[![Build Status](https://travis-ci.org/treehouselabs/TreeHouseCacheBundle.svg)](https://travis-ci.org/treehouselabs/TreeHouseCacheBundle)

## Usage

This configuration:

```yaml
tree_house_cache:
  clients:
    default:
      type: phpredis
      serializer: json
      dsn: redis://localhost
      prefix: "cache:"
```

creates a `tree_house_cache.client.default` service, which resolves to a [CacheInterface][CI] instance.

[CI]: https://github.com/treehouselabs/cache/blob/master/src/TreeHouse/Cache/CacheInterface.php

## Acknowledgements
Some concepts and/or implementations are borrowed from [SncRedisBundle](https://github.com/snc/SncRedisBundle)
