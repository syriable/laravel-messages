<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Syriable\LaravelMessages\Contracts\OptimizesImages;
use Syriable\LaravelMessages\Contracts\ScansAttachments;
use Syriable\LaravelMessages\Events\AttachmentUploaded;
use Syriable\LaravelMessages\Exceptions\InvalidAttachmentException;
use Syriable\LaravelMessages\Models\MessageAttachment;

beforeEach(function () {
    Storage::fake('local');
});

it('stores attachments with randomized filenames under a per-conversation path', function () {
    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, null, [fakePdf('Quarterly Report.pdf')]);
    $attachment = $message->attachments->sole();

    expect($attachment->filename)->toBe('Quarterly Report.pdf')
        ->and($attachment->path)->not->toContain('Quarterly')
        ->and($attachment->path)->toStartWith("message-attachments/{$message->conversation_id}/")
        ->and($attachment->extension)->toBe('pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->size)->toBeGreaterThan(0);

    Storage::disk('local')->assertExists($attachment->path);
});

it('rejects disallowed extensions', function () {
    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, null, [
        UploadedFile::fake()->createWithContent('script.exe', 'MZ binary'),
    ]);
})->throws(InvalidAttachmentException::class);

it('rejects files whose real content type is not allowed', function () {
    $alice = user();
    $bob = user();

    // Allowed extension, but the actual bytes are plain text.
    $alice->sendMessageTo($bob, null, [
        spoofedUpload('fake-image.png', 'just some text'),
    ]);
})->throws(InvalidAttachmentException::class);

it('rejects oversized files', function () {
    config()->set('laravel-messages.attachments.max_file_size', 0);

    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, null, [fakePdf()]);
})->throws(InvalidAttachmentException::class);

it('rejects too many attachments', function () {
    config()->set('laravel-messages.attachments.max_attachments_per_message', 1);

    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, null, [fakePdf(), fakePng()]);
})->throws(InvalidAttachmentException::class);

it('rejects everything when attachments are disabled', function () {
    config()->set('laravel-messages.attachments.enabled', false);

    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, null, [fakePdf()]);
})->throws(InvalidAttachmentException::class);

it('supports extending the allowed file types', function () {
    config()->set('laravel-messages.attachments.allowed_extensions', ['txt']);
    config()->set('laravel-messages.attachments.allowed_mime_types', ['text/plain']);

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, null, [fakeText()]);

    expect($message->attachments->sole()->mime_type)->toBe('text/plain');
});

it('dispatches AttachmentUploaded', function () {
    Event::fake([AttachmentUploaded::class]);

    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, null, [fakePdf(), fakePng()]);

    Event::assertDispatchedTimes(AttachmentUploaded::class, 2);
});

it('runs the configured virus scanner and rejects dirty files', function () {
    $scanner = new class implements ScansAttachments
    {
        public function scan(UploadedFile $file): bool
        {
            return false; // everything is dirty
        }
    };

    app()->instance($scanner::class, $scanner);
    config()->set('laravel-messages.attachments.virus_scanner', $scanner::class);

    $alice = user();
    $bob = user();

    expect(fn () => $alice->sendMessageTo($bob, null, [fakePdf()]))
        ->toThrow(InvalidAttachmentException::class);

    expect(MessageAttachment::count())->toBe(0);
});

it('runs the configured image optimizer hook', function () {
    config()->set('laravel-messages.queue.process_attachments', false);

    $optimizer = new class implements OptimizesImages
    {
        /** @var array<int, string> */
        public array $optimized = [];

        public function optimize(MessageAttachment $attachment): void
        {
            $this->optimized[] = $attachment->path;
        }
    };

    app()->instance($optimizer::class, $optimizer);
    config()->set('laravel-messages.attachments.image_optimizer', $optimizer::class);

    $alice = user();
    $bob = user();

    // One image, one pdf: only the image goes through the optimizer.
    $message = $alice->sendMessageTo($bob, null, [fakePng(), fakePdf()]);

    $imagePath = $message->attachments->firstWhere('mime_type', 'image/png')->path;

    expect($optimizer->optimized)->toBe([$imagePath]);
});

it('records an expiry date when configured', function () {
    config()->set('laravel-messages.attachments.expire_after_days', 7);

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, null, [fakePdf()]);

    expect($message->attachments->sole()->expires_at)->not->toBeNull();
});
