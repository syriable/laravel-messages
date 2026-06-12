<?php

declare(strict_types=1);

use Syriable\LaravelMessages\Support\UserKey;

it('normalizes models and raw keys', function () {
    $alice = user();

    expect(UserKey::of($alice))->toBe($alice->getKey())
        ->and(UserKey::of(42))->toBe(42)
        ->and(UserKey::of('abc'))->toBe('abc');
});

it('produces a symmetric pair hash', function () {
    expect(UserKey::pairHash(1, 2))->toBe(UserKey::pairHash(2, 1))
        ->and(UserKey::pairHash(1, 2))->not->toBe(UserKey::pairHash(1, 3))
        ->and(strlen(UserKey::pairHash('a', 'b')))->toBe(64);
});

it('does not collide on ambiguous key concatenations', function () {
    // ("1", "12") vs ("11", "2") would collide with naive concatenation.
    expect(UserKey::pairHash('1', '12'))->not->toBe(UserKey::pairHash('11', '2'));
});
