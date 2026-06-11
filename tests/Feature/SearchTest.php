<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Syriable\LaravelMessages\Contracts\SearchesMessages;
use Syriable\LaravelMessages\Facades\Messages;

it('searches message content visible to the user', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'the quarterly numbers look great');
    $alice->sendMessageTo($bob, 'lunch tomorrow?');

    $results = app(SearchesMessages::class)->messages($bob, 'quarterly')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->body)->toContain('quarterly');
});

it('does not surface messages hidden by delete-for-me', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'find me later');
    Messages::deleteConversationFor($alice->conversationWith($bob), $bob);

    expect(app(SearchesMessages::class)->messages($bob, 'find me')->count())->toBe(0)
        ->and(app(SearchesMessages::class)->messages($alice, 'find me')->count())->toBe(1);
});

it('searches attachment filenames', function () {
    Storage::fake('local');

    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, null, [fakePdf('contract-final.pdf')]);

    expect(app(SearchesMessages::class)->messages($bob, 'contract')->count())->toBe(1);
});

it('searches conversations by subject and content', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'about the roadmap');
    $alice->conversationWith($bob)->update(['subject' => 'Planning']);

    $search = app(SearchesMessages::class);

    expect($search->conversations($alice, 'Planning')->count())->toBe(1)
        ->and($search->conversations($alice, 'roadmap')->count())->toBe(1)
        ->and($search->conversations($alice, 'nonexistent')->count())->toBe(0);
});

it('searches conversations by participant name', function () {
    $alice = user('Alice Smith');
    $bob = user('Bob Jones');
    $carol = user('Carol White');

    $alice->sendMessageTo($bob, 'hi bob');
    $alice->sendMessageTo($carol, 'hi carol');

    $results = app(SearchesMessages::class)->conversations($alice, 'Jones')->get();

    expect($results)->toHaveCount(1);
});

it('escapes like wildcards in search terms', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, '100% sure');
    $alice->sendMessageTo($bob, 'completely unrelated');

    expect(app(SearchesMessages::class)->messages($bob, '100%')->count())->toBe(1)
        ->and(app(SearchesMessages::class)->messages($bob, '%')->count())->toBe(1);
});
