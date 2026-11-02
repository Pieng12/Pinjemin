<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewBookingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviewPayment', $this->route('booking')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['verify', 'reject'])],
            'owner_comment' => [Rule::requiredIf($this->input('decision') === 'reject'), 'nullable', 'string', 'max:1000'],
            'transaction_reference' => [Rule::requiredIf($this->route('booking')->collectsViaPlatform() && $this->input('decision') === 'verify'), 'nullable', 'string', 'max:150'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['owner_comment.required' => 'Jelaskan alasan bukti pembayaran ditolak.', 'transaction_reference.required' => 'Isi referensi transaksi dari mutasi rekening.'];
    }
}
