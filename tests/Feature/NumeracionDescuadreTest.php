<?php

namespace Tests\Feature;

use App\Mail\AlertaDescuadreNumeracion;
use App\Models\EventoRetiroConfig;
use App\Models\RetiroSitio;
use App\Services\RetiroSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Descuadre de numeración/chip entre delivery y ApiRestEvent (04/10/2026):
 * el sync detecta lo que delivery tiene y la API no (o distinto) y lo deja
 * en caché para el aviso diario y el aviso del POS.
 */
class NumeracionDescuadreTest extends TestCase
{
    use RefreshDatabase;

    private const EVENTO = 90013;

    private function retiroLocal(string $documento, string $numero, string $chip): void
    {
        $retiro = new RetiroSitio();
        $retiro->forceFill([
            'evento_id' => self::EVENTO, 'documento' => $documento, 'nombre' => 'Ana', 'apellido' => 'Perez',
            'numero_corredor' => $numero, 'chip' => $chip, 'estado' => 'entregado', 'pago_status' => 'paid',
            'actualizar_numeracion_url' => 'https://api.test/numeracion?firma=x',
        ])->save();
    }

    private function config(): EventoRetiroConfig
    {
        return EventoRetiroConfig::create([
            'evento_id' => self::EVENTO, 'evento_nombre' => 'Run Chill', 'csv_url' => 'https://fuente-csv.test/participantes.csv',
        ]);
    }

    /** CSV de la API: una fila por documento con su número y chip (vacíos si no los tiene). */
    private function fakeCsv(string $documento, string $numero, string $chip): void
    {
        $header = 'Nombre,Apellido,Documento,Categoría,MontoPendiente,ConfirmarPagoSitioUrl,Estado de pago,Referencia,Talla/Polera,Souvenirs,Teléfono,Correo,Tipo de formulario,NumeroCorredor,Chip';
        $fila = "Ana,Perez,{$documento},5K,,,paid,REF001,M,,70011122,ana@test.net,Individual,{$numero},{$chip}";

        Http::fake(['fuente-csv.test/*' => Http::response($header."\n".$fila, 200)]);
    }

    private function casosEnCache(): array
    {
        return Cache::get(RetiroSyncService::claveDescuadre(self::EVENTO), []);
    }

    public function test_detecta_numero_y_chip_entregados_que_la_api_no_tiene(): void
    {
        $this->retiroLocal('12345', '322', 'C322');
        $this->fakeCsv('12345', '', '');

        (new RetiroSyncService())->sincronizar($this->config());

        $casos = $this->casosEnCache();
        $this->assertCount(1, $casos);
        $this->assertSame(['numero_pendiente_en_api', 'chip_pendiente_en_api'], $casos[0]['problemas']);
        $this->assertSame('322', $casos[0]['numero_delivery']);
        $this->assertSame('', $casos[0]['numero_api']);
    }

    public function test_detecta_numero_distinto_entre_delivery_y_api(): void
    {
        $this->retiroLocal('12345', '5', 'C5');
        $this->fakeCsv('12345', '7', 'C5');

        (new RetiroSyncService())->sincronizar($this->config());

        $this->assertSame(['numero_distinto'], $this->casosEnCache()[0]['problemas']);
    }

    public function test_si_la_api_tiene_dato_y_delivery_no_no_es_descuadre(): void
    {
        $this->retiroLocal('12345', '', '');
        $this->fakeCsv('12345', '9', 'C9');

        (new RetiroSyncService())->sincronizar($this->config());

        $this->assertSame([], $this->casosEnCache());
        $this->assertDatabaseHas('retiros_sitio', ['documento' => '12345', 'numero_corredor' => '9']);
    }

    public function test_sin_descuadre_la_cache_queda_vacia(): void
    {
        $this->retiroLocal('12345', '322', 'C322');
        $this->fakeCsv('12345', '322', 'C322');

        (new RetiroSyncService())->sincronizar($this->config());

        $this->assertSame([], $this->casosEnCache());
    }

    public function test_el_aviso_diario_manda_correo_una_vez_con_el_caso(): void
    {
        config(['services.numeracion.alerta_emails' => ['superadmin@test.net', 'otro@test.net']]);
        Mail::fake();
        Cache::put(RetiroSyncService::claveDescuadre(self::EVENTO), [[
            'documento' => '12345', 'nombre' => 'Ana', 'apellido' => 'Perez',
            'numero_delivery' => '322', 'numero_api' => '', 'chip_delivery' => 'C322', 'chip_api' => '',
            'problemas' => ['numero_pendiente_en_api'],
        ]], now()->addDay());
        $this->config();

        $this->artisan('retiro:alertar-descuadre-numeracion')->assertSuccessful();

        Mail::assertSent(AlertaDescuadreNumeracion::class, 1);
    }

    public function test_el_aviso_diario_sin_correo_configurado_solo_loguea(): void
    {
        config(['services.numeracion.alerta_emails' => []]);
        Mail::fake();
        Cache::put(RetiroSyncService::claveDescuadre(self::EVENTO), [[
            'documento' => '12345', 'nombre' => 'Ana', 'apellido' => 'Perez',
            'numero_delivery' => '322', 'numero_api' => '', 'chip_delivery' => '', 'chip_api' => '',
            'problemas' => ['numero_pendiente_en_api'],
        ]], now()->addDay());
        $this->config();

        $this->artisan('retiro:alertar-descuadre-numeracion')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_reenviar_manda_el_numero_y_el_chip_vigentes(): void
    {
        Http::fake(['api.test/*' => Http::response(['success' => true, 'alertaNumeracion' => ''], 200)]);
        $this->retiroLocal('12345', '322', 'C322');
        $retiro = RetiroSitio::where('documento', '12345')->first();

        $this->assertTrue($retiro->reenviarNumeracionAApi());

        Http::assertSent(fn ($request) => str_contains($request->url(), 'numero_corredor=322')
            && str_contains($request->url(), 'chip=C322'));
    }
}
