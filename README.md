# Cache Pre Warming

A Laravel package that provides manual cache pre-warming for Eloquent models.

## Features

- **Manual Cache Pre-Warming** - Manually warm up cache with `warmup()` methods (always caches forever)
- **Automatic Cache Invalidation** - Clears cache on model save/delete

## Installation

```bash
composer require codewithdennis/cache-pre-warming
```

## Usage

### Basic Usage

Use `HasCachePreWarming` to add manual warmup methods to your models:

```php
use CodeWithDennis\CachePreWarming\Traits\HasCachePreWarming;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasCachePreWarming;
}
```

**Features:**
- Manual warmup methods that cache forever
- Cache is cleared when model is saved or deleted

### Manual Warmup Methods

**Model Instance:**
```php
// Cache a single model (forever)
$user = User::find(1);
$user->warmup();
```

**Collection Macro:**
```php
// Warm up a collection (caches individual models forever)
// Note: warmup() must be called after the query has been executed
$users = User::where('active', true)->get();
$users->warmup();

// Or with all()
$users = User::all();
$users->warmup();
```

## Cache Keys

Cache keys are automatically generated based on:
- Model class name
- Model ID

### Custom Cache Keys

Override the `cacheKey()` or `queryCacheKey()` methods if needed:

```php
protected static function cacheKey(int|string $id): string
{
    return 'custom:'.static::class.':id:'.(string) $id;
}
```

## License

MIT
