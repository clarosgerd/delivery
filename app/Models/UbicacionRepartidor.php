<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UbicacionRepartidor extends Model
{
    protected $table = 'ubicaciones_repartidor';

    public $timestamps = false;

    protected $fillable = [
        'repartidor_id',
        'lat',
        'lng',
        'registrado_at',
    ];

    protected function casts(): array
    {
        return [
            'registrado_at' => 'datetime',
        ];
    }
}
