<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Syriable\LaravelMessages\Contracts\ManagesAttachments;
use Syriable\LaravelMessages\Contracts\ScansAttachments;
use Syriable\LaravelMessages\Events\AttachmentUploaded;
use Syriable\LaravelMessages\Exceptions\InvalidAttachmentException;
use Syriable\LaravelMessages\Jobs\ProcessAttachment;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Models\MessageAttachment;
use Syriable\LaravelMessages\Support\PackageConfig;

class AttachmentManager implements ManagesAttachments
{
    public function validate(array $files): void
    {
        if ($files === []) {
            return;
        }

        if (! PackageConfig::bool('attachments.enabled', true)) {
            throw InvalidAttachmentException::disabled();
        }

        $max = PackageConfig::int('attachments.max_attachments_per_message', 5);

        if (count($files) > $max) {
            throw InvalidAttachmentException::tooMany($max);
        }

        foreach ($files as $file) {
            $this->validateFile($file);
        }
    }

    public function store(Message $message, array $files): Collection
    {
        return collect($files)->map(
            fn (UploadedFile $file): MessageAttachment => $this->storeFile($message, $file),
        );
    }

    protected function validateFile(UploadedFile $file): void
    {
        /** @var array<int, string> $allowedExtensions */
        $allowedExtensions = PackageConfig::array('attachments.allowed_extensions');

        /** @var array<int, string> $allowedMimeTypes */
        $allowedMimeTypes = PackageConfig::array('attachments.allowed_mime_types');

        $maxKilobytes = PackageConfig::int('attachments.max_file_size', 10240);

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, array_map('strtolower', $allowedExtensions), true)) {
            throw InvalidAttachmentException::disallowedExtension($extension);
        }

        // Server-side MIME detection — never trust the client-provided type.
        $mimeType = (string) $file->getMimeType();

        if (! in_array($mimeType, $allowedMimeTypes, true)) {
            throw InvalidAttachmentException::disallowedMimeType($mimeType);
        }

        if ($file->getSize() > $maxKilobytes * 1024) {
            throw InvalidAttachmentException::tooLarge($maxKilobytes);
        }

        $scanner = $this->scanner();

        if ($scanner !== null && ! $scanner->scan($file)) {
            throw InvalidAttachmentException::failedVirusScan();
        }
    }

    protected function storeFile(Message $message, UploadedFile $file): MessageAttachment
    {
        $disk = PackageConfig::string('attachments.disk', 'local');
        $directory = trim(PackageConfig::string('attachments.directory', 'message-attachments'), '/');
        $extension = strtolower($file->getClientOriginalExtension());

        // Randomized name inside a per-conversation directory: stored files
        // are never addressable by guessing original filenames.
        $filename = Str::random(40).($extension !== '' ? ".{$extension}" : '');
        $path = "{$directory}/{$message->conversation_id}";

        $storedPath = (string) Storage::disk($disk)->putFileAs($path, $file, $filename);

        $expireDays = PackageConfig::intOrNull('attachments.expire_after_days');

        /** @var MessageAttachment $attachment */
        $attachment = $message->attachments()->create([
            'disk' => $disk,
            'path' => $storedPath,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'extension' => $extension,
            'size' => (int) $file->getSize(),
            'expires_at' => $expireDays !== null ? now()->addDays($expireDays) : null,
        ]);

        AttachmentUploaded::dispatch($attachment);

        if (PackageConfig::bool('queue.process_attachments', true)) {
            ProcessAttachment::dispatch($attachment);
        } else {
            ProcessAttachment::dispatchSync($attachment);
        }

        return $attachment;
    }

    protected function scanner(): ?ScansAttachments
    {
        $scanner = PackageConfig::stringOrNull('attachments.virus_scanner');

        if ($scanner === null) {
            return null;
        }

        $instance = app($scanner);

        return $instance instanceof ScansAttachments ? $instance : null;
    }
}
