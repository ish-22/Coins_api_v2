<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:191'],
            'category' => ['required', 'string', 'max:50'],
            'priority' => ['required', 'in:LOW,MEDIUM,HIGH,URGENT,low,medium,high,urgent'],
            'message' => ['required', 'string'],
        ];
    }
}
