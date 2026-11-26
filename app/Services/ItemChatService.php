<?php

namespace App\Services;

use App\BookingStatus;
use App\ItemStatus;
use App\Models\Booking;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Item;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ItemChatService
{
    public function find(Item $item, int $renterId): ?ChatConversation
    {
        return ChatConversation::query()
            ->where('item_id', $item->id)
            ->where('renter_id', $renterId)
            ->where('owner_id', $item->user_id)
            ->first();
    }

    public function bookingIsActive(Booking $booking): bool
    {
        return ! in_array($booking->effectiveStatus(), [
            BookingStatus::Completed, BookingStatus::Rejected, BookingStatus::Cancelled, BookingStatus::Expired,
        ], true);
    }

    public function publicItem(Item $item): bool
    {
        return $item->is_active && $item->status === ItemStatus::Available;
    }

    public function bookingChatAvailable(Booking $booking): bool
    {
        return config('features.chat_enabled') && $booking->item !== null
            && ($this->bookingIsActive($booking) || $this->find($booking->item, $booking->renter_user_id) !== null);
    }

    /** @return array{blocked: bool, blocked_by_me: bool} */
    public function blockState(int $viewerId, int $otherId): array
    {
        $blockers = DB::table('chat_blocks')->where(function ($query) use ($viewerId, $otherId): void {
            $query->where('blocker_id', $viewerId)->where('blocked_id', $otherId);
        })->orWhere(function ($query) use ($viewerId, $otherId): void {
            $query->where('blocker_id', $otherId)->where('blocked_id', $viewerId);
        })->pluck('blocker_id');

        return ['blocked' => $blockers->isNotEmpty(), 'blocked_by_me' => $blockers->contains($viewerId)];
    }

    /** @param array{body?: ?string, submission_id: string, attachments?: ?list<UploadedFile>} $data */
    public function start(User $sender, Item $item, int $renterId, array $data, ?Booking $booking = null): ChatConversation
    {
        return $this->persist($sender, $data, function () use ($item, $renterId, $booking): ChatConversation {
            $this->lockParticipants($renterId, $item->user_id);
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);
            $conversation = $this->find($item, $renterId);
            if (! $conversation) {
                $canStart = $booking ? $this->bookingIsActive($booking->refresh()) : $this->publicItem($item);
                abort_unless($canStart, 404);
                $conversation = ChatConversation::query()->create([
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'renter_id' => $renterId,
                    'owner_id' => $item->user_id,
                ]);
            }

            return $conversation;
        });
    }

    /** @param array{body?: ?string, submission_id: string, attachments?: ?list<UploadedFile>} $data */
    public function send(User $sender, ChatConversation $conversation, array $data): ChatConversation
    {
        return $this->persist($sender, $data, function () use ($conversation): ChatConversation {
            $this->lockParticipants($conversation->renter_id, $conversation->owner_id);

            return ChatConversation::query()->lockForUpdate()->findOrFail($conversation->id);
        });
    }

    public function setBlock(User $user, ChatConversation $conversation, bool $blocked): void
    {
        DB::transaction(function () use ($user, $conversation, $blocked): void {
            $this->lockParticipants($conversation->renter_id, $conversation->owner_id);
            $pair = ['blocker_id' => $user->id, 'blocked_id' => $conversation->otherParticipant($user)->id];

            if ($blocked) {
                DB::table('chat_blocks')->insertOrIgnore($pair + ['created_at' => now()]);
            } else {
                DB::table('chat_blocks')->where($pair)->delete();
            }
        }, 3);
    }

    /**
     * @param  array{body?: ?string, submission_id: string, attachments?: ?list<UploadedFile>}  $data
     * @param  Closure(): ChatConversation  $resolve
     */
    private function persist(User $sender, array $data, Closure $resolve): ChatConversation
    {
        $files = $data['attachments'] ?? [];
        $metadata = array_map($this->attachmentMetadata(...), $files);
        $body = $data['body'] ?? '';
        $disk = Storage::disk('local');
        $storedPaths = [];
        $committed = false;

        try {
            [$conversation, $committed] = DB::transaction(function () use ($resolve, $sender, $data, $body, $metadata, $files, $disk, &$storedPaths): array {
                $conversation = $resolve();
                $submission = $conversation->messages()->where('sender_id', $sender->id)
                    ->where('submission_id', $data['submission_id'])->first();

                if ($submission) {
                    $previous = array_map(fn (array $attachment): array => Arr::except($attachment, 'path'), $submission->attachments ?? []);
                    if ($submission->body !== $body || $previous != $metadata) {
                        throw ValidationException::withMessages(['body' => 'Pesan sebelumnya sudah dikirim. Muat ulang percakapan sebelum mengirim isi yang berbeda.']);
                    }

                    return [$conversation, false];
                }

                if ($message = $conversation->closedMessage()) {
                    throw ValidationException::withMessages(['body' => $message]);
                }

                if ($this->blockState($conversation->renter_id, $conversation->owner_id)['blocked']) {
                    throw ValidationException::withMessages(['body' => 'Pengiriman pesan antara kedua akun tidak tersedia.']);
                }

                foreach ($files as $index => $file) {
                    $path = $storedPaths[$index] ??= 'chat-attachments/'.Str::uuid();
                    if ($disk->putFileAs('chat-attachments', $file, basename($path)) === false) {
                        throw ValidationException::withMessages(['attachments' => 'Lampiran belum dapat disimpan. Coba kirim kembali.']);
                    }
                    $metadata[$index]['path'] = $path;
                }

                $conversation->messages()->create([
                    'sender_id' => $sender->id,
                    'submission_id' => $data['submission_id'],
                    'body' => $body,
                    'attachments' => $metadata !== [] ? $metadata : null,
                ]);
                $conversation->touch();

                return [$conversation, true];
            }, 3);

            return $conversation;
        } finally {
            if (! $committed && $storedPaths !== []) {
                $disk->delete($storedPaths);
            }
        }
    }

    /** @return array{name: string, mime: string, size: int, sha256: string} */
    private function attachmentMetadata(UploadedFile $file): array
    {
        $mime = $file->getMimeType();
        $hash = hash_file('sha256', $file->getPathname());
        if (! isset(ChatMessage::AttachmentTypes[$mime]) || $hash === false) {
            throw ValidationException::withMessages(['attachments' => 'File lampiran tidak dapat dibaca. Pilih ulang file dan coba kembali.']);
        }

        $name = preg_replace('/[\p{Cc}\p{Cf}]/u', '', mb_scrub($file->getClientOriginalName()));
        $stem = trim(pathinfo(str_replace('\\', '/', $name), PATHINFO_FILENAME), " .\t");

        return [
            'name' => Str::limit($stem !== '' ? $stem : 'Lampiran', 110, '').'.'.ChatMessage::AttachmentTypes[$mime],
            'mime' => $mime,
            'size' => $file->getSize(),
            'sha256' => $hash,
        ];
    }

    private function lockParticipants(int $firstId, int $secondId): void
    {
        User::query()->whereKey([$firstId, $secondId])->orderBy('id')->lockForUpdate()->get(['id']);
    }
}
