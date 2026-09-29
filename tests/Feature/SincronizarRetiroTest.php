<?php

namespace Tests\Feature;

use App\Models\EventoRetiroConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Resiliencia por evento en `retiro:sincronizar-todos` (29/09/2026) — bug
 * real en UAT: "Scheduled command ... failed with exit code [1]". Un evento
 * cuyo sync tira una excepción no debe impedir que los demás eventos
 * configurados se sincronicen igual (antes, el foreach se cortaba ahí
 * entero). El comando siempre debe devolver éxito (self::SUCCESS) — es un
 * job del scheduler, no algo que el usuario vea fallar.
 */
class SincronizarRetiroTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_evento_con_csv_url_invalida_no_impide_sincronizar_los_demas(): void
    {
        // Evento A: csv_url que ni siquiera resuelve host (fuerza una
        // excepción real de Http, no solo un 4xx/5xx) — simula cualquier
        // fallo inesperado en sincronizar() que no esté ya atajado adentro.
        EventoRetiroConfig::create([
            'evento_id' => 1, 'evento_nombre' => 'Evento roto',
            'csv_url' => 'https://host-que-no-existe.invalid/participantes.csv',
        ]);
        EventoRetiroConfig::create([
            'evento_id' => 2, 'evento_nombre' => 'Evento bueno',
            'csv_url' => 'https://fuente-csv.test/participantes.csv',
        ]);

        $header = "Nombre,Apellido,Documento,Categoría,MontoPendiente,ConfirmarPagoSitioUrl,Estado de pago,Referencia,Talla/Polera,Souvenirs,Teléfono,Correo,Tipo de formulario";
        $fila = 'Luis,Gomez,22222,10K,,,paid,REF002,M,,70011123,luis@test.net,Individual';

        Http::fake([
            'host-que-no-existe.invalid/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('no resuelve'),
            'fuente-csv.test/*' => Http::response($header . "\n" . $fila, 200),
        ]);

        $this->artisan('retiro:sincronizar-todos')->assertSuccessful();

        $this->assertDatabaseHas('retiros_sitio', ['evento_id' => 2, 'documento' => '22222', 'nombre' => 'Luis']);
    }
}
