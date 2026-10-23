<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;

class SubmitKycTier2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'in:PASSPORT,ID_CARD,DRIVERS_LICENSE'],
            'document_number' => ['required', 'string', 'max:100'],
            'address_proof_type' => ['required', 'string', 'in:UTILITY_BILL,BANK_STATEMENT,TAX_ASSESSMENT'],
        ];
    }
}
