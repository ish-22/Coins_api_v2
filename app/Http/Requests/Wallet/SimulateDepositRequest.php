<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class SimulateDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'tx_hash' => ['nullable', 'string', 'max:191'],
        ];
    }
}
