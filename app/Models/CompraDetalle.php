<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraDetalle extends Model
{
    protected $table = 'compra_detalles';

    protected $fillable = [
        'compra_id',
        'ingrediente_id',
        'cantidad',
        'unidad',
        'cantidad_base',
        'costo_unitario',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'cantidad_base' => 'float',
        'costo_unitario' => 'float',
        'subtotal' => 'float',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function ingrediente()
    {
        return $this->belongsTo(Ingrediente::class, 'ingrediente_id');
    }
}
