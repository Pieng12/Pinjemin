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
        ];
    }
}
