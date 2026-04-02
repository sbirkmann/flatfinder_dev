<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->boolean('notify_on_inquiry')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['contact_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_project');
    }
};
