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
        Schema::table('envios_delivery', function (Blueprint $table) {
            $table->foreignId('repartidor_id')->nullable()->after('participante_id')
                ->constrained('repartidores')->nullOnDelete();
            $table->text('actualizar_estado_url')->nullable()->after('raw');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('envios_delivery', function (Blueprint $table) {
            $table->dropConstrainedForeignId('repartidor_id');
            $table->dropColumn('actualizar_estado_url');
        });
    }
};
