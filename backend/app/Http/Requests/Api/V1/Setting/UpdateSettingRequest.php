<?php

namespace App\Http\Requests\Api\V1\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', 'timezone'],
        ];
    }
}
