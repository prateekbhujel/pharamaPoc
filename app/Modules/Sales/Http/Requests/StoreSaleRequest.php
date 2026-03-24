<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(
            collect($this->all())->map(
                static fn (mixed $value): mixed => $value === '' ? null : $value
            )->all()
        );
    }

    public function rules(): array
    {
        return [
            'pharmacy_id' => ['required', 'integer', 'exists:pharmacies,id'],
            'medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'wallet', 'insurance-claim', 'card-plus-cash', 'wallet-plus-cash', 'reversal'])],
            'payment_status' => ['required', Rule::in(['paid', 'insurance', 'partial', 'void'])],
            'sold_at' => ['required', 'date'],
            'batch_number' => ['nullable', 'string', 'max:80'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
