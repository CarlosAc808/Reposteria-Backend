<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingrediente extends Model
{
    protected $table = 'ingredientes';

    protected $fillable = [
        'codigo',
        'nombre',
        'categoria',
        'descripcion',
        'unidad_base',
        'stock_actual',
        'stock_minimo',
        'costo_unitario',
        'fecha_ultima_compra',
        'fecha_caducidad',
        'activo',
        'imagen',
    ];

    protected $casts = [
        'stock_actual' => 'float',
        'stock_minimo' => 'float',
        'costo_unitario' => 'float',
        'activo' => 'boolean',
        'fecha_ultima_compra' => 'date',
        'fecha_caducidad' => 'date',
    ];

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class, 'ingrediente_id');
    }
}
