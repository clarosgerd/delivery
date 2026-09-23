<?php

namespace Tests\Feature;

use App\Models\EventoRetiroConfig;
use App\Models\RetiroSitio;
use App\Services\RetiroSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Recategorización visual por edad/género (23/09/2026) — ver plan y
 * memoria del proyecto. `RetiroSyncService` no tenía tests dedicados
 * (proyecto sin cobertura de features hasta ahora) — se agrega este
 * archivo acotado al caso nuevo, no una suite completa del servicio.
 */
class RetiroSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeCsv(array $filaExtra = []): void
    {
        $header = "Nombre,Apellido,Documento,Categoría,MontoPendiente,ConfirmarPagoSitioUrl,Estado de pago,Referencia,Talla/Polera,Souvenirs,Teléfono,Correo,Tipo de formulario";
        $columnasExtra = array_keys($filaExtra);
        $valoresExtra = array_values($filaExtra);

        $headerCompleto = $header . (count($columnasExtra) ? ',' . implode(',', $columnasExtra) : '');
        $filaCompleta = 'Ana,Perez,12345,5K,,,paid,REF001,M,,70011122,ana@test.net,Individual'
            . (count($valoresExtra) ? ',' . implode(',', $valoresExtra) : '');

        Http::fake(['fuente-csv.test/*' => Http::response($headerCompleto . "\n" . $filaCompleta, 200)]);
    }

    public function test_mapea_categoria_recalculada_y_color_del_csv(): void
    {
        $config = EventoRetiroConfig::create([
            'evento_id' => 1, 'evento_nombre' => 'Evento Test', 'csv_url' => 'https://fuente-csv.test/participantes.csv',
        ]);

        $this->fakeCsv(['CategoriaRecalculada' => '10K', 'CategoriaRecalculadaColor' => '#abcdef']);

        (new RetiroSyncService())->sincronizar($config);

        $this->assertDatabaseHas('retiros_sitio', [
            'evento_id' => 1, 'documento' => '12345',
            'categoria_recalculada' => '10K', 'categoria_recalculada_color' => '#abcdef',
        ]);
    }

    public function test_sin_columnas_de_recategorizacion_en_el_csv_quedan_null(): void
    {
        $config = EventoRetiroConfig::create([
            'evento_id' => 1, 'evento_nombre' => 'Evento Test', 'csv_url' => 'https://fuente-csv.test/participantes.csv',
        ]);

        $this->fakeCsv();

        (new RetiroSyncService())->sincronizar($config);

        $retiro = RetiroSitio::where('evento_id', 1)->where('documento', '12345')->first();
        $this->assertNull($retiro->categoria_recalculada);
        $this->assertNull($retiro->categoria_recalculada_color);
    }
}
