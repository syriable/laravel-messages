<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Tests\Feature;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Tests\TestCase;
use Syriable\LaravelMessages\Tests\TestSupport\Models\User;

class UlidKeysTest extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('laravel-messages.database.id_type', 'ulid');
    }

    #[Test]
    public function package_models_use_ulid_primary_keys_when_configured(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.test']);
        $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.test']);

        /** @var Message $message */
        $message = $alice->sendMessageTo($bob, 'ulid powered');

        $this->assertTrue(Str::isUlid($message->getKey()));
        $this->assertTrue(Str::isUlid($message->conversation_id));
        $this->assertTrue(Str::isUlid($message->conversation->participants->first()->getKey()));

        // Round-trips correctly through queries.
        $this->assertTrue($message->fresh()->isSentBy($alice));
        $this->assertSame(1, $bob->unreadMessagesCount());
    }
}
