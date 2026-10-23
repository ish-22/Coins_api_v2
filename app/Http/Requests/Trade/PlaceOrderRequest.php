<?php

namespace App\Http\Requests\Trade;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pair' => ['required', 'string', 'max:20'],
            'side' => ['required', 'in:BUY,SELL,buy,sell'],
            'type' => ['required', 'in:LIMIT,MARKET,limit,market'],
            'price' => ['nullable', 'numeric', 'gt:0'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
