<?php

namespace Tests\Feature;

use App\Models\EventoRetiroConfig;
use App\Models\RetiroSitio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Editar datos del participante desde el POS al momento de la entrega
 * (28/09/2026) — push-back a ApiRestEvent vía el link firmado por fila
 * (`editar_datos_url`). El precio de una categoría nueva lo decide
 * ApiRestEvent (acá no se conoce), así que estos tests mockean su respuesta.
 */
class PosEditarDatosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function crearEvento(array $overrides = []): EventoRetiroConfig
    {
        return EventoRetiroConfig::create(array_merge([
            'evento_id' => 90020, 'evento_nombre' => 'Evento Test', 'csv_url' => 'https://fuente-csv.test/participantes.csv',
        ], $overrides));
    }

    private function crearRetiro(array $overrides = []): RetiroSitio
    {
        return RetiroSitio::create(array_merge([
            'evento_id' => 90020,
            'documento' => 'CI' . rand(1000000, 9999999),
            'nombre' => 'Nombre', 'apellido' => 'Apellido', 'categoria' => '5K', 'categoria_id' => 10,
            'pago_status' => 'paid', 'estado' => 'pendiente',
            'editar_datos_url' => 'https://api-test.example/organizador/evento/90020/participantes/x/editar-datos?signature=abc',
        ], $overrides));
    }

    public function test_actualiza_nombre_y_apellido(): void
    {
        Http::fake(['api-test.example/*' => Http::response([
            'success' => true,
            'participante' => ['nombre' => 'Andrea', 'apellido' => 'Gomez', 'genero' => 'Femenino', 'fechaNacimiento' => '1995-01-01', 'categoriaId' => 10],
        ], 200)]);
        $evento = $this->crearEvento();
        $retiro = $this->crearRetiro();

        $response = $this->postJson("/pos/{$evento->evento_id}/retiros/{$retiro->id}/editar-datos", [
            'nombre' => 'Andrea', 'apellido' => 'Gomez',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('retiros_sitio', ['id' => $retiro->id, 'nombre' => 'Andrea', 'apellido' => 'Gomez']);
        // Bug real (28/09/2026, encontrado con un servidor real: Http::get($url, $query)
        // reemplazaba el query string del link firmado en vez de agregarle los campos, y la
        // firma (`signature=...`) se perdía). Los campos se AGREGAN a la URL que ya trae su
        // propia firma, nunca la reemplazan.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'nombre=Andrea')
            && str_contains($r->url(), 'apellido=Gomez')
            && str_contains($r->url(), 'signature=abc'));
    }

    public function test_propaga_el_error_de_categoria_con_precio_distinto(): void
    {
        Http::fake(['api-test.example/*' => Http::response([
            'success' => false, 'error' => 'El precio de esa categoría es distinto — debe pasar por Caja.',
        ], 422)]);
        $evento = $this->crearEvento();
        $retiro = $this->crearRetiro();

        $response = $this->postJson("/pos/{$evento->evento_id}/retiros/{$retiro->id}/editar-datos", [
            'categoria_id' => 99,
        ]);

        $response->assertStatus(422);
        $this->assertSame('El precio de esa categoría es distinto — debe pasar por Caja.', $response->json('error'));
        // No se tocó nada localmente.
        $this->assertDatabaseHas('retiros_sitio', ['id' => $retiro->id, 'categoria_id' => 10]);
    }

    public function test_cambio_de_categoria_exitoso_actualiza_el_nombre_local_desde_el_catalogo(): void
    {
        Http::fake(['api-test.example/*' => Http::response([
            'success' => true,
            'participante' => ['categoriaId' => 20],
        ], 200)]);
        $evento = $this->crearEvento([
            'categorias_catalogo' => ['Individual' => [['id' => 10, 'name' => '5K'], ['id' => 20, 'name' => '10K']]],
        ]);
        $retiro = $this->crearRetiro();

        $this->postJson("/pos/{$evento->evento_id}/retiros/{$retiro->id}/editar-datos", ['categoria_id' => 20])
            ->assertOk();

        $this->assertDatabaseHas('retiros_sitio', ['id' => $retiro->id, 'categoria_id' => 20, 'categoria' => '10K']);
    }

    public function test_404_si_el_retiro_es_de_otro_evento(): void
    {
        $evento = $this->crearEvento();
        $retiro = $this->crearRetiro(['evento_id' => 77777]);

        $this->postJson("/pos/{$evento->evento_id}/retiros/{$retiro->id}/editar-datos", ['nombre' => 'X'])
            ->assertNotFound();
    }

    public function test_sin_ningun_campo_da_422(): void
    {
        $evento = $this->crearEvento();
        $retiro = $this->crearRetiro();

        $this->postJson("/pos/{$evento->evento_id}/retiros/{$retiro->id}/editar-datos", [])
            ->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_error_de_conexion_no_se_da_por_exitoso(): void
    {
        Http::fake(['api-test.example/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $evento = $this->crearEvento();
        $retiro = $this->crearRetiro();

        $this->postJson("/pos/{$evento->evento_id}/retiros/{$retiro->id}/editar-datos", ['nombre' => 'Andrea'])
            ->assertStatus(422);
        $this->assertDatabaseHas('retiros_sitio', ['id' => $retiro->id, 'nombre' => 'Nombre']);
    }

    public function test_disponible_tambien_para_un_retiro_ya_entregado(): void
    {
        Http::fake(['api-test.example/*' => Http::response([
            'success' => true, 'participante' => ['nombre' => 'Andrea'],
        ], 200)]);
        $evento = $this->crearEvento();
        $retiro = $this->crearRetiro(['estado' => 'entregado', 'entregado_at' => now()]);

        $this->postJson("/pos/{$evento->evento_id}/retiros/{$retiro->id}/editar-datos", ['nombre' => 'Andrea'])
            ->assertOk();
    }

    /** Compila la vista de verdad (no solo `php -l`) y confirma que el botón/popup están. */
    public function test_la_pantalla_del_pos_renderiza_con_el_boton_y_el_popup(): void
    {
        $evento = $this->crearEvento([
            'categorias_catalogo' => ['Individual' => [['id' => 10, 'name' => '5K']]],
        ]);

        $html = $this->get(route('pos.show', $evento))->assertOk()->getContent();

        $this->assertStringContainsString('Editar datos', $html);
        $this->assertStringContainsString('id="editarDatosModal"', $html);
        $this->assertStringContainsString('abrirEditarDatos', $html);
    }
}
