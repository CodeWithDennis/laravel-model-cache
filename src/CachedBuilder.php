<?php

declare(strict_types=1);

namespace CodeWithDennis\CachePreWarming;

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
    /**
     * @param  array<int, string>|string  $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, TModel>
     */
    public function get($columns = ['*'])
    {
        $key = $this->queryCacheKey();
        $ttl = $this->getCacheTtl();

        /** @var Collection<int, TModel> $result */
        $result = Cache::remember($key, $ttl, fn () => parent::get($columns));

        return $result;
    }

    /**
     * @param  string|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  string|null  $key
     * @return \Illuminate\Support\Collection<array-key, mixed>
     */
    public function pluck($column, $key = null)
    {
        $suffix = 'pluck:'.md5(serialize([$column, $key]));
        $cacheKey = $this->queryCacheKey($suffix);
        $ttl = $this->getCacheTtl();

        /** @var \Illuminate\Support\Collection<int|string, mixed> $result */
        $result = Cache::remember($cacheKey, $ttl, fn () => parent::pluck($column, $key));

        return $result;
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $columns
     * @return int<0, max>
     */
    public function count($columns = '*')
    {
        $suffix = 'count:'.md5(serialize($columns));
        $key = $this->queryCacheKey($suffix);
        $ttl = $this->getCacheTtl();

        /** @var int<0, max> $result */
        $result = Cache::remember($key, $ttl, fn () => parent::count($columns));

        return $result;
    }

    public function exists(): bool
    {
        $key = $this->queryCacheKey('exists');
        $ttl = $this->getCacheTtl();

        return (bool) Cache::remember($key, $ttl, fn () => parent::exists());
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
        $suffix = 'sum:'.md5(serialize($column));
        $key = $this->queryCacheKey($suffix);
        $ttl = $this->getCacheTtl();

        $result = Cache::remember($key, $ttl, fn () => parent::sum($column));

        return $result ?: 0;
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function avg($column)
    {
        $suffix = 'avg:'.md5(serialize($column));
        $key = $this->queryCacheKey($suffix);
        $ttl = $this->getCacheTtl();

        return Cache::remember($key, $ttl, fn () => parent::avg($column));
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function min($column)
    {
        $suffix = 'min:'.md5(serialize($column));
        $key = $this->queryCacheKey($suffix);
        $ttl = $this->getCacheTtl();

        return Cache::remember($key, $ttl, fn () => parent::min($column));
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function max($column)
    {
        $suffix = 'max:'.md5(serialize($column));
        $key = $this->queryCacheKey($suffix);
        $ttl = $this->getCacheTtl();

        return Cache::remember($key, $ttl, fn () => parent::max($column));
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @return mixed
     */
    public function average($column)
    {
        return $this->avg($column);
    }

    protected function getCacheTtl(): int
    {
        $model = $this->getModel();

        /** @var int $ttl */
        $ttl = method_exists($model, 'cacheTtl') ? $model->cacheTtl() : 600;

        return $ttl;
    }

    protected function queryCacheKey(string $suffix = ''): string
    {
        $raw = $this->applyScopes()->toBase()->toRawSql();

        return md5($raw.$suffix);
    }
}
