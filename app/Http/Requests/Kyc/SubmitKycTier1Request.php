<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;

class SubmitKycTier1Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'dob' => ['required', 'date'],
            'country' => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:255'],
        ];
    }
}
