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
     * Store with key (query-based) and one tag per model. Any model change flushes that tag.
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
     * One tag per model. Uses the model's cacheTag() so store and flush always use the same tag (Redis).
     *
     * @return array<int, string>
     */
    protected function getCacheTags(): array
    {
        $model = $this->getModel();
        $modelClass = get_class($model);

        if (! in_array(HasCache::class, class_uses_recursive($modelClass), true)) {
            return [];
        }

        /** @var array<int, string> */
        // @phpstan-ignore-next-line staticMethod.notFound (model uses HasCache, cacheTag() exists)
        return $modelClass::cacheTag();
    }

    /**
     * Cache key: model class + ':' + md5(rawSql + suffix).
     */
    protected function queryCacheKey(string $suffix = ''): string
    {
        $model = $this->getModel();
        $modelClass = get_class($model);
        $prefix = in_array(HasCache::class, class_uses_recursive($modelClass), true) ? $modelClass.':' : '';
        $raw = $this->applyScopes()->toBase()->toRawSql();

        return $prefix.md5($raw.$suffix);
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
}
