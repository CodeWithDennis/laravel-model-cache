<?php

declare(strict_types=1);

namespace CodeWithDennis\LaravelModelCache\Traits;

use CodeWithDennis\LaravelModelCache\CachedBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

trait HasCache
{
    protected int $cacheTtl = 600;

    public static function bootHasCache(): void
    {
        $flush = static function (Model $model): void {
            Cache::tags($model::cacheTag())->flush();
        };

        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event($flush);
        }

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored($flush);
        }
    }

    /**
     * Tag used for all cached queries for this model. Must match the tag used when storing (model class name).
     * For manual invalidation: Cache::tags(MyModel::cacheTag())->flush().
     *
     * @return array<int, string>
     */
    public static function cacheTag(): array
    {
        return [static::class];
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
