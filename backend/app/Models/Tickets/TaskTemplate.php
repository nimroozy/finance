<?php

namespace App\Models\Tickets;

use Illuminate\Database\Eloquent\Model;

class TaskTemplate extends Model
{
    public const WORKFLOW_INSTALLATION = 'installation';

    public const WORKFLOW_TICKET = 'ticket';

    public const WORKFLOW_GENERIC = 'generic';

    protected $fillable = [
        'code',
        'name',
        'workflow_type',
        'steps',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
