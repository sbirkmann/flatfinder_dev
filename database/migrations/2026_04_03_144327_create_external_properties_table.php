<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->onDelete('cascade');
            $table->string('external_id')->index();
            $table->string('name')->nullable();
            $table->integer('rooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->decimal('sqm', 8, 2)->nullable();
            $table->string('marketing_type')->nullable(); // sale, rent
            $table->string('status')->nullable(); // available, reserved, sold
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('warm_rent', 15, 2)->nullable();
            $table->string('available_from')->nullable();
            $table->decimal('outdoor_area', 8, 2)->nullable();
            $table->decimal('additional_costs', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->json('raw_data')->nullable(); // store all unfiltered API return
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_properties');
    }
};
