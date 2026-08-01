<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventoRetiroConfig;
use App\Services\RetiroSyncService;
use Illuminate\Http\Request;

class RetiroConfigController extends Controller
{
    public function __construct(private RetiroSyncService $syncService)
    {
    }

    public function index()
    {
        return view('retiro.index', ['eventos' => EventoRetiroConfig::orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'evento_id' => ['required', 'integer'],
            'evento_nombre' => ['nullable', 'string', 'max:255'],
            'csv_url' => ['required', 'url'],
        ]);

        EventoRetiroConfig::updateOrCreate(
            ['evento_id' => $data['evento_id']],
            ['evento_nombre' => $data['evento_nombre'] ?? null, 'csv_url' => $data['csv_url']]
        );

        return back()->with('status', "Evento {$data['evento_id']} configurado para retiro en sitio.");
    }

    public function sync(EventoRetiroConfig $eventoRetiroConfig)
    {
        $resultado = $this->syncService->sincronizar($eventoRetiroConfig);

        if (! $resultado['ok']) {
            return back()->withErrors(['sync' => $resultado['error']]);
        }

        $mensaje = "Evento {$eventoRetiroConfig->evento_id}: {$resultado['actualizados']} participantes pagados sincronizados para retiro en sitio.";
        if ($resultado['omitidos']) {
            $mensaje .= ' '.count($resultado['omitidos']).' fila(s) sin documento — omitidas.';
        }

        return back()->with('status', $mensaje)->with('omitidos', $resultado['omitidos']);
    }
}
