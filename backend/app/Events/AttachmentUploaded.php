<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttachmentUploaded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $attachmentId, public int $branchId, public string $attachableType, public int $attachableId,
    ) {}
}
