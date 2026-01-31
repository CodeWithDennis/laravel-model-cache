<?php

declare(strict_types=1);

namespace Tests\Stubs;

use CodeWithDennis\CachePreWarming\Traits\HasCache;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasCache;

    protected $table = 'users';

    protected $fillable = ['name', 'score', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];
}
