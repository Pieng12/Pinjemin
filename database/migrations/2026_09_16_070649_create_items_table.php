<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('condition', 30);
            $table->decimal('daily_price', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('city');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 30)->default('available');
            $table->boolean('is_active')->default(true);
            $table->text('rules')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_active']);
            $table->index('city');
            $table->index('daily_price');
            $table->index(['category_id', 'status', 'is_active']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
