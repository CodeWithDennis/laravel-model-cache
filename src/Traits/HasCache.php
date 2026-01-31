<?php

declare(strict_types=1);

namespace CodeWithDennis\CachePreWarming\Traits;

use CodeWithDennis\CachePreWarming\CachedBuilder;
use Illuminate\Database\Eloquent\Builder;

trait HasCache
{
    protected int $cacheTtl = 600;

    public function newEloquentBuilder($query): Builder
    {
        return new CachedBuilder($query);
    }

    public function cacheTtl(): int
    {
        return $this->cacheTtl;
    }
}
