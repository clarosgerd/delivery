<?php

namespace Tests\Feature;

use App\Models\EventoRetiroConfig;
use App\Models\RetiroSitio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Control de duplicados (24/09/2026) — PosController::entregar() no tenía
 * ningún test dedicado hasta ahora, a pesar de ser el endpoint central del
 * POS. Se agrega acá, acotado al caso nuevo (bloquear reasignar un
 * numero_corredor/chip ya entregado a OTRO participante) más los casos de
 * no-regresión del flujo existente.
 */
class PosEntregarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function crearEvento(): EventoRetiroConfig
    {
        return EventoRetiroConfig::create([
            'evento_id' => 90020, 'evento_nombre' => 'Evento Test', 'csv_url' => 'https://fuente-csv.test/participantes.csv',
        ]);
    }

    private function crearRetiro(array $overrides = []): RetiroSitio
    {
        return RetiroSitio::create(array_merge([
            'evento_id' => 90020,
            'documento' => 'CI' . rand(1000000, 9999999),
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'pago_status' => 'paid',
            'estado' => 'pendiente',
        ], $overrides));
    }

    public function test_bloquea_numero_corredor_ya_entregado_a_otro_participante(): void
    {
        $evento = $this->crearEvento();
        $this->crearRetiro(['documento' => 'CI111', 'nombre' => 'Juan', 'apellido' => 'Perez', 'estado' => 'entregado', 'numero_corredor' => '101']);
        $retiro2 = $this->crearRetiro(['documento' => 'CI222']);

        $response = $this->postJson("/pos/{$evento->evento_id}/retiros/{$retiro2->id}/entregar", [
            'numero_corredor' => '101',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('Juan Perez', $response->json('error'));
        $this->assertStringContainsString('CI111', $response->json('error'));

        $this->assertDatabaseHas('retiros_sitio', ['id' => $retiro2->id, 'estado' => 'pendiente', 'numero_corredor' => null]);
    }

    public function test_bloquea_chip_ya_entregado_a_otro_participante(): void
    {
        $evento = $this->crearEvento();
        $this->crearRetiro(['documento' => 'CI111', 'nombre' => 'Juan', 'apellido' => 'Perez', 'estado' => 'entregado', 'chip' => 'CHIP-9']);
        $retiro2 = $this->crearRetiro(['documento' => 'CI222']);

        $response = $this->postJson("/pos/{$evento->evento_id}/retiros/{$retiro2->id}/entregar", [
            'chip' => 'CHIP-9',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('chip', $response->json('error'));
        $this->assertDatabaseHas('retiros_sitio', ['id' => $retiro2->id, 'estado' => 'pendiente', 'chip' => null]);
    }

    public function test_no_bloquea_contra_un_pendiente_con_el_mismo_numero(): void
    {
        $evento = $this->crearEvento();
        $this->crearRetiro(['documento' => 'CI111', 'estado' => 'pendiente', 'numero_corredor' => '101']);
        $retiro2 = $this->crearRetiro(['documento' => 'CI222']);

        $response = $this->postJson("/pos/{$evento->evento_id}/retiros/{$retiro2->id}/entregar", [
            'numero_corredor' => '101',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('retiros_sitio', ['id' => $retiro2->id, 'estado' => 'entregado', 'numero_corredor' => '101']);
    }

    public function test_no_se_autobloquea_reenviando_el_mismo_numero_al_mismo_participante(): void
    {
        $evento = $this->crearEvento();
        $retiro = $this->crearRetiro(['documento' => 'CI111', 'numero_corredor' => '101']);

        $response = $this->postJson("/pos/{$evento->evento_id}/retiros/{$retiro->id}/entregar", [
            'numero_corredor' => '101',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('retiros_sitio', ['id' => $retiro->id, 'estado' => 'entregado', 'numero_corredor' => '101']);
    }

    public function test_entrega_sin_numero_ni_chip_sigue_funcionando_igual_que_hoy(): void
    {
        $evento = $this->crearEvento();
        $retiro = $this->crearRetiro(['documento' => 'CI111']);

        $response = $this->postJson("/pos/{$evento->evento_id}/retiros/{$retiro->id}/entregar", []);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('retiros_sitio', ['id' => $retiro->id, 'estado' => 'entregado']);
    }
}
