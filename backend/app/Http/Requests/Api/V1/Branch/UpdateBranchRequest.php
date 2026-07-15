<?php

namespace App\Http\Requests\Api\V1\Branch;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
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
        $branchId = $this->route('branch')?->id ?? $this->route('branch');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('branches', 'code')->ignore($branchId),
            ],
            'name_en' => ['sometimes', 'string', 'max:255'],
            'name_fa' => ['sometimes', 'string', 'max:255'],
            'province_en' => ['sometimes', 'string', 'max:255'],
            'province_fa' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'receipt_prefix' => ['sometimes', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
