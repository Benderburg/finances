<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['user_id' => 'prohibited', 'is_admin' => 'prohibited', 'billing_plan' => 'prohibited', 'balance_minor' => 'prohibited', 'status' => 'prohibited'];
    }
}
