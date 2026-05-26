<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wrap existing plain-string values in {"en": "..."} before changing column type.
        DB::statement('UPDATE galleries SET title = JSON_OBJECT("en", title) WHERE JSON_VALID(title) = 0');
        DB::statement('UPDATE gallery_images SET caption = JSON_OBJECT("en", caption) WHERE caption IS NOT NULL AND JSON_VALID(caption) = 0');

        Schema::table('galleries', function (Blueprint $table): void {
            $table->json('title')->change();
        });

        Schema::table('gallery_images', function (Blueprint $table): void {
            $table->json('caption')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table): void {
            $table->string('title')->change();
        });

        Schema::table('gallery_images', function (Blueprint $table): void {
            $table->string('caption')->nullable()->change();
        });
    }
};
