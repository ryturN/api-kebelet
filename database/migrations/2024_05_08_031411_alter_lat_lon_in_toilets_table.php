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
        Schema::table('toilets', function (Blueprint $table) {
            $table->decimal('latitude', total: 15, places: 7)->nullable()->change();
            $table->decimal('longitude', total: 15, places: 7)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toilets', function (Blueprint $table) {
            $table->float('latitude', 8, 4)->nullable()->change();
            $table->float('longitude', 8, 4)->nullable()->change();
        });
    }
};
