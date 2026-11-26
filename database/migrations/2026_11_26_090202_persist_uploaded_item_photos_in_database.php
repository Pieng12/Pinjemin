<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('item_photos', function (Blueprint $table): void {
            $table->uuid('file_token')->nullable()->unique();
        });

        $usesMySql = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
        Schema::create('item_photo_files', function (Blueprint $table) use ($usesMySql): void {
            $table->foreignId('item_photo_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('mime_type', 32);
            if ($usesMySql) {
                $table->mediumText('contents')->charset('binary');
            } else {
                $table->binary('contents');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_photo_files');
        Schema::table('item_photos', function (Blueprint $table): void {
            $table->dropUnique(['file_token']);
            $table->dropColumn('file_token');
        });
    }
};
