<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receta extends Model
{
    protected $table = 'recetas';

    protected $fillable = [
        'nombre',
        'fotografia',
        'descripcion',
        'sabor',
        'tamano_rendimiento',
        'tiempo_preparacion',
        'tiempo_horneado',
        'instrucciones',
    ];

    protected $casts = [
        'tiempo_preparacion' => 'integer',
        'tiempo_horneado' => 'integer',
    ];

    public function ingredientes()
    {
        return $this->hasMany(RecetaIngrediente::class, 'receta_id');
    }
}
