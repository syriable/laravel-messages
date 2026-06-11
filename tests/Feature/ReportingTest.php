<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Syriable\LaravelMessages\Contracts\ManagesReports;
use Syriable\LaravelMessages\Enums\ReportStatus;
use Syriable\LaravelMessages\Events\MessageReported;
use Syriable\LaravelMessages\Models\MessageReport;

it('reports a message', function () {
    $alice = user();
    $bob = user();

    $message = $bob->sendMessageTo($alice, 'offensive content');

    $report = app(ManagesReports::class)->report($alice, $message, 'harassment', 'This is unacceptable.');

    expect((string) $report->reporter_id)->toBe((string) $alice->getKey())
        ->and($report->reportable)->toBeInstanceOf($message::class)
        ->and($report->reason)->toBe('harassment')
        ->and($report->description)->toBe('This is unacceptable.')
        ->and($report->status)->toBe(ReportStatus::Pending)
        ->and($report->created_at)->not->toBeNull();
});

it('reports a conversation and a user', function () {
    $alice = user();
    $bob = user();

    $bob->sendMessageTo($alice, 'hi');
    $conversation = $alice->conversationWith($bob);

    $reports = app(ManagesReports::class);

    $conversationReport = $reports->report($alice, $conversation, 'spam');
    $userReport = $reports->report($alice, $bob, 'impersonation');

    expect($conversationReport->reportable->is($conversation))->toBeTrue()
        ->and($userReport->reportable->is($bob))->toBeTrue();
});

it('dispatches an event for moderation workflows', function () {
    Event::fake([MessageReported::class]);

    $alice = user();
    $bob = user();

    $message = $bob->sendMessageTo($alice, 'bad');

    app(ManagesReports::class)->report($alice, $message, 'abuse');

    Event::assertDispatched(MessageReported::class);
});

it('moves through the moderation lifecycle', function () {
    $alice = user();
    $bob = user();

    $message = $bob->sendMessageTo($alice, 'bad');
    $report = app(ManagesReports::class)->report($alice, $message, 'abuse');

    $report->markReviewed();

    expect($report->fresh()->status)->toBe(ReportStatus::Reviewed)
        ->and($report->fresh()->resolved_at)->not->toBeNull();

    $report->dismiss();

    expect($report->fresh()->status)->toBe(ReportStatus::Dismissed);
});

it('scopes pending and prunable reports', function () {
    $alice = user();
    $bob = user();

    $message = $bob->sendMessageTo($alice, 'bad');
    $reports = app(ManagesReports::class);

    $pending = $reports->report($alice, $message, 'one');
    $old = $reports->report($alice, $message, 'two');

    $old->markReviewed();
    $old->forceFill(['resolved_at' => now()->subDays(120)])->save();

    expect(MessageReport::pending()->count())->toBe(1)
        ->and(MessageReport::prunable(90)->count())->toBe(1)
        ->and(MessageReport::prunable(90)->sole()->is($old))->toBeTrue();
});
