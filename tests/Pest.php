<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Syriable\LaravelMessages\Tests\TestCase;
use Syriable\LaravelMessages\Tests\TestSupport\Models\User;

uses(TestCase::class)->in(__DIR__);

function user(?string $name = null): User
{
    static $counter = 0;

    $counter++;

    return User::create([
        'name' => $name ?? "User {$counter}",
        'email' => "user-{$counter}-".uniqid().'@example.test',
    ]);
}

/**
 * A real (magic-byte valid) PDF upload so server-side MIME detection works.
 */
function fakePdf(string $name = 'document.pdf'): UploadedFile
{
    $content = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    return UploadedFile::fake()->createWithContent($name, $content);
}

/**
 * A real 1x1 PNG upload.
 */
function fakePng(string $name = 'image.png'): UploadedFile
{
    $content = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    );

    return UploadedFile::fake()->createWithContent($name, $content);
}

function fakeText(string $name = 'notes.txt'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, 'plain text content');
}

/**
 * A real UploadedFile (not Laravel's testing double) whose contents do not
 * match its extension — Testing\File fakes getMimeType(), so MIME-sniffing
 * tests need the real implementation.
 */
function spoofedUpload(string $name, string $content): UploadedFile
{
    $path = (string) tempnam(sys_get_temp_dir(), 'laravel-messages-test');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}
