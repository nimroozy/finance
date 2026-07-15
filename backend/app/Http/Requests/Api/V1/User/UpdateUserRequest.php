<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'username' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'locale' => ['sometimes', 'string', 'in:en,fa'],
            'status' => ['sometimes', 'string', 'in:active,disabled'],
            'password' => [
                'sometimes',
                'string',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
            'force_password_change' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ];
    }
}
