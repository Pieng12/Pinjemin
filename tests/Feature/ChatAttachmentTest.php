<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatReport;
use App\Models\Item;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ChatAttachmentTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.chat_enabled' => true]);
        Storage::fake('local');
    }

    public function test_attachment_only_message_previews_and_downloads_from_its_own_chat(): void
    {
        Storage::fake('public');
        $item = Item::factory()->create();
        $renter = User::factory()->create();
        $note = 'Ukuran barang 30 x 40 cm.';
        $photo = UploadedFile::fake()->image('kondisi.png');

        $this->actingAs($renter)->post(route('chat.items.store', $item), [
            'submission_id' => (string) Str::uuid(),
            'attachments' => [$photo, UploadedFile::fake()->createWithContent('ukuran.txt', $note)],
        ])->assertRedirect();

        $conversation = ChatConversation::query()->sole();
        $message = $conversation->messages()->sole();
        $imageUrl = route('chat.attachments.show', [$conversation, $message, 0]);
        $documentUrl = route('chat.attachments.show', [$conversation, $message, 1]);
        $this->assertSame('', $message->body);
        foreach ($message->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment['path']);
            Storage::disk('public')->assertMissing($attachment['path']);
        }

        $this->actingAs($item->user)->get(route('chat.index'))->assertOk()->assertSee('2 lampiran');
        $this->get(route('chat.show', $conversation))->assertOk()->assertSee($imageUrl)->assertSee('ukuran.txt');
        $this->getJson(route('chat.updates', $conversation))->assertOk()->assertJsonPath('last_id', $message->id);
        $this->get($imageUrl)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'inline; filename=kondisi.png')->assertHeader('Cache-Control', 'no-store, private');
        $download = $this->get($documentUrl)->assertOk()->assertDownload('ukuran.txt')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($note, $download->streamedContent());

        $other = ChatConversation::factory()->for($item)->create();
        $this->get(route('chat.attachments.show', [$other, $message, 1]))->assertNotFound();
        $this->get(route('chat.attachments.show', [$conversation, $message, 2]))->assertNotFound();
        $this->actingAs(User::factory()->create())->get($imageUrl)->assertNotFound();
    }

    public function test_file_validation_and_retries_do_not_store_duplicates(): void
    {
        $conversation = ChatConversation::factory()->create();
        $url = route('chat.send', $conversation);
        $submission = (string) Str::uuid();
        $this->actingAs($conversation->renter)->post($url, [
            'submission_id' => $submission,
            'attachments' => [UploadedFile::fake()->create('logo.jpg', 2, 'image/svg+xml')],
        ])->assertSessionHasErrors('attachments.0');
        $this->post($url, [
            'submission_id' => $submission,
            'attachments' => [UploadedFile::fake()->image('besar.png')->size(5121)],
        ])->assertSessionHasErrors('attachments.0');
        $notes = UploadedFile::fake()->createWithContent('catatan.txt', 'Perlengkapan sudah lengkap.');
        $this->post($url, ['submission_id' => $submission, 'attachments' => array_fill(0, 4, $notes)])
            ->assertSessionHasErrors('attachments');
        $this->assertDatabaseCount('chat_messages', 0);

        $payload = ['submission_id' => $submission, 'body' => 'Rincian barang', 'attachments' => [$notes]];
        $this->post($url, $payload)->assertRedirect($url);
        $this->post($url, $payload)->assertRedirect($url);
        $payload['attachments'] = [UploadedFile::fake()->createWithContent('catatan.txt', 'Berkas yang berbeda.')];
        $this->post($url, $payload)->assertSessionHasErrors('body');
        $this->assertDatabaseCount('chat_messages', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('chat-attachments'));
    }

    public function test_a_partial_storage_failure_cleans_up_and_rolls_back_the_first_message(): void
    {
        $item = Item::factory()->create();
        $renter = User::factory()->create();
        $local = Storage::disk('local');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturnUsing($local->putFileAs(...));
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturnUsing($local->delete(...));
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->actingAs($renter)->post(route('chat.items.store', $item), [
            'submission_id' => (string) Str::uuid(),
            'attachments' => [
                UploadedFile::fake()->createWithContent('satu.txt', 'Lampiran pertama.'),
                UploadedFile::fake()->createWithContent('dua.txt', 'Lampiran kedua.'),
            ],
        ])->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('chat_messages', 0);
        $this->assertDatabaseCount('chat_conversations', 0);
        $this->assertSame([], $local->allFiles('chat-attachments'));
    }

    public function test_admin_can_download_reported_attachments_through_the_report_only(): void
    {
        $conversation = ChatConversation::factory()->create();
        $attachment = UploadedFile::fake()->createWithContent('panduan.pdf', "%PDF-1.4\n%%EOF\n");
        $this->actingAs($conversation->renter)->post(route('chat.send', $conversation), [
            'submission_id' => (string) Str::uuid(), 'attachments' => [$attachment],
        ])->assertRedirect();
        $message = $conversation->messages()->sole();
        $normalUrl = route('chat.attachments.show', [$conversation, $message, 0]);

        $this->actingAs($conversation->owner)->post(route('chat.reports.store', [$conversation, $message]), ['reason' => 'spam'])->assertRedirect();
        $report = ChatReport::query()->sole();
        $reportUrl = route('admin.chat-reports.attachments.show', [$report, 0]);
        $this->get($reportUrl)->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => User::RoleAdmin]))->get($normalUrl)->assertNotFound();
        $this->get(route('admin.chat-reports.index'))->assertOk()->assertSee($reportUrl);
        $this->get($reportUrl)->assertOk()->assertDownload('panduan.pdf')->assertHeader('Content-Type', 'application/pdf');
    }
}
