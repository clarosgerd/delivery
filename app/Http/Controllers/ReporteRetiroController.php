<?php

namespace App\Http\Controllers;

use App\Models\EventoRetiroConfig;
use App\Models\RetiroSitio;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * Reporte de entregas en sitio para el organizador (23/09/2026) — link
 * firmado, sin login. Distinto del dashboard de delivery de ApiRestEvent
 * (`DeliveryController`, ese es solo para envío a domicilio por courier,
 * `participantes.quiere_delivery=true`) — acá se reporta el estado real
 * del POS de retiro en sitio (`retiros_sitio.estado`), que nunca se
 * sincroniza de vuelta hacia ApiRestEvent. Mismo criterio de link firmado
 * que el resto de reportes para organizadores en el ecosistema (ver
 * ApiRestEvent/app/Http/Controllers/DeliveryController.php).
 */
class ReporteRetiroController extends Controller
{
    public function show(Request $request, EventoRetiroConfig $evento)
    {
        [$estado, $retiros] = $this->filtrar($request, $evento);

        return view('reporte.retiro', [
            'evento' => $evento,
            'estadoSeleccionado' => $estado,
            'retiros' => $retiros,
            'resumen' => $this->resumen($evento),
            // Link con su propia firma — no se puede armar reemplazando texto
            // en la URL de la página (cada ruta firma su propio path).
            'csvUrl' => URL::signedRoute('retiro.reporte.csv', array_filter([
                'evento' => $evento->evento_id,
                'estado' => $estado !== '' ? $estado : null,
            ])),
        ]);
    }

    /**
     * Firma cubre solo `evento` — `estado` viaja sin firmar, mismo patrón
     * "ignorar filtros" que ya usa DeliveryController::exportCsv() en
     * ApiRestEvent.
     */
    public function exportCsv(Request $request, EventoRetiroConfig $evento): Response
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['estado']), 403);

        [, $retiros] = $this->filtrar($request, $evento, saltarFirma: true);

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Nombre', 'Apellido', 'Documento', 'Categoría', 'Estado', 'Entregado por', 'Entregado el']);
        foreach ($retiros as $r) {
            fputcsv($handle, [
                $r->nombre,
                $r->apellido,
                $r->documento,
                $r->categoria,
                $r->estado,
                $r->entregado_por,
                optional($r->entregado_at)->format('Y-m-d H:i'),
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="entregas-sitio-evento-'.$evento->evento_id.'.csv"',
        ]);
    }

    /**
     * @return array{0: string, 1: \Illuminate\Database\Eloquent\Collection<int, RetiroSitio>}
     */
    private function filtrar(Request $request, EventoRetiroConfig $evento, bool $saltarFirma = false): array
    {
        if (! $saltarFirma) {
            abort_unless($request->hasValidSignatureWhileIgnoring(['estado']), 403);
        }

        $estado = $request->query('estado', '');
        $estado = in_array($estado, RetiroSitio::ESTADOS, true) ? $estado : '';

        $query = RetiroSitio::where('evento_id', $evento->evento_id);
        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        return [$estado, $query->orderBy('apellido')->get()];
    }

    private function resumen(EventoRetiroConfig $evento): array
    {
        $total = RetiroSitio::where('evento_id', $evento->evento_id)->count();
        $entregados = RetiroSitio::where('evento_id', $evento->evento_id)->where('estado', 'entregado')->count();

        return [
            'total' => $total,
            'entregados' => $entregados,
            'pendientes' => $total - $entregados,
            'porcentaje' => $total > 0 ? round(($entregados / $total) * 100) : 0,
        ];
    }
}
