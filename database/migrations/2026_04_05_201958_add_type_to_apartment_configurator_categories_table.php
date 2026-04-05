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
        Schema::table('apartment_configurator_categories', function (Blueprint $table) {
            $table->enum('type', ['material', 'visibility'])->default('material')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('apartment_configurator_categories', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
