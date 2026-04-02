<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64)->index();        // browser fingerprint hash
            $table->string('ip', 45)->nullable();
            $table->string('country', 4)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('device', 20)->nullable();          // desktop / mobile / tablet
            $table->string('language', 10)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('screen_resolution', 20)->nullable();
            $table->timestamp('first_visit_at')->useCurrent();
            $table->timestamp('last_visit_at')->useCurrent();
            $table->unsignedInteger('visit_count')->default(1);
            $table->timestamps();

            $table->unique(['project_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
