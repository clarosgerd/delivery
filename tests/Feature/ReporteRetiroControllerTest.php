<?php

namespace Tests\Feature;

use App\Models\EventoRetiroConfig;
use App\Models\RetiroSitio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Reporte de entregas en sitio para el organizador (23/09/2026) — ver
 * plan/memoria del proyecto. Link firmado, sin login.
 */
class ReporteRetiroControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearEvento(): EventoRetiroConfig
    {
        return EventoRetiroConfig::create([
            'evento_id' => 90020, 'evento_nombre' => 'Corporates Games 5K 2026 v2',
            'csv_url' => 'https://api.inscrito.net/organizador/evento/90020/participantes.csv',
        ]);
    }

    private function crearRetiro(int $eventoId, string $estado, array $overrides = []): RetiroSitio
    {
        return RetiroSitio::create(array_merge([
            'evento_id' => $eventoId, 'documento' => (string) rand(1000000, 9999999),
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'categoria' => '5K', 'estado' => $estado,
        ], $overrides));
    }

    public function test_sin_firma_valida_da_403(): void
    {
        $evento = $this->crearEvento();

        $this->get("/retiro/{$evento->evento_id}/reporte")->assertForbidden();
    }

    public function test_con_firma_valida_muestra_el_resumen_correcto(): void
    {
        $evento = $this->crearEvento();
        $this->crearRetiro(90020, 'entregado');
        $this->crearRetiro(90020, 'entregado');
        $this->crearRetiro(90020, 'pendiente');

        $url = URL::signedRoute('retiro.reporte', ['evento' => 90020]);
        $response = $this->get($url)->assertOk();

        $response->assertSeeText('3'); // total
        $response->assertSeeText('2'); // entregados
        $response->assertSeeText('1'); // pendientes
        $response->assertSeeText('67%'); // 2/3 redondeado
    }

    public function test_filtra_por_estado(): void
    {
        $evento = $this->crearEvento();
        $this->crearRetiro(90020, 'entregado', ['nombre' => 'Carlos', 'apellido' => 'Entregado']);
        $this->crearRetiro(90020, 'pendiente', ['nombre' => 'Maria', 'apellido' => 'Pendiente']);

        $url = URL::signedRoute('retiro.reporte', ['evento' => 90020]);
        $response = $this->get($url . '&estado=entregado')->assertOk();

        $response->assertSeeText('Carlos');
        $response->assertDontSeeText('Maria');
    }

    public function test_evento_de_otro_no_se_mezcla(): void
    {
        $this->crearEvento();
        EventoRetiroConfig::create(['evento_id' => 99999, 'evento_nombre' => 'Otro evento', 'csv_url' => 'https://x.test/x.csv']);
        $this->crearRetiro(90020, 'entregado');
        $this->crearRetiro(99999, 'entregado');
        $this->crearRetiro(99999, 'entregado');

        $url = URL::signedRoute('retiro.reporte', ['evento' => 90020]);
        $response = $this->get($url)->assertOk();

        $response->assertSeeText('1'); // total, solo del evento 90020
    }

    public function test_csv_trae_las_columnas_esperadas(): void
    {
        $this->crearEvento();
        $this->crearRetiro(90020, 'entregado', [
            'nombre' => 'Ana', 'apellido' => 'Gomez', 'documento' => '12345',
            'entregado_por' => 'Staff 1', 'entregado_at' => now(),
        ]);

        $url = URL::signedRoute('retiro.reporte.csv', ['evento' => 90020]);
        $csv = str_replace("\xEF\xBB\xBF", '', $this->get($url)->assertOk()->getContent());
        $header = str_getcsv(explode("\n", trim($csv))[0]);

        $this->assertSame(['Nombre', 'Apellido', 'Documento', 'Categoría', 'Estado', 'Entregado por', 'Entregado el'], $header);
        $this->assertStringContainsString('Ana,Gomez,12345', $csv);
    }
}
