<?php

declare(strict_types=1);

namespace Syriable\LaravelMessages\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Syriable\LaravelMessages\Models\Concerns\HasConfigurableKey;
use Syriable\LaravelMessages\Models\Concerns\InteractsWithPackageTables;
use Syriable\LaravelMessages\Support\PackageConfig;

/**
 * @property int|string $id
 * @property int|string $message_id
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string $mime_type
 * @property string $extension
 * @property int $size
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MessageAttachment extends Model
{
    use HasConfigurableKey;
    use InteractsWithPackageTables;

    protected $guarded = [];

    protected static function tableKey(): string
    {
        return 'message_attachments';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'metadata' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * A URL to the file. Uses a temporary (signed) URL when enabled and
     * supported by the disk.
     */
    public function url(): string
    {
        $disk = Storage::disk($this->disk);

        if (PackageConfig::bool('attachments.temporary_urls.enabled')) {
            return $disk->temporaryUrl(
                $this->path,
                now()->addMinutes(PackageConfig::int('attachments.temporary_urls.expire_after_minutes', 30)),
            );
        }

        return $disk->url($this->path);
    }

    public function deleteFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }

    /**
     * @param  Builder<MessageAttachment>  $query
     * @return Builder<MessageAttachment>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }
}
