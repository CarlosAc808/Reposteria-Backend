<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'ingrediente_id',
        'tipo_movimiento',
        'cantidad',
        'unidad',
        'existencia_anterior',
        'existencia_posterior',
        'motivo',
        'referencia_tipo',
        'referencia_id',
        'user_id',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'existencia_anterior' => 'float',
        'existencia_posterior' => 'float',
    ];

    public function ingrediente()
    {
        return $this->belongsTo(Ingrediente::class, 'ingrediente_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
