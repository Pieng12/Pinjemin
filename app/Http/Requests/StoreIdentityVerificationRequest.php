<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIdentityVerificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['ktp', 'sim'])],
            'legal_name' => ['required', 'string', 'max:255'],
            'document_number' => ['required', 'string', 'max:50', 'regex:/^[0-9]+$/', Rule::when($this->input('document_type') === 'ktp', ['size:16'])],
            'document_expires_on' => ['required_if:document_type,sim', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.now(Booking::RentalTimezone)->toDateString()],
            'document' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_type.required' => 'Pilih KTP atau SIM.', 'legal_name.required' => 'Isi nama sesuai dokumen.',
            'document_number.required' => 'Isi nomor identitas.', 'document_number.regex' => 'Nomor identitas hanya berisi angka.',
            'document_number.size' => 'NIK KTP harus terdiri dari 16 angka.',
            'document_expires_on.required_if' => 'Isi tanggal kedaluwarsa SIM.',
            'document_expires_on.after_or_equal' => 'Dokumen sudah kedaluwarsa.',
            'document.required' => 'Unggah satu foto dokumen.', 'document.image' => 'Pilih gambar dokumen yang valid.',
            'document.mimes' => 'Gunakan JPG, PNG, atau WebP.', 'document.max' => 'Ukuran dokumen maksimal 5 MB.',
            'consent.accepted' => 'Setujui penggunaan data untuk verifikasi identitas terlebih dahulu.',
        ];
    }
}
