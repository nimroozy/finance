<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'phone' => ['nullable', 'string', 'max:50'],
            'locale' => ['sometimes', 'string', 'in:en,fa'],
            'password' => [
                'required',
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
