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
        Schema::create('toilet_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('toilet_id');
            $table->foreign('toilet_id')->references('id')->on('toilets')->onDelete('cascade');
            $table->text('url');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('toilet_images');
    }
};
