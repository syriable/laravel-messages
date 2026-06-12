<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Syriable\LaravelMessages\Contracts\ManagesReports;
use Syriable\LaravelMessages\Jobs\ArchiveInactiveConversations;
use Syriable\LaravelMessages\Jobs\CleanTemporaryFiles;
use Syriable\LaravelMessages\Jobs\DeleteExpiredAttachments;
use Syriable\LaravelMessages\Jobs\PruneReports;
use Syriable\LaravelMessages\Models\MessageAttachment;
use Syriable\LaravelMessages\Models\MessageReport;

it('deletes expired attachments and their files', function () {
    Storage::fake('local');

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, null, [fakePdf(), fakePng()]);

    $expired = $message->attachments->first();
    $expired->forceFill(['expires_at' => now()->subDay()])->save();

    (new DeleteExpiredAttachments)->handle();

    expect(MessageAttachment::count())->toBe(1);
    Storage::disk('local')->assertMissing($expired->path);
});

it('cleans orphaned files from the attachment directory', function () {
    Storage::fake('local');

    $alice = user();
    $bob = user();

    $message = $alice->sendMessageTo($bob, null, [fakePdf()]);
    $kept = $message->attachments->sole()->path;

    Storage::disk('local')->put('message-attachments/999/orphan.pdf', 'leftover');

    (new CleanTemporaryFiles)->handle();

    Storage::disk('local')->assertExists($kept);
    Storage::disk('local')->assertMissing('message-attachments/999/orphan.pdf');
});

it('archives inactive conversations for all participants', function () {
    config()->set('laravel-messages.cleanup.archive_inactive_after_days', 30);

    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'ancient history');

    $this->travel(31)->days();

    (new ArchiveInactiveConversations)->handle();

    expect($alice->archivedConversations()->count())->toBe(1)
        ->and($bob->archivedConversations()->count())->toBe(1);
});

it('does nothing when inactive archiving is disabled', function () {
    config()->set('laravel-messages.cleanup.archive_inactive_after_days', null);

    $alice = user();
    $bob = user();

    $alice->sendMessageTo($bob, 'hello');

    $this->travel(365)->days();

    (new ArchiveInactiveConversations)->handle();

    expect($alice->conversationInbox()->count())->toBe(1);
});

it('prunes old resolved reports but keeps pending ones', function () {
    config()->set('laravel-messages.cleanup.prune_reports_after_days', 90);

    $alice = user();
    $bob = user();

    $message = $bob->sendMessageTo($alice, 'bad');
    $reports = app(ManagesReports::class);

    $pending = $reports->report($alice, $message, 'pending forever');
    $resolved = $reports->report($alice, $message, 'old and resolved');
    $resolved->markReviewed();
    $resolved->forceFill(['resolved_at' => now()->subDays(120)])->save();

    (new PruneReports)->handle();

    expect(MessageReport::count())->toBe(1)
        ->and(MessageReport::sole()->is($pending))->toBeTrue();
});
