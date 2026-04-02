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
        Schema::table('floors', function (Blueprint $table) {
            $table->json('points')->nullable()->after('polygons');
        });

        Schema::table('frames', function (Blueprint $table) {
            $table->json('points')->nullable()->after('polygons');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('floors', function (Blueprint $table) {
            $table->dropColumn('points');
        });

        Schema::table('frames', function (Blueprint $table) {
            $table->dropColumn('points');
        });
    }
};
