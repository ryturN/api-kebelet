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
        Schema::table('toilet_images', function (Blueprint $table) {
            //
            $table->integer('sequence')->default(1)->after('url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toilet_images', function (Blueprint $table) {
            //
            $table->dropColumn('sequence');
        });
    }
};
