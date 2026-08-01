<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoDeliveryConfig extends Model
{
    protected $table = 'eventos_delivery_config';

    protected $fillable = [
        'evento_id',
        'evento_nombre',
        'json_url',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }
}
