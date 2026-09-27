<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordRefund', $this->route('booking')) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'refunded_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
