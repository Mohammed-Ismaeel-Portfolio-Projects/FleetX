<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Public endpoint — anyone may register as a client.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Note: deliberately no "role_id" field. Public registration always
     * creates a client account (forced in AuthService) — role is never
     * accepted from the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
