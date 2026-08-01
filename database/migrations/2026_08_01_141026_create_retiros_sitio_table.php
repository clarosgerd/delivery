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
        Schema::create('retiros_sitio', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evento_id');
            $table->string('documento');
            $table->string('nombre')->nullable();
            $table->string('apellido')->nullable();
            $table->string('categoria')->nullable();
            $table->string('tipo_formulario')->nullable();
            $table->string('talla')->nullable();
            $table->string('souvenirs')->nullable();
            $table->string('telefono')->nullable();
            $table->string('correo')->nullable();
            $table->string('pago_status')->nullable();
            $table->string('referencia')->nullable();
            $table->enum('estado', ['pendiente', 'entregado'])->default('pendiente');
            $table->timestamp('entregado_at')->nullable();
            $table->string('entregado_por')->nullable();
            $table->timestamps();

            $table->unique(['evento_id', 'documento']);
            $table->index('referencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retiros_sitio');
    }
};
