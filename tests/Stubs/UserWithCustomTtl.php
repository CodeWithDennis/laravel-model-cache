<?php

declare(strict_types=1);

namespace Tests\Stubs;

class UserWithCustomTtl extends User
{
    public function cacheTtl(): int
    {
        return 300;
    }
}
