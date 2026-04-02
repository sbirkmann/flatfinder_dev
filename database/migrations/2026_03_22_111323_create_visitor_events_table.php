<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50)->index();       // page_view, apartment_view, view_change, filter_used, contact_click, favorite, map_open, tour_open, slider_open
            $table->unsignedBigInteger('target_id')->nullable(); // e.g. apartment_id, view_id
            $table->string('target_type', 50)->nullable();       // apartment, view, slider, etc
            $table->json('meta')->nullable();                    // extra data
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_events');
    }
};
