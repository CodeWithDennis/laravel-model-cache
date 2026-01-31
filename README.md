# Cache Pre-Warming

[![Tests](https://github.com/CodeWithDennis/cache-pre-warming/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/CodeWithDennis/cache-pre-warming/actions/workflows/tests.yml)
[![License](https://img.shields.io/github/license/CodeWithDennis/cache-pre-warming)](https://github.com/CodeWithDennis/cache-pre-warming/blob/master/LICENSE.md)
[![PHP Version](https://img.shields.io/packagist/php-v/codewithdennis/cache-pre-warming)](https://packagist.org/packages/codewithdennis/cache-pre-warming)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red)](https://laravel.com)

Automatic query caching for Laravel Eloquent. Add the `HasCache` trait to a model—queries are cached automatically with a TTL. Use `warmup()` when you want a result cached **forever** (e.g. heavy dashboard stats or reference data).

---

## Installation

```bash
composer require codewithdennis/cache-pre-warming
```

---

## Overview

| Mode | When to use | Behaviour |
|------|-------------|-----------|
| **Warmup** | Heavy or rarely-changing data (dashboards, aggregates, reference data) | Cached **forever**. First run hits the DB; later identical queries come from cache until you clear it. |
| **Normal** | Everything else | Cached with a **TTL** (default 10 min). First run hits the DB; later identical queries come from cache until TTL expires. |

Both modes are **query-keyed**: cache keys come from the query (SQL + bindings), so different queries get different cache entries.

---

## Usage

### 1. Add the trait

```php
use CodeWithDennis\CachePreWarming\Traits\HasCache;

class User extends Model
{
    use HasCache;
}
```

### 2. Warmup: cache heavy or static data forever

Use `warmup()` for expensive or rarely-changing queries—dashboard stats, site-wide totals, reference data. The result is stored with `Cache::rememberForever()`.

**Dashboard / reference data**

```php
$stats = [
    'total_revenue' => Order::query()->warmup()->sum('amount'),
    'total_users'   => User::query()->warmup()->count(),
];
$countries = Country::query()->warmup()->pluck('name', 'code');
```

**Where to warmup** – Run warmup in a Laravel command and schedule it (e.g. hourly or after deploy). First request then hits cache.

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

### 3. Normal caching: TTL-based

Use the model as usual. The first run hits the database; identical queries later are served from cache until the TTL (default 600 seconds) expires.

```php
$users = User::where('active', true)->get(); // 1st: DB, 2nd: cache
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
public function cacheTtl(): int { return 3600; }  // or: protected int $cacheTtl = 300;
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
