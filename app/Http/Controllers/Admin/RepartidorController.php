<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Repartidor;
use Illuminate\Http\Request;

class RepartidorController extends Controller
{
    public function index()
    {
        return view('repartidores.index', [
            'repartidores' => Repartidor::orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'tipo_vehiculo' => ['required', 'in:'.implode(',', Repartidor::VEHICULOS)],
        ]);

        Repartidor::create($data);

        return back()->with('status', 'Repartidor agregado.');
    }

    public function destroy(Repartidor $repartidor)
    {
        $repartidor->update(['activo' => false]);

        return back()->with('status', "Repartidor #{$repartidor->id} desactivado.");
    }

    public function mapa()
    {
        return view('repartidores.mapa');
    }

    public function ubicaciones()
    {
        return response()->json(
            Repartidor::where('activo', true)
                ->whereNotNull('lat')
                ->get(['id', 'nombre', 'tipo_vehiculo', 'lat', 'lng', 'ubicacion_at'])
        );
    }
}
