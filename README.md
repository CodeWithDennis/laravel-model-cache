# Cache Pre-Warming

[![Tests](https://github.com/CodeWithDennis/cache-pre-warming/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/CodeWithDennis/cache-pre-warming/actions/workflows/tests.yml)
[![License](https://img.shields.io/github/license/CodeWithDennis/cache-pre-warming)](https://github.com/CodeWithDennis/cache-pre-warming/blob/master/LICENSE.md)
[![PHP Version](https://img.shields.io/packagist/php-v/codewithdennis/cache-pre-warming)](https://packagist.org/packages/codewithdennis/cache-pre-warming)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red)](https://laravel.com)

Automatic query caching for Laravel Eloquent models. Use the `HasCache` trait on a model and its read queries (`get`, `first`, `find`, `pluck`, `count`, `exists`, aggregates, pagination, etc.) are cached—the first run hits the database, subsequent identical queries are served from cache until the TTL expires.

## Installation

```bash
composer require codewithdennis/cache-pre-warming
```

## What it does

- **Automatic caching** – No extra code. Queries on models using `HasCache` are cached by default.
- **Query-keyed** – Cache keys are derived from the query (SQL + bindings), so different queries get different cache entries.
- **TTL-based** – Cached results expire after a configurable time (default 600 seconds). Uses your Laravel cache driver (file, Redis, Memcached, etc.).

Cached methods include: `get`, `first`, `find`, `findMany`, `pluck`, `value`, `sole`, `count`, `exists`, `doesntExist`, `sum`, `avg`, `average`, `min`, `max`, `paginate`, `simplePaginate`.

### How cache keys are generated

Each cache key is an MD5 hash of:

1. **The query** – After applying scopes, the builder’s raw SQL is taken (with bindings replaced). So `User::where('active', true)->get()` and `User::where('active', false)->get()` get different keys.
2. **A method suffix** – The same query can be used for different operations (`get()` vs `count()` vs `pluck('name')`). A short suffix is appended so each operation has its own cache entry and you don’t mix collections, integers, and other result shapes.

So identical queries for the same method share one cache entry; the same query for different methods (e.g. `get()` and `count()`) do not.

## Usage

Add the trait to any Eloquent model:

```php
use CodeWithDennis\CachePreWarming\Traits\HasCache;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasCache;
}
```

Then use the model as usual. The first execution of a query runs against the database and stores the result; repeated identical queries return from cache until the TTL expires.

```php
// First call: runs query, caches result
$users = User::where('active', true)->get();

// Second call: returns cached result (no database query)
$users = User::where('active', true)->get();
```

## Customization

### Cache TTL

Default TTL is **600 seconds** (10 minutes). Override per model in either of these ways:

**1. Override the method**

```php
class User extends Model
{
    use HasCache;

    public function cacheTtl(): int
    {
        return 3600; // 1 hour
    }
}
```

**2. Set the property**

```php
class Post extends Model
{
    use HasCache;

    protected int $cacheTtl = 300; // 5 minutes
}
```

Models without the trait use the default builder (no caching). Uses your Laravel cache driver from `config/cache.php`.

## Requirements

- PHP 8.4+
- Laravel 12.x

## License

MIT. See [LICENSE.md](LICENSE.md) for details.
