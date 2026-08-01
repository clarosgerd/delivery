<?php

namespace App\Http\Controllers;

use App\Models\EventoDeliveryConfig;
use App\Services\DeliverySyncService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private DeliverySyncService $syncService)
    {
    }

    public function index()
    {
        return view('import.index', ['eventos' => EventoDeliveryConfig::orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'evento_id' => ['required', 'integer'],
            'evento_nombre' => ['nullable', 'string', 'max:255'],
            'json_url' => ['required', 'url'],
        ]);

        EventoDeliveryConfig::updateOrCreate(
            ['evento_id' => $data['evento_id']],
            ['evento_nombre' => $data['evento_nombre'] ?? null, 'json_url' => $data['json_url']]
        );

        return back()->with('status', "Evento {$data['evento_id']} configurado.");
    }

    public function sync(EventoDeliveryConfig $eventoDeliveryConfig)
    {
        $resultado = $this->syncService->sincronizar($eventoDeliveryConfig);

        if (! $resultado['ok']) {
            return back()->withErrors(['sync' => $resultado['error']]);
        }

        $mensaje = "Evento {$eventoDeliveryConfig->evento_id}: {$resultado['actualizados']} envíos sincronizados.";
        if ($resultado['omitidos']) {
            $mensaje .= ' '.count($resultado['omitidos'])." fila(s) sin id de participante reconocible — quedaron sin importar, ver detalle abajo.";
        }

        return back()->with('status', $mensaje)->with('omitidos', $resultado['omitidos']);
    }
}
