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
        Schema::table('apartments', function (Blueprint $table) {
            $table->string('available_from')->nullable();
            $table->decimal('outdoor_area', 8, 2)->nullable();
            $table->decimal('additional_costs', 8, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('virtual_tour_url')->nullable();
            $table->string('external_contact_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropColumn(['available_from', 'outdoor_area', 'additional_costs', 'description', 'virtual_tour_url', 'external_contact_url']);
        });
    }
};
