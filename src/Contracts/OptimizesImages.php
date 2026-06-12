<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Contracts;

use Syriable\LaravelMessages\Models\MessageAttachment;

interface OptimizesImages
{
    public function optimize(MessageAttachment $attachment): void;
}
