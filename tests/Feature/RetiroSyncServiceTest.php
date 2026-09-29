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

    /**
     * Editar datos del participante desde el POS (28/09/2026) — CategoriaId/
     * EditarDatosUrl por fila, y CatalogoCategorias (JSON, contiene comas) se
     * arma con fputcsv real para no depender de escapar comas a mano.
     */
    public function test_mapea_categoria_id_editar_datos_url_y_catalogo_de_la_primera_fila(): void
    {
        $config = EventoRetiroConfig::create([
            'evento_id' => 1, 'evento_nombre' => 'Evento Test', 'csv_url' => 'https://fuente-csv.test/participantes.csv',
        ]);

        $header = ['Nombre', 'Apellido', 'Documento', 'Categoría', 'MontoPendiente', 'ConfirmarPagoSitioUrl',
            'Estado de pago', 'Referencia', 'Talla/Polera', 'Souvenirs', 'Teléfono', 'Correo', 'Tipo de formulario',
            'CategoriaId', 'EditarDatosUrl', 'CatalogoCategorias'];
        $catalogo = json_encode(['Individual' => [['id' => 10, 'name' => '5K'], ['id' => 20, 'name' => '10K']]]);
        $fila = ['Ana', 'Perez', '12345', '5K', '', '', 'paid', 'REF001', 'M', '', '70011122', 'ana@test.net',
            'Individual', '10', 'https://api-test.example/editar-datos?signature=abc', $catalogo];

        $buffer = fopen('php://temp', 'r+');
        fputcsv($buffer, $header);
        fputcsv($buffer, $fila);
        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);

        Http::fake(['fuente-csv.test/*' => Http::response($csv, 200)]);

        (new RetiroSyncService())->sincronizar($config);

        $this->assertDatabaseHas('retiros_sitio', [
            'evento_id' => 1, 'documento' => '12345',
            'categoria_id' => 10, 'editar_datos_url' => 'https://api-test.example/editar-datos?signature=abc',
        ]);
        $this->assertSame(
            ['Individual' => [['id' => 10, 'name' => '5K'], ['id' => 20, 'name' => '10K']]],
            $config->fresh()->categorias_catalogo
        );
    }

    /**
     * Bug real (29/09/2026, UAT: "Data truncated for column categoria_id")
     * — `categoria_id` es BIGINT en `retiros_sitio`; un valor no numérico en
     * la columna CategoriaId del CSV (vacío, o cualquier texto) no debe
     * llegar nunca a la query de UPDATE/INSERT — defensa en profundidad,
     * independiente de que ApiRestEvent ya no debería mandarlo así.
     */
    public function test_categoria_id_no_numerico_se_guarda_como_null_sin_romper_el_sync(): void
    {
        $config = EventoRetiroConfig::create([
            'evento_id' => 1, 'evento_nombre' => 'Evento Test', 'csv_url' => 'https://fuente-csv.test/participantes.csv',
        ]);

        $header = ['Nombre', 'Apellido', 'Documento', 'Categoría', 'MontoPendiente', 'ConfirmarPagoSitioUrl',
            'Estado de pago', 'Referencia', 'Talla/Polera', 'Souvenirs', 'Teléfono', 'Correo', 'Tipo de formulario',
            'CategoriaId', 'EditarDatosUrl', 'CatalogoCategorias'];
        // CategoriaId vacío: exactamente lo que ApiRestEvent manda ahora para
        // un participante sin categoría real (staff/ponente/legado) — antes
        // del fix del lado ApiRestEvent, acá llegaba texto crudo tipo "5K".
        $fila = ['Ana', 'Perez', '12345', '5K', '', '', 'paid', 'REF001', 'M', '', '70011122', 'ana@test.net',
            'Staff', '', 'https://api-test.example/editar-datos?signature=abc', ''];

        $buffer = fopen('php://temp', 'r+');
        fputcsv($buffer, $header);
        fputcsv($buffer, $fila);
        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);

        Http::fake(['fuente-csv.test/*' => Http::response($csv, 200)]);

        (new RetiroSyncService())->sincronizar($config);

        $this->assertDatabaseHas('retiros_sitio', [
            'evento_id' => 1, 'documento' => '12345', 'categoria_id' => null,
        ]);
    }

    /**
     * Resiliencia por fila (29/09/2026) — bug real en UAT: antes de esto,
     * un error al guardar UNA fila (QueryException, cast inválido, lo que
     * sea) tiraba abajo TODO el sync del evento, incluidas las filas buenas
     * que venían antes/después en el mismo CSV. Ahora una fila mala se
     * omite y el resto se sincroniza igual. Se fuerza el error con una
     * fecha de nacimiento inválida (el cast `date` del modelo la rechaza al
     * guardar) — deliberadamente OTRO disparador que el de categoria_id, ya
     * cubierto en el test de arriba, para probar la resiliencia en general,
     * no solo ese caso puntual.
     */
    public function test_una_fila_con_error_no_impide_sincronizar_las_demas_del_mismo_evento(): void
    {
        $config = EventoRetiroConfig::create([
            'evento_id' => 1, 'evento_nombre' => 'Evento Test', 'csv_url' => 'https://fuente-csv.test/participantes.csv',
        ]);

        $header = ['Nombre', 'Apellido', 'Documento', 'Categoría', 'MontoPendiente', 'ConfirmarPagoSitioUrl',
            'Estado de pago', 'Referencia', 'Talla/Polera', 'Souvenirs', 'Teléfono', 'Correo', 'Tipo de formulario',
            'FechaNacimiento'];
        $filaMala = ['Ana', 'Perez', '11111', '5K', '', '', 'paid', 'REF001', 'M', '', '70011122', 'ana@test.net',
            'Individual', 'no-es-una-fecha'];
        $filaBuena = ['Luis', 'Gomez', '22222', '10K', '', '', 'paid', 'REF002', 'M', '', '70011123', 'luis@test.net',
            'Individual', '1990-01-01'];

        $buffer = fopen('php://temp', 'r+');
        fputcsv($buffer, $header);
        fputcsv($buffer, $filaMala);
        fputcsv($buffer, $filaBuena);
        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);

        Http::fake(['fuente-csv.test/*' => Http::response($csv, 200)]);

        $resultado = (new RetiroSyncService())->sincronizar($config);

        $this->assertTrue($resultado['ok']);
        $this->assertSame(1, $resultado['actualizados']);
        $this->assertCount(1, $resultado['omitidos']);
        $this->assertDatabaseMissing('retiros_sitio', ['evento_id' => 1, 'documento' => '11111']);
        $this->assertDatabaseHas('retiros_sitio', ['evento_id' => 1, 'documento' => '22222', 'nombre' => 'Luis']);
    }
}
