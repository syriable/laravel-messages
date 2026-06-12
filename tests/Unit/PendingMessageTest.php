<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Syriable\LaravelMessages\Data\PendingMessage;

it('builds immutably', function () {
    $original = PendingMessage::make(1, 2);
    $withBody = $original->withBody('hi');
    $withMeta = $withBody->withMetadata(['k' => 'v']);

    expect($original->body)->toBeNull()
        ->and($withBody->body)->toBe('hi')
        ->and($withMeta->metadata)->toBe(['k' => 'v'])
        ->and($withBody->metadata)->toBe([]);
});

it('knows when it is empty', function () {
    $file = UploadedFile::fake()->createWithContent('a.pdf', '%PDF-1.4');

    expect(PendingMessage::make(1, 2)->isEmpty())->toBeTrue()
        ->and(PendingMessage::make(1, 2)->withBody('  ')->isEmpty())->toBeTrue()
        ->and(PendingMessage::make(1, 2)->withBody('x')->isEmpty())->toBeFalse()
        ->and(PendingMessage::make(1, 2)->withAttachments([$file])->isEmpty())->toBeFalse();
});
