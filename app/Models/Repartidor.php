<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Repartidor extends Model
{
    protected $table = 'repartidores';

    public const VEHICULOS = ['moto', 'auto', 'bicicleta'];

    protected $fillable = [
        'nombre',
        'telefono',
        'tipo_vehiculo',
        'access_token',
        'lat',
        'lng',
        'ubicacion_at',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'ubicacion_at' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Repartidor $repartidor) {
            $repartidor->access_token ??= Str::random(40);
        });
    }

    public function envios(): HasMany
    {
        return $this->hasMany(EnvioDelivery::class, 'repartidor_id');
    }

    public function ubicaciones(): HasMany
    {
        return $this->hasMany(UbicacionRepartidor::class, 'repartidor_id');
    }

    public function registrarUbicacion(float $lat, float $lng): void
    {
        $this->forceFill(['lat' => $lat, 'lng' => $lng, 'ubicacion_at' => now()])->save();

        $this->ubicaciones()->create([
            'lat' => $lat,
            'lng' => $lng,
            'registrado_at' => now(),
        ]);
    }
}
