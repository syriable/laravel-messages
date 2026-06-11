<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Tests\TestSupport\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Syriable\LaravelMessages\Concerns\HasMessaging;

class User extends Authenticatable
{
    use HasMessaging;
    use Notifiable;

    protected $guarded = [];
}
