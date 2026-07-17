<?php

namespace App\Services\Attachments;

use App\Events\AttachmentUploaded;
use App\Jobs\GenerateAttachmentThumbnailJob;
use App\Models\Tickets\OperationalAttachment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OperationalAttachmentService
{
    public function __construct(private AuditLogger $audit) {}

    public function store(Model $attachable, UploadedFile $file, User $actor, int $branchId, ?string $kind = null): OperationalAttachment
    {
        $this->validateFile($file);

        $disk = (string) config('ticketing.attachments.disk', 'local');
        $dir = 'operational/'.$branchId.'/'.Str::lower(class_basename($attachable)).'/'.$attachable->getKey();
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $filename, $disk);

        $attachment = OperationalAttachment::query()->create([
            'uuid' => (string) Str::uuid(),
            'branch_id' => $branchId,
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'uploaded_by' => $actor->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType() ?: $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'kind' => $kind,
            'virus_scan_status' => 'pending',
        ]);

        $this->audit->log('attachment.uploaded', $attachment, null, [
            'path' => $path,
            'mime' => $attachment->mime,
            'size' => $attachment->size,
        ], $branchId);

        AttachmentUploaded::dispatch(
            $attachment->id,
            $branchId,
            $attachment->attachable_type,
            (int) $attachment->attachable_id,
        );

        GenerateAttachmentThumbnailJob::dispatch($attachment->id);

        return $attachment;
    }

    public function temporaryDownloadUrl(OperationalAttachment $attachment, ?int $ttlMinutes = null): string
    {
        $ttl = $ttlMinutes ?? (int) config('ticketing.attachments.signed_url_ttl_minutes', 30);

        return URL::temporarySignedRoute(
            'api.v1.attachments.download',
            now()->addMinutes($ttl),
            ['attachment' => $attachment->uuid],
        );
    }

    public function validateFile(UploadedFile $file): void
    {
        $max = (int) config('ticketing.attachments.max_bytes', 10 * 1024 * 1024);
        if (($file->getSize() ?: 0) > $max) {
            throw new InvalidArgumentException('Attachment exceeds maximum allowed size.');
        }

        $mime = $file->getClientMimeType() ?: $file->getMimeType() ?: '';
        $allowed = config('ticketing.attachments.allowed_mimes', []);
        if (config('ticketing.attachments.allow_video')) {
            $allowed = array_merge($allowed, config('ticketing.attachments.video_mimes', []));
        }

        if ($mime === '' || ! in_array($mime, $allowed, true)) {
            throw new InvalidArgumentException("Attachment MIME type [{$mime}] is not allowed.");
        }
    }
}
