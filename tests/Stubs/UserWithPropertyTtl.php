<?php

declare(strict_types=1);

namespace Tests\Stubs;

class UserWithPropertyTtl extends User
{
    protected int $cacheTtl = 123;
}
