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
        Schema::table('virtual_tour_points', function (Blueprint $table) {
            $table->float('minimap_x')->nullable();
            $table->float('minimap_y')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('virtual_tour_points', function (Blueprint $table) {
            $table->dropColumn(['minimap_x', 'minimap_y']);
        });
    }
};
