<?php

namespace App\Models\Tickets;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[ScopedBy([BelongsToBranchScope::class])]
class OperationalAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'branch_id',
        'attachable_type',
        'attachable_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'kind',
        'thumbnail_path',
        'virus_scan_status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $attachment): void {
            if (empty($attachment->uuid)) {
                $attachment->uuid = (string) Str::uuid();
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
