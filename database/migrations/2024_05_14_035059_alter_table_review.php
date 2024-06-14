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
        Schema::table('reviews',function(Blueprint $table){
            $table->string('total_toilet')->after('rating');
            $table->string('cleanness')->after('total_toilet')->nullable();
            $table->string('facility')->after('cleanness')->nullable();
            $table->string('environment')->after('facility')->nullable();
            $table->string('crowded')->after('environment')->nullable();
            $table->string('url_img')->after('crowded')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('reviews',function(Blueprint $table){
            $table->dropColumn('total_toilet');
            $table->dropColumn('cleanness');
            $table->dropColumn('facility');
            $table->dropColumn('environment');
            $table->dropColumn('crowded');
            $table->dropColumn('url_img');
        });
    }
};
