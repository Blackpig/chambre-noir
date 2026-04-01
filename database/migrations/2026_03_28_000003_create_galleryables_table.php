<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleryables', function (Blueprint $table): void {
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->morphs('galleryable');
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleryables');
    }
};
