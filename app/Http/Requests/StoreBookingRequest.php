<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now(Booking::RentalTimezone)->toDateString()],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'quantity' => ['required', 'integer', 'min:1'],
            'renter_note' => ['nullable', 'string', 'max:1000'],
            'fulfillment_method' => ['sometimes', Rule::in([Booking::Pickup, Booking::Delivery])],
            'recipient_name' => ['exclude_unless:fulfillment_method,delivery', 'required', 'string', 'max:255'],
            'recipient_phone' => ['exclude_unless:fulfillment_method,delivery', 'required', 'string', 'max:30'],
            'recipient_city' => ['exclude_unless:fulfillment_method,delivery', 'required', 'string', 'max:255'],
            'recipient_address' => ['exclude_unless:fulfillment_method,delivery', 'required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal selesai',
            'quantity' => 'jumlah unit',
            'renter_note' => 'catatan untuk pemilik',
            'fulfillment_method' => 'cara menerima barang',
            'recipient_name' => 'nama penerima',
            'recipient_phone' => 'telepon penerima',
            'recipient_city' => 'kota tujuan',
            'recipient_address' => 'alamat tujuan',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'quantity.min' => 'Jumlah unit minimal 1.',
        ];
    }
}
