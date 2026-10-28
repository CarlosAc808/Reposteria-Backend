<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CosteoDetalle extends Model
{
    protected $table = 'costeo_detalles';

    protected $fillable = [
        'costeo_id',
        'ingrediente_id',
        'cantidad',
        'unidad',
        'cantidad_base',
        'costo_unitario_snapshot',
        'costo_subtotal',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'cantidad_base' => 'float',
        'costo_unitario_snapshot' => 'float',
        'costo_subtotal' => 'float',
    ];

    public function costeo()
    {
        return $this->belongsTo(Costeo::class, 'costeo_id');
    }

    public function ingrediente()
    {
        return $this->belongsTo(Ingrediente::class, 'ingrediente_id');
    }
}
