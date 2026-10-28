<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = [
        'folio',
        'cliente_nombre',
        'cliente_telefono',
        'fecha_registro',
        'fecha_entrega',
        'hora_entrega',
        'fecha_entrega_real',
        'descripcion',
        'sabor',
        'tamano',
        'relleno',
        'decoracion_tipo',
        'dedicatoria',
        'foto_referencia',
        'cantidad',
        'precio_sugerido',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'fecha_registro' => 'date',
        'fecha_entrega' => 'date',
        'fecha_entrega_real' => 'datetime',
        'cantidad' => 'integer',
        'precio_sugerido' => 'float',
    ];
}
