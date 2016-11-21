# MCP Cache

[![Build Status](https://travis-ci.org/quickenloans-mcp/mcp-cache.png)](https://travis-ci.org/quickenloans-mcp/mcp-cache)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/quickenloans-mcp/mcp-cache/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/quickenloans-mcp/mcp-cache/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/ql/mcp-cache/version)](https://packagist.org/packages/ql/mcp-cache)
[![License](https://poser.pugx.org/ql/mcp-cache/license)](https://packagist.org/packages/ql/mcp-cache)

Simple caching standard for Quicken Loans PHP projects.

We prefer a simpler cache protocol than the one provided by [PSR-6 Caching Interface](http://www.php-fig.org/psr/psr-6/).
This package has many similarities with the draft [PSR-16 Simple Cache](https://github.com/php-fig/fig-standards/blob/master/proposed/simplecache.md).
If this PSR is accepted, we will update this package to ensure compatibility.

## Installation

```
composer require ql/mcp-cache ~3.0
```

## Table of Contents

- [Usage](#usage)
    - [CachingTrait](#cachingtrait)
- [Implementations](#implementations)
    - [MemoryCache](#memorycache)
    - [PredisCache](#prediscache)
    - [APCCache](#apccache)
    - [MemcachedCache](#memcachedcache)
    - [MemcacheCache](#memcachecache)
- [Stampede Protection](#stampede-protection)


## Usage

The `CacheInterface` has only two methods - `get` and `set`.

### Get

```php
public function get($key);
```

Get takes any string as a key and does a lookup of the data.

##### Please Note:
- Missing data will return `null`. There is no difference between a cache hit of a `null` value and a cache miss.
- It is not necessary to serialize objects before storage. Serialization will be optimized by each cache implementation.

### Set

```php
public function set($key, $value, $ttl = 0);
```

Set data in the cache. A boolean will be returned to indicate whether the data was saved.

##### Please Note:
- Resources cannot be cached.
- TTL is the time in seconds the data should live until expired. A time to live of `0` will never expire the data.

### CachingTrait

The [CachingTrait](src/CachingTrait.php) is provided to make adding optional caching to your classes easy.

`QL\MCP\Cache\CachingTrait` adds the following private methods to your trait consumer:

```php
/**
 * @return CacheInterface|null
 */
private function cache();

/**
 * @param string $key
 * @return mixed|null
 */
private function getFromCache($key);

/**
 * @param string $key
 * @param mixed $value
 * @param mixed $ttl
 * @return null
 */
private function setToCache($key, $value, $ttl = 0);
```

If you want to access the cache directly, use `$this->cache()`. However, it is usually not necessary. The `get` and `set` methods will fail gracefully and return `null` if no cacher was set.

The following public methods are added as well:
```php
/**
 * @param CacheInterface $cache
 * @return null
 */
public function setCache(CacheInterface $cache);

/**
 * @param int $ttl
 * @return null
 */
public function setCacheTTL($ttl);
```

Here is an example of this in action:

```php
use QL\MCP\Cache\CachingTrait;

class MyRepo
{
    use CachingTrait;

    public function load()
    {
        if ($cached = $this->getFromCache('cachekey')) {
            return $cached;
        }

        // get data from service
        $data = $this->callService();

        $this->setToCache('cachekey', $data);
        return $data;
    }
}
```

Setup example for **Symfony DI**:
```
services:
    cache:
        class: 'QL\MCP\Cache\PredisCache'
        arguments: ['@predis']
    repo:
        class: 'MyRepo'
        calls:
            - ['setCache', ['@cache']]
            - ['setCacheTTL', [3600]]
```
Setup example for **PHP**:
```php
$cacher = new PredisCache($predis);

$repo = new MyRepo;
$repo->setCache($cacher);
$repo->setCacheTTL(3600);
```

#### Why use this?

- Reduce boilerplate
- Make caching easy

It allows you to enable or disable caching with minimal boilerplate. All of the caching actions within your class will fail gracefully if the cache was never set.

Please note that it is possible to set a *global* ttl for your class. If a ttl is provided at the time of setting data, it will be used. If not provided, the global ttl will be used. If the global ttl is never set, no ttl will be used.

## Implementations

- [MemoryCache](#memorycache)
- [PredisCache](#prediscache)
- [APCCache](#apccache)
- [MemcachedCache](#memcachedcache)
- [MemcacheCache](#memcachecache)

### MemoryCache

The `MemoryCache` is a very basic cache for caching data that only lives through the lifetime
of the request. This cache ignores `ttl`.

```php
use QL\MCP\Cache\MemoryCache;

$cache = new MemoryCache

// Store data
$cache->set('key', $data);

// Store data with expiration of 10 minutes  - note that ttl is ignored for this cacher
$cache->set('key', $data, 600);
```

### PredisCache

This cache will store data in the redis using [predis](https://github.com/nrk/predis).

An optional suffix may be provided to salt the cache keys. This can be used to invalidate the entire cache
between code pushes or other configuration changes.

Setting a key to `null` will delete the key rather than setting the value to `null`.
It is not possible to store a `null` value with the predis cacher.

```php
use Predis\Client;
use QL\MCP\Cache\PredisCache;

$predis = new Client;
$suffix = '6038aa7'; // optional
$cache = new PredisCache($predis, $suffix);

// Store data
$cache->set('key', $data);

// Store data with expiration of 10 minutes
$cache->set('key', $data, 600);
```

### APCCache

This cache will store items in the APC user cache space. Optionally, a maximum TTL may be set by calling the 
`APCCache::setMaximumTtl($ttl)` method.

```php
use QL\MCP\Cache\APCCache;
use QL\MCP\Common\Time\Clock;

$cache = new APCCache(new Clock());

// Optionally, set the maximum TTL to 10 minutes
$cache->setMaximumTtl(600);

// Store data
$cache->set('key', $data);

// Store data with an expiration of 10 minutes
$cache->set('key', $data, 600);

// Retrieve data
$data = $cache->get('key');
```

### MemcachedCache

This cache will store data in memcached using [pecl-memcached](https://github.com/php-memcached-dev/php-memcached).
It is also compatible with [AWS ElastiCache Cluster Client](https://github.com/awslabs/aws-elasticache-cluster-client-memcached-for-php),
a drop-in replacement for `pecl-memcache` with support for autodiscovery.

See [PHP.NET Memcached Book](http://php.net/manual/en/book.memcached.php)

### MemcacheCache

This cache will store data in memcached using [pecl-memcache](https://github.com/php-memcached-dev/php-memcache).

See [PHP.NET Memcache Book](http://php.net/manual/en/book.memcache.php)

## Stampede Protection

In the case of high load services, when the cache expires or is flushed many requests attempting to regenerate the
cache can cause a dog-piling effect on dependent ssystems, especially if the cost of regenerating the cached data is high.
This is typically a concern under heavy load, when cached data is shared across many requests.

Several methods exist for preventing this, for MCP Cache we implement **Probabilistic early expiration**.

With this approach, when the remaining TTL gets close to expiring, the application has a higher and higher random
chance of returning a **cache miss** from `get`. In this scenario, a very small portion of users will attempt to regenerate
the cache, rather than every request.

## Caching implementations with Stampede Protection

Note: by default stampede protection is **disabled**.

- [APCCache](#apccache)

### Code example

```php
use QL\MCP\Cache\APCCache;

$cache = new APCCache;
$cache->enableStampedeProtection();

// Customize beta and delta (not recommended).
$cache->setPrecomputeBeta(5);
$cache->setPrecomputeDelta(10);

// use cache as normal
$cache->set('test', 'value', 60);
```

### Example Cache Stampede Protection

Beta = `5`  
Delta = `10` % of TTL

Set a value with a ttl of 60 seconds and run 1000 tests.

TTL left   | percentile | early expires   | percent
---------- | ---------- | --------------- | -------
25s        | 60%        |   0 out of 1000 | 0%
20s        | 66%        |  10 out of 1000 | 1%
15s        | 75%        |  40 out of 1000 | 4%
6s         | 90%        | 300 out of 1000 | 30%
3s         | 95%        | 500 out of 1000 | 50%

References:
- [https://en.wikipedia.org/wiki/Cache_stampede#Probabilistic_early_expiration](https://en.wikipedia.org/wiki/Cache_stampede#Probabilistic_early_expiration)
- [http://www.vldb.org/pvldb/vol8/p886-vattani.pdf](http://www.vldb.org/pvldb/vol8/p886-vattani.pdf)
