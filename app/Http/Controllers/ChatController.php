<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendChatMessageRequest;
use App\Models\Booking;
use App\Models\ChatConversation;
use App\Models\Item;
use App\Models\User;
use App\Services\ItemChatService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChatController extends Controller
{
    private const PageSize = 30;

    public function __construct(private ItemChatService $chat) {}

    public function index(Request $request): View|JsonResponse
    {
        $inboxSearch = $this->inboxSearch($request);
        $conversations = $this->inbox($request->user(), $request->integer('page', 1), $inboxSearch);

        if ($request->expectsJson()) {
            return response()->json(['html' => view('chat.inbox', [
                'conversations' => $conversations,
                'selected' => $request->integer('selected'),
                'inboxSearch' => $inboxSearch,
            ])->render()]);
        }

        return view('chat.index', compact('conversations', 'inboxSearch'));
    }

    public function create(Request $request, Item $item): View|RedirectResponse
    {
        abort_if($request->user()->id === $item->user_id, 404);

        return $this->openComposer($request, $item, $request->user()->id);
    }

    public function fromBooking(Request $request, Booking $booking): View|RedirectResponse
    {
        $item = $this->bookingItem($request->user(), $booking);

        return $this->openComposer($request, $item, $booking->renter_user_id, $booking);
    }

    public function start(SendChatMessageRequest $request, Item $item): RedirectResponse|JsonResponse
    {
        return $this->sent($request, $this->chat->start($request->user(), $item, $request->user()->id, $request->validated()));
    }

    public function startFromBooking(SendChatMessageRequest $request, Booking $booking): RedirectResponse|JsonResponse
    {
        $item = $this->bookingItem($request->user(), $booking);

        return $this->sent($request, $this->chat->start($request->user(), $item, $booking->renter_user_id, $request->validated(), $booking));
    }

    public function show(Request $request, ChatConversation $conversation): View
    {
        Gate::authorize('view', $conversation);
        $conversation->loadMissing(['item.primaryPhoto', 'item.photos']);
        $request->validate(['before' => ['nullable', 'integer', 'min:1']]);
        $inboxSearch = $this->inboxSearch($request);
        $before = $request->integer('before') ?: null;
        $messages = $conversation->messages()
            ->when($before, fn ($query) => $query->where('id', '<', $before))
            ->latest('id')->limit(self::PageSize + 1)->get();
        $older = $messages->count() > self::PageSize;
        $messages = $messages->take(self::PageSize)->reverse()->values();
        $viewer = $request->user();
        $other = $conversation->otherParticipant($viewer);
        $block = $this->chat->blockState($viewer->id, $other->id);

        return view('chat.show', [
            'conversations' => $this->inbox($viewer, $request->integer('page', 1), $inboxSearch),
            'inboxSearch' => $inboxSearch,
            'conversation' => $conversation,
            'other' => $other,
            'messages' => $messages,
            'older' => $older,
            'before' => $before,
            'incomingId' => $this->incomingId($messages, $viewer),
            'block' => $block,
            'readOnlyReason' => $this->readOnlyReason($conversation, $block['blocked']),
        ]);
    }

    public function updates(Request $request, ChatConversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);
        $request->validate(['after' => ['nullable', 'integer', 'min:0']]);
        $viewer = $request->user();
        $messages = $conversation->messages()->where('id', '>', $request->integer('after'))
            ->oldest('id')->limit(self::PageSize + 1)->get();
        $more = $messages->count() > self::PageSize;
        $messages = $messages->take(self::PageSize);
        $block = $this->chat->blockState($viewer->id, $conversation->otherParticipant($viewer)->id);
        $readOnlyReason = $this->readOnlyReason($conversation, $block['blocked']);

        return response()->json([
            'html' => view('chat.messages', compact('messages', 'conversation'))->render(),
            'last_id' => $messages->last()?->id,
            'incoming_id' => $this->incomingId($messages, $viewer),
            'has_more' => $more,
            'read_only' => $readOnlyReason !== null,
            'read_only_reason' => $readOnlyReason,
        ]);
    }

    public function read(Request $request, ChatConversation $conversation): RedirectResponse|Response
    {
        Gate::authorize('view', $conversation);
        $validated = $request->validate(['through' => ['required', 'integer', 'min:1']]);
        $viewer = $request->user();
        $message = $conversation->messages()->where('sender_id', '!=', $viewer->id)->findOrFail($validated['through']);
        $column = $conversation->readColumn($viewer);
        ChatConversation::query()->whereKey($conversation->id)->where($column, '<', $message->id)
            ->toBase()->update([$column => $message->id]);

        return $request->expectsJson() ? response()->noContent() : back();
    }

    public function send(SendChatMessageRequest $request, ChatConversation $conversation): RedirectResponse|JsonResponse
    {
        return $this->sent($request, $this->chat->send($request->user(), $conversation, $request->validated()));
    }

    private function openComposer(Request $request, Item $item, int $renterId, ?Booking $booking = null): View|RedirectResponse
    {
        if ($conversation = $this->chat->find($item, $renterId)) {
            return redirect()->route('chat.show', $conversation);
        }

        abort_unless($booking ? $this->chat->bookingIsActive($booking) : $this->chat->publicItem($item), 404);
        $item->loadMissing(['primaryPhoto', 'photos']);
        $viewer = $request->user();
        $other = $viewer->id === $renterId ? $item->user : $booking->renter;

        return view('chat.create', [
            'conversations' => $this->inbox($viewer, 1),
            'item' => $item,
            'other' => $other,
            'booking' => $booking,
            'block' => $this->chat->blockState($viewer->id, $other->id),
        ]);
    }

    private function bookingItem(User $user, Booking $booking): Item
    {
        $booking->loadMissing('item');
        abort_unless($user->can('viewAsRenter', $booking) || $user->can('viewAsOwner', $booking), 404);

        return $booking->item;
    }

    private function inboxSearch(Request $request): string
    {
        return $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';
    }

    private function readOnlyReason(ChatConversation $conversation, bool $blocked): ?string
    {
        return $conversation->closedMessage() ?? ($blocked ? 'Pengiriman pesan antara kedua akun tidak tersedia.' : null);
    }

    private function inbox(User $user, int $page, string $search = ''): LengthAwarePaginator
    {
        $query = ChatConversation::query()->forParticipant($user)->withUnreadFor($user);
        if ($search !== '') {
            $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->whereNull('direct_key')->whereRaw("item_name LIKE ? ESCAPE '!'", [$pattern]);
        }

        return $query->with(['owner:id,name,profile_photo', 'renter:id,name,profile_photo', 'latestMessage'])
            ->latest('updated_at')->latest('id')->paginate(15, page: max(1, $page))
            ->withPath(route('chat.index'))->appends($search !== '' ? ['q' => $search] : []);
    }

    private function incomingId(Collection $messages, User $viewer): ?int
    {
        return $messages->where('sender_id', '!=', $viewer->id)->max('id');
    }

    private function sent(Request $request, ChatConversation $conversation): RedirectResponse|JsonResponse
    {
        $redirect = route('chat.show', $conversation);

        return $request->expectsJson()
            ? response()->json(['redirect' => $redirect, 'submission_id' => (string) Str::uuid()])
            : redirect()->to($redirect);
    }
}
