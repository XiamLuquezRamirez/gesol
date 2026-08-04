<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionOficina extends Model
{
    protected $table = 'cotizaciones_oficina';
    protected $fillable = ['solicitud_oficina_id', 'path', 'nombre_original'];

    public function solicitudOficina()
    {
        return $this->belongsTo(SolicitudOficina::class, 'solicitud_oficina_id');
    }
}
