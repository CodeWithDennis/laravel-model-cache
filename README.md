# Cache Pre Warming

A Laravel package that provides automatic query caching and manual cache pre-warming for Eloquent models.

## Installation

```bash
composer require codewithdennis/cache-pre-warming
```

## Why Pre-Warm Cache?

Pre-warming cache improves performance by loading frequently accessed data before users request it. This provides several benefits:

- **Faster Response Times**: Data is already in cache when users request it, eliminating database queries
- **Reduced Database Load**: Move expensive queries to background jobs or scheduled tasks instead of blocking user requests
- **Predictable Performance**: Pre-load critical data during off-peak hours for consistent response times during peak traffic
- **Better User Experience**: Homepage content, dashboard statistics, and featured items load instantly

Use pre-warming for dashboard statistics, featured content, or any data that needs to load instantly.

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

Queries are automatically cached with a TTL (default: 300 seconds):

```php
// First call executes query and caches result
$users = User::where('active', true)->get();

// Second call returns cached result (no database query)
$users = User::where('active', true)->get();
```

Automatic caching works great for most queries, but sometimes you want more control. For data that should be cached forever or pre-loaded before users request it, use manual warmup:

### Manual Warmup

Pre-warm specific models or collections:

```php
Post::query()
    ->where('published', true)
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
