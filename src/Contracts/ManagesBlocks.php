<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Contracts;

use Illuminate\Database\Eloquent\Model;
use Syriable\LaravelMessages\Models\UserBlock;

interface ManagesBlocks
{
    public function block(Model|int|string $blocker, Model|int|string $blocked): UserBlock;

    public function unblock(Model|int|string $blocker, Model|int|string $blocked): void;

    public function hasBlocked(Model|int|string $blocker, Model|int|string $blocked): bool;
}
