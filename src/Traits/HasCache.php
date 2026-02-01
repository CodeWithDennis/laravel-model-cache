<?php

declare(strict_types=1);

namespace CodeWithDennis\LaravelModelCache\Traits;

use CodeWithDennis\LaravelModelCache\CachedBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

trait HasCache
{
    protected int $cacheTtl = 600;

    public static function bootHasCache(): void
    {
        static::created(fn () => static::flushCache());
        static::updated(function ($model): void {
            static::forgetModelKey($model);
            static::flushCache();
        });
        static::deleted(function ($model): void {
            static::forgetModelKey($model);
            static::flushCache();
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function ($model): void {
                static::forgetModelKey($model);
                static::flushCache();
            });
        }
    }

    /**
     * Forget cache key for this model id (find($id)). Enables targeted bust.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     */
    protected static function forgetModelKey($model): void
    {
        $key = $model->getKey();
        if ($key !== null) {
            Cache::forget(static::class.':'.$key);
        }
    }

    /**
     * Flush all cache for this model (collection queries). Called on create/update/delete/restore.
     */
    protected static function flushCache(): void
    {
        Cache::tags([static::class])->flush();
    }

    public function newEloquentBuilder($query): Builder
    {
        return new CachedBuilder($query);
    }

    public function cacheTtl(): int
    {
        return $this->cacheTtl;
    }
}
