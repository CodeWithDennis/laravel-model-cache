<?php

declare(strict_types=1);

namespace CodeWithDennis\CachePreWarming;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Custom Builder that adds automatic query result caching.
 *
 * This class extends Laravel's Builder and overrides get() and first()
 * to intercept query execution and return cached results when available.
 *
 * @note This overrides Laravel's Builder methods. If Laravel changes
 * these method signatures in future versions, this package may need updates.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class CachedBuilder extends Builder
{
    /**
     * Execute the query as a "select" statement and cache the results.
     *
     * @param  array<int, string>|string  $columns
     * @return EloquentCollection<int, TModel>
     */
    public function get($columns = ['*']): EloquentCollection
    {
        /** @var TModel $model */
        $model = $this->getModel();

        // Set columns on query so toRawSql() includes them
        if ($columns !== ['*']) {
            /** @var array<int, string> $columnsArray */
            $columnsArray = is_array($columns) ? $columns : [$columns];
            $this->getQuery()->columns = $columnsArray;
        }

        /** @var string $key */
        $key = $model::queryCacheKey($this);

        /** @var EloquentCollection<int, TModel>|null $cached */
        $cached = Cache::get($key);
        if ($cached !== null) {
            /** @var EloquentCollection<int, TModel> $cached */
            return $cached;
        }

        /** @var EloquentCollection<int, TModel> $result */
        $result = parent::get($columns);
        /** @var int $ttl */
        $ttl = $model->cacheTtl();
        Cache::put($key, $result, $ttl);

        return $result;
    }

    /**
     * Execute the query and get the first result, caching the result.
     *
     * @param  array<int, string>|string  $columns
     * @return TModel|null
     */
    public function first($columns = ['*']): ?Model
    {
        /** @var TModel $model */
        $model = $this->getModel();

        // Set columns on query so toRawSql() includes them
        if ($columns !== ['*']) {
            /** @var array<int, string> $columnsArray */
            $columnsArray = is_array($columns) ? $columns : [$columns];
            $this->getQuery()->columns = $columnsArray;
        }

        /** @var string $key */
        $key = $model::queryCacheKey($this);

        /** @var TModel|string|null $cached */
        $cached = Cache::get($key);
        if ($cached !== null) {
            if ($cached === '__CACHED_NULL__') {
                return null;
            }

            /** @var TModel $cached */
            return $cached;
        }

        /** @var TModel|null $result */
        $result = parent::first($columns);
        /** @var int $ttl */
        $ttl = $model->cacheTtl();
        Cache::put($key, $result ?? '__CACHED_NULL__', $ttl);

        return $result;
    }
}
