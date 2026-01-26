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
use CodeWithDennis\CachePreWarming\Traits\HasCache;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasCache;
}
```

### Manual Warmup

Manually pre-warm cache by calling `warmup()` on models or collections. Unlike automatic caching which expires after a TTL, manually warmed cache persists forever until the model is saved or deleted. Use this to pre-load critical data in scheduled jobs or during application startup:

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

### Automatic Query Caching

All queries are automatically cached when executed. Results are stored with a TTL (Time To Live) of 300 seconds by default, meaning cached results expire after 5 minutes. This happens automatically—no additional code needed:

```php
// First call executes query and caches result
$users = User::where('active', true)->get();

// Second call returns cached result (no database query)
$users = User::where('active', true)->get();
```

**Customize TTL:**

Override the `cacheTtl()` method in your model to change the cache duration:

```php
class User extends Model
{
    use HasCache;

    public function cacheTtl(): int
    {
        return 600; // Cache for 10 minutes instead of 5
    }
}
```

Cache is automatically cleared when models are saved or deleted.

## License

MIT
