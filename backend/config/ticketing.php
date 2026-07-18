<?php

return [
    'enabled' => env('TICKETING_ENABLED', true),

    'ticket_number' => [
        'sequence_pad' => 6,
        'infix' => 'TKT',
    ],

    'task_number' => [
        'sequence_pad' => 6,
        'infix' => 'TSK',
    ],

    'installation_number' => [
        'sequence_pad' => 6,
        'infix' => 'INS',
    ],

    'sla' => [
        'pause_statuses' => ['waiting_customer', 'waiting_equipment', 'scheduled'],
        'default_first_response_minutes' => 60,
        'default_resolution_minutes' => 1440,
        'breach_check_batch' => 200,
    ],

    'escalation' => [
        'rules' => [
            [
                'code' => 'first_response_overdue',
                'level' => 'l1',
                'minutes_after_due' => 0,
                'notify_role' => 'Branch Finance Manager',
            ],
            [
                'code' => 'resolution_overdue',
                'level' => 'l2',
                'minutes_after_due' => 60,
                'notify_role' => 'Super Administrator',
            ],
        ],
        'batch' => 100,
    ],

    'intake' => [
        'auto_create' => env('TICKETING_AUTO_CREATE', false),
        'default_priority' => 'normal',
        'default_category' => 'general',
    ],

    'attachments' => [
        'disk' => 'local',
        'max_bytes' => 10 * 1024 * 1024,
        'allow_video' => env('TICKETING_ALLOW_VIDEO', false),
        'allowed_mimes' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4',
        ],
        'video_mimes' => [
            'video/mp4', 'video/quicktime', 'video/webm',
        ],
        'signed_url_ttl_minutes' => 30,
    ],

    'staff_action_token_ttl_minutes' => 60,

    'departments' => [
        ['code' => 'sales', 'name_en' => 'Sales', 'name_fa' => 'فروش'],
        ['code' => 'finance', 'name_en' => 'Finance', 'name_fa' => 'مالی'],
        ['code' => 'technical', 'name_en' => 'Technical', 'name_fa' => 'فنی'],
        ['code' => 'noc', 'name_en' => 'NOC', 'name_fa' => 'مرکز عملیات شبکه'],
        ['code' => 'inventory', 'name_en' => 'Inventory', 'name_fa' => 'انبار'],
        ['code' => 'management', 'name_en' => 'Management', 'name_fa' => 'مدیریت'],
        ['code' => 'support', 'name_en' => 'Support', 'name_fa' => 'پشتیبانی'],
    ],
];
