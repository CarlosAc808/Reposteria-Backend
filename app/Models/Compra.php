<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    protected $table = 'compras';

    protected $fillable = [
        'folio_ticket',
        'establecimiento',
        'fecha_compra',
        'importe_total',
        'observaciones',
        'user_id',
    ];

    protected $casts = [
        'fecha_compra' => 'date',
        'importe_total' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }
}
