# Cache Pre Warming

A Laravel package that provides automatic query caching and manual cache pre-warming for Eloquent models.

## Installation

```bash
composer require codewithdennis/cache-pre-warming
```

## Why Pre-Warm Cache?

Pre-warming cache improves performance by loading frequently accessed data before users request it. Use it for dashboard statistics, featured content, or any data that needs to load instantly.

## Usage

Add the trait to your models:

```php
use CodeWithDennis\CachePreWarming\Traits\HasCachePreWarming;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasCachePreWarming;
}
```

### Automatic Query Caching

Queries are automatically cached:

```php
// First call executes query and caches result
$users = User::where('active', true)->get();

// Second call returns cached result (no database query)
$users = User::where('active', true)->get();
```

### Manual Warmup

Pre-warm specific models or collections:

```php
Post::where('published', true)
    ->where('published_at', '<=', now())
    ->with(['author', 'category', 'tags'])
    ->orderBy('published_at', 'desc')
    ->limit(20)
    ->get()
    ->warmup();
```

Cache is automatically cleared when models are saved or deleted.

## License

MIT
