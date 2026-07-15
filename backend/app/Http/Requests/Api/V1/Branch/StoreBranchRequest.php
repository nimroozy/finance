<?php

namespace App\Http\Requests\Api\V1\Branch;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('branches.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:branches,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_fa' => ['required', 'string', 'max:255'],
            'province_en' => ['required', 'string', 'max:255'],
            'province_fa' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'receipt_prefix' => ['required', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
