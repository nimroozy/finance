<?php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SurveyCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $surveyId,
        public int $branchId,
        public int $leadId,
    ) {}
}
