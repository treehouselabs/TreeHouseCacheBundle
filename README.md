Cache bundle
============

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]

Symfony bundle for our [cache client][client].

[client]: https://github.com/treehouselabs/cache

## Installation

```sh
composer require treehouselabs/cache-bundle:^1.0
```


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


## Security

If you discover any security related issues, please email dev@treehouse.nl
instead of using the issue tracker.


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.


## Acknowledgements
Some concepts and/or implementations are borrowed from [SncRedisBundle](https://github.com/snc/SncRedisBundle)


## Credits

- [Peter Kruithof][link-author]
- [All Contributors][link-contributors]


[ico-version]: https://img.shields.io/packagist/v/treehouselabs/cache-bundle.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/treehouselabs/cache-bundle/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/treehouselabs/cache-bundle.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/treehouselabs/cache-bundle.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/treehouselabs/cache-bundle.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/treehouselabs/cache-bundle
[link-travis]: https://travis-ci.org/treehouselabs/cache-bundle
[link-scrutinizer]: https://scrutinizer-ci.com/g/treehouselabs/cache-bundle/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/treehouselabs/cache-bundle
[link-downloads]: https://packagist.org/packages/treehouselabs/cache-bundle
[link-author]: https://github.com/treehouselabs
[link-contributors]: ../../contributors
