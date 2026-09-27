<?php

namespace App\Http\Requests;

use App\PaymentMethodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route('paymentMethod');

        return $method ? $this->user()?->can('update', $method) ?? false : $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(PaymentMethodType::values())],
            'provider' => [Rule::requiredIf(in_array($this->input('type'), ['bank', 'other'], true)), 'nullable', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_identifier' => [Rule::requiredIf($this->input('type') !== 'qris'), 'nullable', 'string', 'max:255'],
            'qris_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_primary' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $method = $this->route('paymentMethod');
            if ($this->input('type') === 'qris' && ! $this->hasFile('qris_image') && ! $method?->qris_path) {
                $validator->errors()->add('qris_image', 'Gambar QRIS wajib diunggah.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'qris_image.image' => 'Pilih gambar QRIS yang valid.',
            'qris_image.mimes' => 'QRIS harus berformat JPG, PNG, atau WebP.',
            'qris_image.max' => 'Ukuran QRIS maksimal 5 MB.',
        ];
    }
}
