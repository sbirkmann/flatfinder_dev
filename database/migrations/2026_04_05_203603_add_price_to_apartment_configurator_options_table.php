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
        Schema::table('apartment_configurator_options', function (Blueprint $table) {
            $table->decimal('price_surcharge', 10, 2)->nullable()->default(0)->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('apartment_configurator_options', function (Blueprint $table) {
            $table->dropColumn('price_surcharge');
        });
    }
};
