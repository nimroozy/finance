<?php

namespace App\Jobs;

use App\Models\OperationalAttachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Thumbnail generation stub — safe no-op when imaging tools are unavailable.
 */
class GenerateAttachmentThumbnailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $attachmentId) {}

    public function handle(): void
    {
        $attachment = OperationalAttachment::query()->find($this->attachmentId);
        if (! $attachment) {
            return;
        }

        if ($attachment->thumbnail_status === 'ready') {
            return;
        }

        $mime = (string) $attachment->mime_type;
        if (! str_starts_with($mime, 'image/')) {
            $attachment->thumbnail_status = 'skipped';
            $attachment->save();

            return;
        }

        // No image library guaranteed in this environment — mark skipped safely.
        $attachment->thumbnail_status = 'skipped';
        $attachment->meta = array_merge($attachment->meta ?? [], [
            'thumbnail_note' => 'GenerateAttachmentThumbnailJob noop-safe stub',
        ]);
        $attachment->save();

        Log::debug('GenerateAttachmentThumbnailJob skipped (noop-safe)', [
            'attachment_id' => $attachment->id,
        ]);
    }
}
