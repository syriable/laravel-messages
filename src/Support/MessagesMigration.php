<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Support;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\ForeignIdColumnDefinition;
use Illuminate\Support\Facades\Schema;

abstract class MessagesMigration extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    /**
     * The (configured) name of the table this migration creates.
     */
    abstract protected function table(): string;

    protected function tableName(string $key): string
    {
        /** @var string */
        return config("laravel-messages.database.tables.{$key}", $key);
    }

    protected function idType(): string
    {
        /** @var string */
        return config('laravel-messages.database.id_type', 'id');
    }

    protected function userIdType(): string
    {
        /** @var string */
        return config('laravel-messages.database.user_id_type', 'id');
    }

    protected function primaryKey(Blueprint $table): void
    {
        match ($this->idType()) {
            'uuid' => $table->uuid('id')->primary(),
            'ulid' => $table->ulid('id')->primary(),
            default => $table->id(),
        };
    }

    /**
     * Add a foreign key to another package table, matching the configured
     * primary key type.
     */
    protected function foreignKey(
        Blueprint $table,
        string $column,
        string $referencedTableKey,
        bool $nullable = false,
        bool $cascadeOnDelete = true,
    ): ForeignIdColumnDefinition|ColumnDefinition {
        $definition = match ($this->idType()) {
            'uuid' => $table->foreignUuid($column),
            'ulid' => $table->foreignUlid($column),
            default => $table->foreignId($column),
        };

        if ($nullable) {
            $definition->nullable();
        }

        $constraint = $definition->constrained($this->tableName($referencedTableKey));

        if ($cascadeOnDelete) {
            $constraint->cascadeOnDelete();
        } elseif ($nullable) {
            $constraint->nullOnDelete();
        }

        return $definition;
    }

    /**
     * Add a column referencing a user. Intentionally unconstrained so the
     * package makes no assumptions about your users table (multi-tenancy,
     * external identity providers, ...).
     */
    protected function userKey(Blueprint $table, string $column, bool $nullable = false): ColumnDefinition
    {
        $definition = match ($this->userIdType()) {
            'uuid' => $table->uuid($column),
            'ulid' => $table->ulid($column),
            'string' => $table->string($column, 191),
            default => $table->unsignedBigInteger($column),
        };

        if ($nullable) {
            $definition->nullable();
        }

        return $definition;
    }
}
