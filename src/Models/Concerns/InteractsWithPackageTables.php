<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait InteractsWithPackageTables
{
    public function getTable(): string
    {
        /** @var string */
        return config(
            'laravel-messages.database.tables.'.static::tableKey(),
            parent::getTable(),
        );
    }

    /**
     * The config key under "laravel-messages.database.tables" for this model.
     */
    abstract protected static function tableKey(): string;

    /**
     * @return class-string<Model>
     */
    protected static function userModel(): string
    {
        /** @var class-string<Model> */
        return config('laravel-messages.user_model');
    }
}
