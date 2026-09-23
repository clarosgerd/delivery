<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aviso de numeración vs. género/edad real en entrega de kit
     * (16/09/2026) — la tarjeta de entrega mostraba antes la edad "de hoy"
     * (fecha actual menos fecha de nacimiento), que puede no coincidir con
     * el método que eligió la categoría (`calculo_edad_id` en ApiRestEvent,
     * ver CalculoEdadResolver) — confundía al staff con casos como "edad
     * que cumple en el año del evento". Esta columna trae la edad REAL que
     * usó el aviso (`AlertaNumeracion`), calculada del lado de ApiRestEvent.
     */
    public function up(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->unsignedSmallInteger('edad_calculada')->nullable()->after('fecha_nacimiento');
        });
    }

    public function down(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->dropColumn('edad_calculada');
        });
    }
};
