# Change Log
All notable changes to this project will be documented in this file. See [keepachangelog.com](http://keepachangelog.com) for reference.

This package follows [semver](http://semver.org/) versioning.

## [3.0.0] - 2016-11-21

### Added
- Added `CacheInterface::VERSION` to get the current version of the Cache package.
- **[BC WARNING]** Append cache version to cache keys automatically.
    - When the cache package is updated, old data will be automatically ignored
      (Ensure your caches are always in [LRU mode](https://en.wikipedia.org/wiki/Cache_algorithms#LRU)!).
      This will make it easier for consumers to handle cached data that is not backwards compatible.

### Changed
- Changed namespace from `MCP\Cache\*` to `QL\MCP\Cache\*`.
- PHP `5.6+` or `7.0+` is now required.
- Switched dependency from `ql/mcp-core` to `ql/mcp-common`.
- Fixed `APCCache::clear()`.

### Removed

- Removed `SkeletorSessionCache`.

## [2.5.1] - 2015-10-13

### Changed
- Cache keys can now be salted with a suffix for **MemcachedCache**.
- A maximum TTL can now be set for **MemcachedCache**.
- Error responses from **MemcachedCache** can now be optionally logged.
    - `pecl-memcached` returns error codes when failures occur, which **MemcachedCache** silently consumes. Attach
      a logger to log these messages as warnings.

## [2.5.0] - 2015-10-08

### Added
- Add **MemcacheCache** for caching using `pecl-memcache`.
- Add **MemcachedCache** for caching using `pecl-memcached`.

## [2.4.1] - 2015-09-21

### Changed
- **APCCache** now uses `apcu_*` methods instead of `apc_*`.

## [2.4.0] - 2015-09-01

### Added
- Add optional cache stampede protection to **APCCache**. This is disabled by default.

### Changed
- Cache keys can now be salted with a suffix for **APCCache**.
- Attempts to cache a resource will now throw `MCP\Cache\Exception` instead of `InvalidArgumentException`.

## [2.3.1] - 2015-05-01

### Changed
- A maximum TTL can now be set for **APCCache**.

## [2.3.0] - 2015-05-01

### Added
- Add **APCCache** for caching to APC (APCU recommended).

## [2.2.0] - 2014-11-12

### Added
- Add `MCP\Cache\CachingTrait` for enabling quick cache integration on repositories, services, or other code.
