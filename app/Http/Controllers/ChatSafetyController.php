<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatReport;
use App\Services\ItemChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChatSafetyController extends Controller
{
    public function __construct(private ItemChatService $chat) {}

    public function block(Request $request, ChatConversation $conversation): RedirectResponse
    {
        Gate::authorize('view', $conversation);
        $this->chat->setBlock($request->user(), $conversation, true);

        return to_route('chat.show', $conversation)->with('status', 'Pengguna telah diblokir.');
    }

    public function unblock(Request $request, ChatConversation $conversation): RedirectResponse
    {
        Gate::authorize('view', $conversation);
        $this->chat->setBlock($request->user(), $conversation, false);

        return to_route('chat.show', $conversation)->with('status', 'Blokir Anda telah dibuka.');
    }

    public function createReport(Request $request, ChatConversation $conversation, ChatMessage $message): View
    {
        $this->authorizeReport($request, $conversation, $message);

        return view('chat.report', [
            'conversation' => $conversation,
            'message' => $message,
            'reasons' => ChatReport::Reasons,
            'alreadyReported' => ChatReport::query()->where('chat_message_id', $message->id)
                ->where('reporter_id', $request->user()->id)->exists(),
        ]);
    }

    public function storeReport(Request $request, ChatConversation $conversation, ChatMessage $message): RedirectResponse
    {
        $this->authorizeReport($request, $conversation, $message);
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(ChatReport::Reasons))],
            'details' => ['required_if:reason,other', 'nullable', 'string', 'max:2000'],
        ], [
            'details.required_if' => 'Jelaskan alasan pelaporan jika memilih Lainnya.',
        ], ['reason' => 'Alasan laporan', 'details' => 'Keterangan']);

        $report = ChatReport::query()->createOrFirst([
            'chat_message_id' => $message->id,
            'reporter_id' => $request->user()->id,
        ], ['reason' => $data['reason'], 'details' => $data['details'] ?? null]);

        return to_route('chat.show', $conversation)->with('status', $report->wasRecentlyCreated
            ? 'Laporan pesan telah dikirim untuk ditinjau.'
            : 'Pesan ini sudah pernah Anda laporkan.');
    }

    private function authorizeReport(Request $request, ChatConversation $conversation, ChatMessage $message): void
    {
        Gate::authorize('view', $conversation);
        abort_unless($message->chat_conversation_id === $conversation->id && $message->sender_id !== $request->user()->id, 404);
    }
}
