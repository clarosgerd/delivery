<?php

namespace Tests\Feature;

use App\Models\EventoRetiroConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recategorización visual: el color se muestra siempre (02/10/2026) — antes
 * solo aparecía si `categoria_recalculada` era DISTINTA de `categoria`, como
 * alerta de desacuerdo. El fix de RecategorizacionResolver (ApiRestEvent)
 * ahora hace que `categoria_recalculada` confirme la propia categoría
 * cuando es válida, así que mostrarla solo en caso de desacuerdo escondía
 * el color en el caso normal (la inmensa mayoría). Ahora se muestra
 * siempre que ApiRestEvent haya podido resolver una categoría por
 * categoría+género+edad, sin importar si coincide con la elegida.
 */
class PosRecategorizacionColorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_el_js_muestra_el_color_sin_exigir_que_difiera_de_la_categoria(): void
    {
        $evento = EventoRetiroConfig::create([
            'evento_id' => 90013, 'evento_nombre' => 'RUN & CHILL 2026', 'csv_url' => 'https://fuente-csv.test/participantes.csv',
        ]);

        $html = $this->get("/pos/{$evento->evento_id}")->assertOk()->getContent();

        $this->assertStringContainsString('const categoriaRecalculadaHtml = r.categoria_recalculada', $html);
        $this->assertStringNotContainsString("r.categoria_recalculada !== r.categoria", $html);
    }
}
