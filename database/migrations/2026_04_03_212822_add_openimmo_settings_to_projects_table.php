<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('projects', 'openimmo_settings')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->json('openimmo_settings')->nullable()->after('calculator_settings');
            });
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('openimmo_settings');
        });
    }
};
