<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoRetiroConfig extends Model
{
    protected $table = 'eventos_retiro_config';

    protected $fillable = [
        'evento_id',
        'evento_nombre',
        'csv_url',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }
}
