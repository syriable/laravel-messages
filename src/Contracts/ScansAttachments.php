<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Contracts;

use Illuminate\Http\UploadedFile;

interface ScansAttachments
{
    /**
     * Return true when the file is clean. Returning false (or throwing)
     * rejects the upload.
     */
    public function scan(UploadedFile $file): bool;
}
