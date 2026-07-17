<?php

namespace App\Models\Services;

use Illuminate\Database\Eloquent\Model;

class ServiceAccessTechnology extends Model
{
    protected $fillable = ['code', 'name_en', 'name_fa', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
