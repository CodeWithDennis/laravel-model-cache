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
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function (): void {
                static::flushModelCache();
            });
        }
    }

    /**
     * Flush all cached query results for this model by clearing the model's tag.
     * Entries are stored with both a query-based key and this model tag.
     */
    protected static function flushModelCache(): void
    {
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags([static::class])->flush();
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
