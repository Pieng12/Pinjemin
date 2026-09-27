<?php

namespace App\Http\Requests;

use App\PaymentPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submitPayment', $this->route('booking')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'payment_plan' => ['nullable', Rule::in(PaymentPlan::values())],
            'method_index' => ['required', 'integer', 'min:0'],
            'transferred_at' => ['required', 'date'],
            'renter_note' => ['nullable', 'string', 'max:1000'],
            'proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'proof.image' => 'Pilih foto bukti pembayaran yang valid.',
            'proof.mimes' => 'Bukti harus berformat JPG, PNG, atau WebP.',
            'proof.max' => 'Ukuran bukti maksimal 5 MB.',
        ];
    }
}
