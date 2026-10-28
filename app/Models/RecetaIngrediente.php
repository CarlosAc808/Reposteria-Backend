<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecetaIngrediente extends Model
{
    protected $table = 'receta_ingredientes';

    protected $fillable = [
        'receta_id',
        'ingrediente_id',
        'nombre_ingrediente',
        'cantidad',
        'unidad',
    ];

    protected $casts = [
        'cantidad' => 'float',
    ];

    public function receta()
    {
        return $this->belongsTo(Receta::class, 'receta_id');
    }

    public function ingrediente()
    {
        return $this->belongsTo(Ingrediente::class, 'ingrediente_id');
    }
}
