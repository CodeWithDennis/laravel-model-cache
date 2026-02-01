<?php

declare(strict_types=1);

namespace CodeWithDennis\LaravelModelCache;

use CodeWithDennis\LaravelModelCache\Traits\HasCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class CachedBuilder extends Builder
{
    protected bool $cacheForever = false;

    private ?int $resolvedTtl = null;

    /**
     * Cache the result indefinitely (no TTL). Use before executing the query.
     *
     * @return $this
     */
    public function warmup(): static
    {
        $this->cacheForever = true;

        return $this;
    }

    /**
     * @param  array<int, string>|string  $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>
     */
    public function get($columns = ['*'])
    {
        $key = $this->queryCacheKey();

        /** @var Collection<int, TModel> $result */
        $result = $this->remember($key, fn () => parent::get($columns));

        return $result;
    }

    /**
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  string|null  $key
     * @return \Illuminate\Support\Collection<array-key, mixed>
     */
    public function pluck($column, $key = null)
    {
        $cacheKey = $this->queryCacheKey($this->cacheKeySuffix('pluck', $column, $key));

        /** @var \Illuminate\Support\Collection<int|string, mixed> $result */
        $result = $this->remember($cacheKey, fn () => parent::pluck($column, $key));

        return $result;
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $columns
     * @return int<0, max>
     */
    public function count($columns = '*')
    {
        $key = $this->queryCacheKey($this->cacheKeySuffix('count', $columns));

        /** @var int<0, max> $result */
        $result = $this->remember($key, fn () => parent::count($columns));

        return $result;
    }

    public function exists(): bool
    {
        $key = $this->queryCacheKey('exists');

        return (bool) $this->remember($key, fn () => parent::exists());
    }

    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function sum($column)
    {
        $key = $this->queryCacheKey($this->cacheKeySuffix('sum', $column));

        $result = $this->remember($key, fn () => parent::sum($column));

        return $result ?: 0;
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function avg($column)
    {
        $key = $this->queryCacheKey($this->cacheKeySuffix('avg', $column));

        return $this->remember($key, fn () => parent::avg($column));
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function min($column)
    {
        $key = $this->queryCacheKey($this->cacheKeySuffix('min', $column));

        return $this->remember($key, fn () => parent::min($column));
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function max($column)
    {
        $key = $this->queryCacheKey($this->cacheKeySuffix('max', $column));

        return $this->remember($key, fn () => parent::max($column));
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function average($column)
    {
        return $this->avg($column);
    }

    /**
     * Store with key (query-based) and tag(s). Collection queries use :collections tag;
     * single/multi by id use :{id} so we only bust that id on update/delete.
     *
     * @param  \Closure(): mixed  $callback
     */
    protected function remember(string $key, \Closure $callback): mixed
    {
        $tags = $this->getCacheTags();

        if ($this->cacheForever) {
            $value = $callback();
            if ($tags !== []) {
                Cache::tags($tags)->forever($key, $value);
            } else {
                Cache::forever($key, $value);
            }

            return $value;
        }

        if ($tags !== []) {
            return Cache::tags($tags)->remember($key, $this->getCacheTtl(), $callback);
        }

        return Cache::remember($key, $this->getCacheTtl(), $callback);
    }

    /**
     * Tags for this query: [model::class.':collections'] for collection queries,
     * or [model::class.':'.$id, ...] for single/multi by primary key only.
     *
     * @return array<int, string>
     */
    protected function getCacheTags(): array
    {
        $model = $this->getModel();

        if (! in_array(HasCache::class, class_uses($model), true)) {
            return [];
        }

        $ids = $this->getPrimaryKeyIdsFromQuery();
        $prefix = $model::class.':';

        if ($ids === null) {
            return [$prefix.'collections'];
        }

        return array_map(
            fn ($id) => $prefix.$id,
            $ids,
        );
    }

    /**
     * If the query is constrained only by primary key (= or In), return the id(s).
     * Otherwise null (collection query).
     *
     * @return array<int, int|string>|null
     */
    protected function getPrimaryKeyIdsFromQuery(): ?array
    {
        $query = $this->applyScopes()->getQuery();
        $keyName = $this->getModel()->getQualifiedKeyName();

        if ($query->wheres === []) {
            return null;
        }

        $ids = [];

        foreach ($query->wheres as $where) {
            if (! is_array($where)) {
                return null;
            }

            $clauseIds = $this->extractPrimaryKeyIdsFromClause($where, $keyName);

            if ($clauseIds === null) {
                return null;
            }

            $ids = [...$ids, ...$clauseIds];
        }

        $result = array_values(array_unique($ids));

        return $result === [] ? null : $result;
    }

    /**
     * Extract primary key id(s) from a single where clause if it constrains only the given key.
     *
     * @param  array<mixed, mixed>  $where
     * @return array<int, int|string>|null
     */
    private function extractPrimaryKeyIdsFromClause(array $where, string $keyName): ?array
    {
        if (($where['column'] ?? null) !== $keyName) {
            return null;
        }

        $type = $where['type'] ?? null;

        if ($type === 'Basic' && ($where['operator'] ?? null) === '=') {
            return $this->singleIdOrNull($where['value'] ?? null);
        }

        if ($type === 'In' || $type === 'InRaw') {
            $values = $where['values'] ?? [];

            if (! is_array($values)) {
                return null;
            }

            return $this->idsFromValues($values);
        }

        return null;
    }

    /**
     * @return array<int, int|string>|null
     */
    private function singleIdOrNull(mixed $value): ?array
    {
        $id = $this->normalizeId($value);

        return $id !== null ? [$id] : null;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<int, int|string>|null
     */
    private function idsFromValues(array $values): ?array
    {
        $ids = [];

        foreach ($values as $value) {
            $id = $this->normalizeId($value);

            if ($id === null) {
                return null;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    private function normalizeId(mixed $value): int|string|null
    {
        $value = $value instanceof \BackedEnum ? $value->value : $value;

        return is_int($value) || is_string($value) ? $value : null;
    }

    protected function getCacheTtl(): int
    {
        if ($this->resolvedTtl !== null) {
            return $this->resolvedTtl;
        }

        $model = $this->getModel();
        /** @var int $ttl */
        $ttl = method_exists($model, 'cacheTtl') ? $model->cacheTtl() : 600;
        $this->resolvedTtl = $ttl;

        return $ttl;
    }

    /**
     * @param  mixed  ...$args
     */
    protected function cacheKeySuffix(string $method, ...$args): string
    {
        return $method.':'.md5(serialize($args));
    }

    protected function queryCacheKey(string $suffix = ''): string
    {
        $raw = $this->applyScopes()->toBase()->toRawSql();

        return md5($raw.$suffix);
    }
}
