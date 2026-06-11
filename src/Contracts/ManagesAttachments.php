<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Contracts;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Syriable\LaravelMessages\Exceptions\InvalidAttachmentException;
use Syriable\LaravelMessages\Models\Message;
use Syriable\LaravelMessages\Models\MessageAttachment;

interface ManagesAttachments
{
    /**
     * @param  array<int, UploadedFile>  $files
     *
     * @throws InvalidAttachmentException
     */
    public function validate(array $files): void;

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, MessageAttachment>
     */
    public function store(Message $message, array $files): Collection;
}
