<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConditionInspectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $this->user()?->can($this->input('action') === 'resolve' ? 'resolveInspection' : 'participateInspection', $booking) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['ready', 'save', 'send', 'supplement', 'agree', 'dispute', 'physical', 'resolve'])],
            'session_id' => ['nullable', 'integer'],
            'function_state' => ['nullable', Rule::in(['working', 'issues', 'untested'])],
            'accessories' => ['nullable', 'string', 'max:2000'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'condition_note' => ['nullable', 'string', 'max:2000'],
            'note' => [Rule::requiredIf(in_array($this->input('action'), ['dispute', 'resolve', 'supplement'], true)), 'nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'replace_photos' => ['sometimes', 'boolean'],
            'request_admin_review' => ['sometimes', 'boolean'],
            'physical_confirmation' => ['sometimes', 'boolean'],
            'outcome' => ['required_if:action,resolve', 'nullable', Rule::in(['confirmed', 'financial_review'])],
            'physical_event' => ['nullable', Rule::in(['none', 'handed_over', 'returned'])],
            'evidence_report_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['note.required' => 'Isi alasan atau catatan pemeriksaan.', 'photos.max' => 'Maksimal 5 foto per kiriman.', 'photos.*.image' => 'Pilih foto yang valid.', 'photos.*.mimes' => 'Foto harus JPG, PNG, atau WebP.', 'photos.*.max' => 'Setiap foto maksimal 5 MB.', 'outcome.required_if' => 'Pilih hasil pemeriksaan admin.'];
    }
}
