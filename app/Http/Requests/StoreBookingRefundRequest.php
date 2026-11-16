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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'refunded_at.required' => 'Isi waktu pengembalian dana dalam WIB.',
            'refunded_at.date' => 'Waktu pengembalian dana tidak valid.',
            'proof.required' => 'Unggah foto bukti pengembalian dana.',
            'proof.image' => 'Pilih foto bukti pengembalian dana yang valid.',
            'proof.mimes' => 'Bukti pengembalian dana harus berformat JPG, PNG, atau WebP.',
            'proof.max' => 'Ukuran bukti pengembalian dana maksimal 5 MB.',
        ];
    }
}
