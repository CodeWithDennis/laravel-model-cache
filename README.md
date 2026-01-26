# Cache Pre Warming

A Laravel package that provides manual cache pre-warming for Eloquent models.

## Features

- **Manual Cache Pre-Warming** - Manually warm up cache with `warmup()` methods (always caches forever)
- **Automatic Cache Invalidation** - Clears cache on model save/delete

## Installation

```bash
composer require codewithdennis/cache-pre-warming
```

## Why Pre-Warm Cache?

Pre-warming cache is beneficial for Laravel applications:

- **Dashboard Performance**: Pre-load dashboard statistics, recent activity, and analytics data so admin panels load instantly
- **Homepage Speed**: Cache featured content, latest posts, or popular items before users visit your homepage
- **Scheduled Tasks**: Use Laravel's scheduler to warm cache during off-peak hours, reducing database load during peak traffic
- **Queue Jobs**: Pre-warm expensive queries in background jobs instead of blocking user requests
- **API Performance**: Cache frequently accessed API resources to ensure consistent response times

### Example: Pre-Warm Dashboard Data

Create an Artisan command to pre-warm your dashboard data:

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Post;
use Illuminate\Console\Command;

class WarmDashboardCache extends Command
{
    protected $signature = 'cache:warm-dashboard';
    protected $description = 'Pre-warm cache for dashboard data';

    public function handle(): int
    {
        $this->info('Warming dashboard cache...');

        // Pre-warm recent users
        User::latest()->limit(50)->get()->warmup();

        // Pre-warm featured posts
        Post::where('featured', true)
            ->with(['author', 'category'])
            ->get()
            ->warmup();

        // Pre-warm admin user
        User::where('role', 'admin')->first()?->warmup();

        $this->info('Dashboard cache warmed successfully!');

        return Command::SUCCESS;
    }
}
```

Then schedule it in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('cache:warm-dashboard')->hourly();
}
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

### Manual Warmup

Call `warmup()` on model instances or collections to cache them forever:

```php
// Pre-warm frequently accessed models
User::whereIn('id', [1, 2, 3, 4, 5])->get()->warmup();

// Pre-warm featured content for homepage
Post::where('featured', true)
    ->where('published_at', '<=', now())
    ->orderBy('published_at', 'desc')
    ->limit(10)
    ->get()
    ->warmup();

// Pre-warm user with relationships
$user = User::with(['posts', 'comments'])->find(1);
$user->warmup();
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
