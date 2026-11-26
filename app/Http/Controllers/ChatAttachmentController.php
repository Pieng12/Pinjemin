<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAttachmentController extends Controller
{
    public function show(Request $request, ChatConversation $conversation, ChatMessage $message, int $attachment): StreamedResponse
    {
        Gate::authorize('view', $conversation);
        abort_unless($message->chat_conversation_id === $conversation->id, 404);

        return $this->file($message, $attachment, $request->boolean('download'));
    }

    public function reported(ChatReport $report, int $attachment): StreamedResponse
    {
        return $this->file($report->message, $attachment, download: true);
    }

    private function file(ChatMessage $message, int $attachment, bool $download): StreamedResponse
    {
        $file = $message->attachments[$attachment] ?? null;
        $disk = Storage::disk('local');
        abort_unless($file && $disk->exists($file['path']), 404);

        return $disk->response($file['path'], $file['name'], [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ], ! $download && str_starts_with($file['mime'], 'image/') ? 'inline' : 'attachment');
    }
}
