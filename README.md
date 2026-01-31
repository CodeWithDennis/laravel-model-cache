# Cache Pre-Warming

[![Tests](https://github.com/CodeWithDennis/cache-pre-warming/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/CodeWithDennis/cache-pre-warming/actions/workflows/tests.yml)
[![License](https://img.shields.io/github/license/CodeWithDennis/cache-pre-warming)](https://github.com/CodeWithDennis/cache-pre-warming/blob/master/LICENSE.md)
[![PHP Version](https://img.shields.io/packagist/php-v/codewithdennis/cache-pre-warming)](https://packagist.org/packages/codewithdennis/cache-pre-warming)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red)](https://laravel.com)

Pre-warm Eloquent queries so expensive or static data is served from cache, not the database. The standout feature is **warmup**: run a query once (e.g. in a scheduled command), and the result is cached **forever**—every request after that hits cache. No TTL, no cold cache. Plus normal TTL-based caching for everything else.

---

## Installation

```bash
composer require codewithdennis/cache-pre-warming
```

---

## Why warmup?

Heavy queries—dashboard stats, totals, reference data—either run on every request (expensive) or make the first request slow when the cache is empty. With normal TTL caching you still pay that cost whenever the cache expires or is cold.

**Warmup flips that.** Run the query once in a Laravel command (on a schedule or right after deploy). The result is stored with `Cache::rememberForever()`. From then on, every identical query in your app gets the result from cache. No expiry. And because the command fills the cache before users hit the app, **no one is punished for being the first visitor**—the cache is already warm. Clear the cache manually when you need to refresh the data.


---

## Overview

**Warmup** – Call `warmup()` before a query. The result is cached forever. Run that query once in a scheduled command (or at deploy); once the command has run, the cache is full, so the first user and every user after get a fast response. Use it for dashboards, aggregates, reference data, anything heavy or rarely-changing.

**Normal caching** – Without `warmup()`, queries are still cached automatically, but results expire after a configurable TTL (default 10 minutes). Same idea: first run hits the DB, next runs hit cache—until the TTL expires.

Cache keys are derived from the query, so different queries get different cache entries.

---

## Usage

Add the trait to any model:

```php
use CodeWithDennis\CachePreWarming\Traits\HasCache;

class User extends Model
{
    use HasCache;
}
```

From there you get two separate behaviours:

### Pre-warming (warmup)

Add `warmup()` before the query. Run that query once (e.g. in a scheduled command)—the result is cached forever. Every later identical query in your app gets the value from cache.

```php
$stats = [
    'total_revenue' => Order::query()->warmup()->sum('amount'),
    'total_users'   => User::query()->warmup()->count(),
];

$countries = Country::query()->warmup()->pluck('name', 'code');
```

Put these queries in a Laravel command and schedule it (hourly, daily, or after deploy). Once the command has run, the next user request will already hit cache—no cold cache for the first visitor.

```php
// app/Console/Commands/WarmCache.php
class WarmCache extends Command
{
    protected $signature = 'cache:warm';

    public function handle(): int
    {
        Order::query()->warmup()->sum('amount');
        User::query()->warmup()->count();

        return self::SUCCESS;
    }
}
```

```php
// routes/console.php
Schedule::command('cache:warm')->hourly();
```

### Caching (TTL-based)

Use the model as usual, without `warmup()`. The first run hits the database; identical queries later are served from cache until the TTL (default 600 seconds) expires.

```php
// 1st call: DB. 2nd call: cache
$users = User::where('active', true)->get();
```

---

## Customization

### Warmup

Call `warmup()` before any cached method. Works with `get`, `pluck`, `count`, `sum`, `avg`, `min`, `max`, `exists`, pagination, etc.

```php
Report::query()->warmup()->get();
Metric::query()->warmup()->sum('value');
```

### Cache TTL (normal mode)

Default TTL is **600 seconds** (10 minutes). Override per model:

**Method or property (inside your model):**

```php
public function cacheTtl(): int
{
    return 3600; // 1 hour
}

// or
protected int $cacheTtl = 300; // 5 minutes
```

### Cached methods

`get`, `first`, `find`, `findMany`, `pluck`, `value`, `sole`, `count`, `exists`, `doesntExist`, `sum`, `avg`, `average`, `min`, `max`, `paginate`, `simplePaginate`.

### How cache keys work

Keys are an MD5 hash of the query (raw SQL with bindings) plus a short suffix for the operation (`get`, `count`, `pluck:...`, etc.). Same query + same method = same key; different query or method = different key.

---

## Requirements

- PHP 8.4+
- Laravel 12.x

Uses your Laravel cache driver from `config/cache.php`. Models without the trait use the default builder (no caching).

---

## License

MIT. See [LICENSE.md](LICENSE.md) for details.
