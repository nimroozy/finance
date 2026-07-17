<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OperationalAttachment extends Model
{
    protected $fillable = [
        'uuid', 'branch_id', 'attachable_type', 'attachable_id', 'uploaded_by',
        'disk', 'path', 'original_name', 'mime_type', 'size',
        'virus_scan_status', 'thumbnail_path', 'thumbnail_status', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'meta' => 'array',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
