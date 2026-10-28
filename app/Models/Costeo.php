<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Costeo extends Model
{
    protected $table = 'costeos';

    protected $fillable = [
        'folio',
        'nombre_preparacion',
        'user_id',
        'costo_total',
        'porciones',
        'costo_por_porcion',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'costo_total' => 'float',
        'porciones' => 'integer',
        'costo_por_porcion' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(CosteoDetalle::class, 'costeo_id');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class, 'referencia_id')
                    ->where('referencia_tipo', 'costeo');
    }
}
