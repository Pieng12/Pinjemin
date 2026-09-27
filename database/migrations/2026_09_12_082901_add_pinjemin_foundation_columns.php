<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'profile_photo')) {
                $table->string('profile_photo')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'city')) {
                $table->string('city')->nullable()->after('profile_photo');
            }

            if (! Schema::hasColumn('users', 'address')) {
                $table->text('address')->nullable()->after('city');
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('user')->index()->after('address');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'name')) {
                $table->string('name')->after('id');
            }

            if (! Schema::hasColumn('categories', 'slug')) {
                $table->string('slug')->unique()->after('name');
            }

            if (! Schema::hasColumn('categories', 'icon')) {
                $table->string('icon', 40)->nullable()->after('slug');
            }

            if (! Schema::hasColumn('categories', 'description')) {
                $table->text('description')->nullable()->after('icon');
            }

            if (! Schema::hasColumn('categories', 'is_active')) {
                $table->boolean('is_active')->default(true)->index()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'slug')) {
                $table->dropUnique(['slug']);
            }

            if (Schema::hasColumn('categories', 'is_active')) {
                $table->dropIndex(['is_active']);
            }

            $table->dropColumn([
                'name',
                'slug',
                'icon',
                'description',
                'is_active',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropIndex(['role']);
            }

            $table->dropColumn([
                'phone',
                'profile_photo',
                'city',
                'address',
                'role',
            ]);
        });
    }
};
