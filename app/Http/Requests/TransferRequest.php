<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount')) {
            $raw = $this->input('amount');

            if (is_int($raw)) {
                $this->merge(['amount' => $raw.'.00']);
            } elseif (is_float($raw)) {
                $this->merge(['amount' => number_format($raw, 2, '.', '')]);
            } elseif (is_string($raw)) {
                $this->merge(['amount' => trim($raw)]);
            }
        }
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
                'regex:/^\d{1,18}(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    if (! is_string($value)) {
                        return;
                    }
                    if (is_numeric($value) && (float) $value < 0.01) {
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
            'amount.regex' => 'Amount format is invalid or too large (max 18 digits before decimal, 2 after).',
        ];
    }
}
