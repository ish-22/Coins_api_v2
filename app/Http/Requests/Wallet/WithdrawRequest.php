<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset' => ['required', 'string', 'max:20'],
            'network' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'min:15', 'max:191'],
            'memo' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'twoFactorCode' => ['nullable', 'string'],
        ];
    }
}
