<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageBookingPayoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(match ($this->input('action')) {
            'receive' => 'receivePayout', 'report' => 'reportPaymentIssue', default => 'managePayout',
        }, $this->route('booking')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['start', 'hold', 'release', 'paid', 'receive', 'report'])],
            'note' => [Rule::requiredIf(in_array($this->input('action'), ['hold', 'release', 'report'], true)), 'nullable', 'string', 'max:1000'],
            'paid_at' => ['required_if:action,paid', 'nullable', 'date'],
            'transaction_reference' => ['required_if:action,paid', 'nullable', 'string', 'max:150'],
            'proof' => ['required_if:action,paid', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'Isi alasan tindakan ini.',
            'paid_at.required_if' => 'Isi waktu transfer dalam WIB.',
            'paid_at.date' => 'Waktu transfer tidak valid.',
            'transaction_reference.required_if' => 'Isi referensi transfer pencairan.',
            'proof.required_if' => 'Unggah bukti transfer pencairan.',
            'proof.image' => 'Bukti harus berupa gambar.',
            'proof.mimes' => 'Gunakan JPG, PNG, atau WebP.',
            'proof.max' => 'Ukuran bukti maksimal 5 MB.',
        ];
    }
}
