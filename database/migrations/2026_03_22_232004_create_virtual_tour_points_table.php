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
        Schema::create('virtual_tour_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_tour_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort_index')->default(0);
            $table->json('hotspots')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_tour_points');
    }
};
