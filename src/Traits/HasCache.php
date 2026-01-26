<?php

declare(strict_types=1);

namespace CodeWithDennis\CachePreWarming\Traits;

use CodeWithDennis\CachePreWarming\CachedBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @mixin Model
 */
trait HasCache
{
    protected static bool $enableAutomaticCaching = true;

    public static function isAutomaticCachingEnabled(): bool
    {
        return static::$enableAutomaticCaching;
    }

    protected static function bootHasCache(): void
    {
        static $macroRegistered = false;

        if (! $macroRegistered) {
            $macroRegistered = true;

            EloquentCollection::macro('warmup', function (): self {
                /** @var EloquentCollection<int, Model> $this */
                $this->each(static fn (Model $model): Model => $model->warmup());

                return $this;
            });
        }

        static::saved(static fn (Model $model): bool => Cache::forget(static::cacheKey($model->getKey())));
        static::deleted(static fn (Model $model): bool => Cache::forget(static::cacheKey($model->getKey())));
    }

    protected static function cacheKey(int|string $id): string
    {
        return 'prewarm:'.static::class.':id:'.$id;
    }

    public function cacheTtl(): int
    {
        return 300;
    }

    protected static function queryCacheKey(Builder $query): string
    {
        $model = $query->getModel();
        $sql = $query->getQuery()->toRawSql();

        return 'cache:'.$model::class.':q:'.md5($model::class.'|'.$sql);
    }

    public function newEloquentBuilder($query)
    {
        return static::$enableAutomaticCaching ? new CachedBuilder($query) : parent::newEloquentBuilder($query);
    }

    public function warmup(): static
    {
        Cache::forever(static::cacheKey($this->getKey()), $this);

        return $this;
    }
}
