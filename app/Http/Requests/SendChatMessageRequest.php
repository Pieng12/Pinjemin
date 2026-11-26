<?php

namespace App\Http\Requests;

use App\Models\ChatMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($conversation = $this->route('conversation')) {
            Gate::authorize('view', $conversation);
        }
        if ($booking = $this->route('booking')) {
            abort_unless($this->user()->can('viewAsRenter', $booking) || $this->user()->can('viewAsOwner', $booking), 404);
        }
        if ($item = $this->route('item')) {
            abort_if($this->user()->id === $item->user_id, 404);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'body' => ['required_without:attachments', 'nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'list', 'max:'.ChatMessage::MaxAttachments],
            'attachments.*' => ['required', 'file', 'mimetypes:'.implode(',', array_keys(ChatMessage::AttachmentTypes)), 'max:'.ChatMessage::MaxAttachmentKilobytes],
            'submission_id' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['body' => 'Pesan', 'submission_id' => 'Pengenal pesan', 'attachments' => 'Lampiran', 'attachments.*' => 'File lampiran'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required_without' => 'Tulis pesan atau pilih lampiran untuk dikirim.',
            'attachments.max' => 'Maksimal 3 lampiran per pesan.',
            'attachments.*.mimetypes' => 'Gunakan lampiran berformat JPG, PNG, WebP, PDF, atau TXT.',
            'attachments.*.max' => 'Ukuran tiap lampiran maksimal 5 MB.',
            'attachments.*.uploaded' => 'File gagal diunggah. Periksa ukurannya dan coba kembali.',
        ];
    }
}
