<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetiroSitio extends Model
{
    protected $table = 'retiros_sitio';

    public const ESTADOS = ['pendiente', 'entregado'];

    protected $fillable = [
        'evento_id',
        'documento',
        'nombre',
        'apellido',
        'categoria',
        'tipo_formulario',
        'talla',
        'souvenirs',
        'telefono',
        'correo',
        'pago_status',
        'referencia',
        'estado',
        'entregado_at',
        'entregado_por',
    ];

    protected function casts(): array
    {
        return [
            'entregado_at' => 'datetime',
        ];
    }

    public function marcarEntregado(?string $entregadoPor): void
    {
        $this->update([
            'estado' => 'entregado',
            'entregado_at' => now(),
            'entregado_por' => $entregadoPor,
        ]);
    }

    public function deshacerEntrega(): void
    {
        $this->update(['estado' => 'pendiente', 'entregado_at' => null, 'entregado_por' => null]);
    }
}
