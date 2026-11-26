<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->foreignId('renter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('renter_read_id')->default(0);
            $table->unsignedBigInteger('owner_read_id')->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'renter_id', 'owner_id']);
            $table->index(['renter_id', 'updated_at']);
            $table->index(['owner_id', 'updated_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('submission_id');
            $table->text('body');
            $table->timestamps();

            $table->index(['chat_conversation_id', 'id']);
            $table->unique(['chat_conversation_id', 'sender_id', 'submission_id'], 'chat_messages_submission_unique');
        });

        Schema::create('chat_blocks', function (Blueprint $table): void {
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['blocker_id', 'blocked_id']);
        });

        Schema::create('chat_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 30);
            $table->text('details')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_message_id', 'reporter_id']);
            $table->index(['resolved_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reports');
        Schema::dropIfExists('chat_blocks');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
