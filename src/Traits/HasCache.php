<?php

declare(strict_types=1);

namespace CodeWithDennis\LaravelModelCache\Traits;

use CodeWithDennis\LaravelModelCache\CachedBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

trait HasCache
{
    protected int $cacheTtl = 600;

    public static function bootHasCache(): void
    {
        static::created(function ($model): void {
            static::flushCollectionCache();
        });

        static::updated(function ($model): void {
            static::flushModelCache($model);
        });

        static::deleted(function ($model): void {
            static::flushModelCache($model);
        });
    }

    /**
     * Flush collection caches only (e.g. get(), where()->get()). Called on create.
     */
    protected static function flushCollectionCache(): void
    {
        Cache::tags([static::class.':collections'])->flush();
    }

    /**
     * Flush collection caches and caches for this specific model (e.g. find($id)). Called on update/delete.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     */
    protected static function flushModelCache($model): void
    {
        Cache::tags([static::class.':collections'])->flush();

        $key = $model->getKey();

        if ($key !== null) {
            Cache::tags([static::class.':'.$key])->flush();
        }
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
