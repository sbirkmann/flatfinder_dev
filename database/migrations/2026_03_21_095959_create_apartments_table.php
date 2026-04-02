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
        Schema::create('apartments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('house_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('best_view_id')->nullable()->constrained('views')->nullOnDelete();
            $table->foreignId('best_frame_id')->nullable()->constrained('frames')->nullOnDelete();
            
            $table->string('name');
            $table->decimal('rooms', 4, 1)->nullable();
            $table->integer('bathrooms')->nullable();
            $table->decimal('sqm', 8, 2)->nullable();
            
            $table->string('marketing_type')->nullable(); // Miete / Verkauf
            $table->string('status')->nullable(); // Reserviert, Vermietet, Verkauft, Frei
            
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('warm_rent', 10, 2)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apartments');
    }
};
