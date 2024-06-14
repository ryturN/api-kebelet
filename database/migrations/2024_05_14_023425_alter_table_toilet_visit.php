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
        //
        Schema::table('toilet_visits', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->text('ip_address')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('toilet_visits', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->change();
            $table->dropColumn('ip_address');
        });
    }
};
