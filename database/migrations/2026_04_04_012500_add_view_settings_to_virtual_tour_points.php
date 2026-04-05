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
            $table->float('initial_yaw')->nullable();
            $table->float('initial_pitch')->nullable();
            $table->float('initial_fov')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('virtual_tour_points', function (Blueprint $table) {
            $table->dropColumn(['initial_yaw', 'initial_pitch', 'initial_fov']);
        });
    }
};
