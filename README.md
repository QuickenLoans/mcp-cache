# MCP Cache

Caching standard for mcp services

## Usage

The `CacheInterface` has only two methods - `get` and `set`.

### Get

```php
public function get($key);
```

Get takes any string as a key and does a lookup of the data.

##### Please Note:
- Missing data will return null. There is no difference between a cache hit of a `null` value and a cache miss.
- It is not necessary to serialize objects before storage. Serialization will be optimized by each cache implementation.

### Set

```php
public function set($key, $value, $ttl = 0);
```

Set data in the cache. A boolean will be returned to indicate whether the data was saved.

##### Please Note:
- Resources cannot be cached.
- TTL is the time in seconds the data should live until expired. A time to live of `0` will never expire the data.

### Convenience

The [CachingTrait](src/CachingTrait.php) is provided to make adding optional caching to your classes easy.

`MCP\Cache\CachingTrait` adds the following private methods to your trait consumer:

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
use MCP\Cache\CachingTrait;

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

Setup example: Symfony DI:
```
cache:
    class: 'MCP\Cache\PredisCache'
    arguments: [@predis]
repo:
    class: 'MyRepo'
    calls:
        - ['setCache', [@cache]]
        - ['setCacheTTL', [3600]]
```
Setup example: PHP:
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
- [SkeletorSessionCache](#skeletorsessioncache)
- [PredisCache](#prediscache)

### MemoryCache

The `MemoryCache` is a very basic cache for caching data that only lives through the lifetime
of the request. This cache ignores `ttl`.

```php
use MCP\Cache\MemoryCache;

$cache = new MemoryCache

// Store data
$cache->set('key', $data);

// Store data with expiration of 10 minutes  - note that ttl is ignored for this cacher
$cache->set('key', $data, 600);
```

### SkeletorSessionCache

This cache will store data in the skeletor session.

An optional suffix may be provided to salt the cache keys. This can be used to invalidate the entire cache
between code pushes or other configuration changes.

```php
use MCP\Cache\SkeletorSessionCache;
use MCP\DataType\Time\Clock;

$clock = new Clock('now', 'UTC');
$suffix = '6038aa7'; // optional
$cache = new SkeletorSessionCache($session, $clock, $suffix);

// Store data
$cache->set('key', $data);

// Store data with expiration of 10 minutes
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
use MCP\Cache\PredisCache;

$predis = new Client;
$suffix = '6038aa7'; // optional
$cache = new PredisCache($predis, $suffix);

// Store data
$cache->set('key', $data);

// Store data with expiration of 10 minutes
$cache->set('key', $data, 600);
```

### Building

A PSR-4 compatible autoloader is required to use this library. Composer is recommended.

### Installing

Install development dependencies

    bin/install

Wipe compiled files:

    bin/clean

Run tests:

    vendor/bin/phpunit
