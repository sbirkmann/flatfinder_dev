<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            // Source references (nullable – not every inquiry has all)
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('house_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('apartment_id')->nullable()->constrained()->nullOnDelete();

            // Contact info
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('message')->nullable();

            // Status + routing
            $table->enum('status', ['new', 'in_progress', 'done', 'rejected'])->default('new');
            $table->string('source')->default('web'); // web, phone, email, etc.

            // Internal
            $table->text('notes')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
