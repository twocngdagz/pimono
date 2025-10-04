<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'receiver_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ((int) $value === auth()->id()) {
                        $fail('Receiver must be different from sender.');
                    }
                },
            ],
            'amount' => [
                'required',
                // ensure numeric or string formatted with up to 2 decimals
                'regex:/^\d+(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    if (! is_numeric($value)) {
                        return; // regex rule already flags invalid format
                    }
                    if ((float) $value < 0.01) {
                        $fail('Amount must be at least 0.01.');
                    }
                },
            ],
            'idempotency_key' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'receiver_id.required' => 'Receiver is required.',
            'receiver_id.integer' => 'Receiver must be an integer id.',
            'receiver_id.exists' => 'Receiver not found.',
            'amount.required' => 'Amount is required.',
            'amount.regex' => 'Amount format is invalid (max 2 decimals).',
        ];
    }
}
