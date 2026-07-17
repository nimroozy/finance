<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Installation extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SURVEY_SCHEDULED = 'survey_scheduled';
    public const STATUS_SURVEY_DONE = 'survey_done';
    public const STATUS_AWAITING_FINANCE = 'awaiting_finance';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_EQUIPMENT_RESERVED = 'equipment_reserved';
    public const STATUS_IN_INSTALL = 'in_install';
    public const STATUS_AWAITING_ACTIVATION = 'awaiting_activation';
    public const STATUS_ACTIVATED = 'activated';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, list<string>> */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SURVEY_SCHEDULED, self::STATUS_AWAITING_FINANCE, self::STATUS_CANCELLED],
        self::STATUS_SURVEY_SCHEDULED => [self::STATUS_SURVEY_DONE, self::STATUS_CANCELLED],
        self::STATUS_SURVEY_DONE => [self::STATUS_AWAITING_FINANCE, self::STATUS_CANCELLED],
        self::STATUS_AWAITING_FINANCE => [self::STATUS_APPROVED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_EQUIPMENT_RESERVED, self::STATUS_IN_INSTALL, self::STATUS_CANCELLED],
        self::STATUS_EQUIPMENT_RESERVED => [self::STATUS_IN_INSTALL, self::STATUS_CANCELLED],
        self::STATUS_IN_INSTALL => [self::STATUS_AWAITING_ACTIVATION, self::STATUS_CANCELLED],
        self::STATUS_AWAITING_ACTIVATION => [self::STATUS_ACTIVATED, self::STATUS_CANCELLED],
        self::STATUS_ACTIVATED => [self::STATUS_ACCEPTED],
        self::STATUS_ACCEPTED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'uuid', 'branch_id', 'number', 'customer_id', 'ticket_id', 'template_id',
        'created_by', 'status', 'address', 'latitude', 'longitude', 'notes', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'meta' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InstallationTaskTemplate::class, 'template_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
