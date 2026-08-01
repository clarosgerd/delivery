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
        Schema::create('eventos_retiro_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evento_id')->unique();
            $table->string('evento_nombre')->nullable();
            $table->text('csv_url');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos_retiro_config');
    }
};
