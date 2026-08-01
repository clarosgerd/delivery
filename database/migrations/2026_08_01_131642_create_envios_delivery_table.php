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
        Schema::create('envios_delivery', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('participante_id');
            $table->string('referencia')->nullable();
            $table->string('nombre')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->text('kit')->nullable();
            $table->string('estado')->default('pendiente');
            $table->text('notas')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['evento_id', 'participante_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('envios_delivery');
    }
};
