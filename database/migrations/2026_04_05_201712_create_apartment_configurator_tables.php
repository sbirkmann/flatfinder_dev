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
        Schema::create('apartment_configurators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('apartment_configurator_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_configurator_id')->constrained('apartment_configurators', 'id', 'fk_apt_conf_rm_conf_id')->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('apartment_configurator_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_configurator_room_id')->constrained('apartment_configurator_rooms', 'id', 'fk_apt_conf_cat_rm_id')->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('apartment_configurator_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_configurator_category_id')->constrained('apartment_configurator_categories', 'id', 'fk_apt_conf_opt_cat_id')->cascadeOnDelete();
            $table->string('label');
            $table->string('color_hex')->nullable();
            $table->decimal('texture_scale', 8, 2)->default(1.0);
            $table->json('mesh_names')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apartment_configurator_options');
        Schema::dropIfExists('apartment_configurator_categories');
        Schema::dropIfExists('apartment_configurator_rooms');
        Schema::dropIfExists('apartment_configurators');
    }
};
